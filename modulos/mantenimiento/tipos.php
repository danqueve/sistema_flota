<?php

require_once __DIR__ . '/../../includes/auth.php';
requerirRol(['admin']);
require_once __DIR__ . '/../../includes/funciones.php';

$pdo = obtenerConexion();
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar') {
    $id     = ($_POST['id'] ?? '') !== '' ? (int) $_POST['id'] : null;
    $nombre = trim($_POST['nombre'] ?? '');

    if ($nombre === '') {
        $errores[] = 'El nombre es obligatorio.';
    }

    if (!$errores) {
        try {
            if ($id) {
                $pdo->prepare('UPDATE tipos_service SET nombre=? WHERE id=?')->execute([$nombre, $id]);
            } else {
                $pdo->prepare('INSERT INTO tipos_service (nombre) VALUES (?)')->execute([$nombre]);
            }
            header('Location: tipos.php');
            exit;
        } catch (PDOException $e) {
            $errores[] = 'No se pudo guardar el tipo de service.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar') {
    try {
        $pdo->prepare('DELETE FROM tipos_service WHERE id=?')->execute([(int) $_POST['id']]);
        header('Location: tipos.php');
        exit;
    } catch (PDOException $e) {
        $errores[] = $e->getCode() === '23000'
            ? 'No se puede eliminar: ya tiene planes o services cargados con este tipo.'
            : 'No se pudo eliminar el tipo de service.';
    }
}

$registroEditar = null;
if (isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM tipos_service WHERE id=?');
    $stmt->execute([(int) $_GET['id']]);
    $registroEditar = $stmt->fetch() ?: null;
}

$tipos = $pdo->query('SELECT * FROM tipos_service ORDER BY nombre')->fetchAll();

$activo       = 'tipos';
$tituloPagina = 'Tipos de service';
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/tabs.php';
?>

<h1><?= $registroEditar ? 'Editar tipo de service' : 'Nuevo tipo de service' ?></h1>

<?php foreach ($errores as $error): ?>
  <p class="login-error"><?= htmlspecialchars($error) ?></p>
<?php endforeach; ?>

<form method="post">
  <input type="hidden" name="accion" value="guardar">
  <input type="hidden" name="id" value="<?= $registroEditar['id'] ?? '' ?>">

  <label for="nombre">Nombre</label>
  <input class="campo-input" type="text" id="nombre" name="nombre" maxlength="80" required
    value="<?= htmlspecialchars($registroEditar['nombre'] ?? '') ?>">

  <button type="submit" class="btn">Guardar tipo de service</button>
  <?php if ($registroEditar): ?>
    <a href="tipos.php" class="btn sec">Cancelar edición</a>
  <?php endif; ?>
</form>

<h1 class="seccion">Tipos cargados</h1>

<?php foreach ($tipos as $tipo): ?>
  <div class="item">
    <div class="l1"><span class="num"><?= htmlspecialchars($tipo['nombre']) ?></span></div>
    <div class="acciones">
      <a href="tipos.php?id=<?= $tipo['id'] ?>">Editar</a>
      <form method="post" onsubmit="return confirm('¿Eliminar este tipo de service?');">
        <input type="hidden" name="accion" value="eliminar">
        <input type="hidden" name="id" value="<?= $tipo['id'] ?>">
        <button type="submit">Eliminar</button>
      </form>
    </div>
  </div>
<?php endforeach; ?>

<?php if (!$tipos): ?>
  <p class="nota">Todavía no hay tipos de service cargados.</p>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
