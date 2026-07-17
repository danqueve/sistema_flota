<?php

require_once __DIR__ . '/../../includes/auth.php';
requerirRol(['admin']);
require_once __DIR__ . '/../../includes/funciones.php';

$pdo = obtenerConexion();
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar') {
    $id       = ($_POST['id'] ?? '') !== '' ? (int) $_POST['id'] : null;
    $nombre   = trim($_POST['nombre'] ?? '');
    $contacto = trim($_POST['contacto'] ?? '');

    if ($nombre === '') {
        $errores[] = 'El nombre es obligatorio.';
    }

    if (!$errores) {
        if ($id) {
            $stmt = $pdo->prepare('UPDATE financieras SET nombre=?, contacto=? WHERE id=?');
            $stmt->execute([$nombre, $contacto ?: null, $id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO financieras (nombre, contacto) VALUES (?, ?)');
            $stmt->execute([$nombre, $contacto ?: null]);
        }
        header('Location: financieras.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cambiar_estado') {
    $stmt = $pdo->prepare('UPDATE financieras SET activo=? WHERE id=?');
    $stmt->execute([(int) $_POST['activo'], (int) $_POST['id']]);
    header('Location: financieras.php');
    exit;
}

$registroEditar = null;
if (isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM financieras WHERE id=?');
    $stmt->execute([(int) $_GET['id']]);
    $registroEditar = $stmt->fetch() ?: null;
}

$financieras = $pdo->query('SELECT * FROM financieras ORDER BY activo DESC, nombre')->fetchAll();

$activo = 'financieras';
$tituloPagina = 'Financieras';
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/tabs.php';
?>

<h1><?= $registroEditar ? 'Editar financiera' : 'Nueva financiera' ?></h1>

<?php foreach ($errores as $error): ?>
  <p class="login-error"><?= htmlspecialchars($error) ?></p>
<?php endforeach; ?>

<form method="post">
  <input type="hidden" name="accion" value="guardar">
  <input type="hidden" name="id" value="<?= $registroEditar['id'] ?? '' ?>">

  <label for="nombre">Nombre</label>
  <input class="campo-input" type="text" id="nombre" name="nombre" maxlength="80" required
    value="<?= htmlspecialchars($registroEditar['nombre'] ?? '') ?>">

  <label for="contacto">Contacto</label>
  <input class="campo-input" type="text" id="contacto" name="contacto" maxlength="80"
    value="<?= htmlspecialchars($registroEditar['contacto'] ?? '') ?>">

  <button type="submit" class="btn">Guardar financiera</button>
  <?php if ($registroEditar): ?>
    <a href="financieras.php" class="btn sec">Cancelar edición</a>
  <?php endif; ?>
</form>

<h1 class="seccion">Financieras cargadas</h1>

<?php foreach ($financieras as $financiera): ?>
  <div class="item <?= $financiera['activo'] ? '' : 'inactivo' ?>">
    <div class="l1">
      <span class="num"><?= htmlspecialchars($financiera['nombre']) ?></span>
    </div>
    <div class="l2">
      <span><?= htmlspecialchars($financiera['contacto'] ?: 'Sin contacto') ?></span>
      <?php if (!$financiera['activo']): ?><span class="chip rech">inactiva</span><?php endif; ?>
    </div>
    <div class="acciones">
      <a href="financieras.php?id=<?= $financiera['id'] ?>">Editar</a>
      <form method="post">
        <input type="hidden" name="accion" value="cambiar_estado">
        <input type="hidden" name="id" value="<?= $financiera['id'] ?>">
        <input type="hidden" name="activo" value="<?= $financiera['activo'] ? 0 : 1 ?>">
        <button type="submit"><?= $financiera['activo'] ? 'Desactivar' : 'Activar' ?></button>
      </form>
    </div>
  </div>
<?php endforeach; ?>

<?php if (!$financieras): ?>
  <p class="nota">Todavía no hay financieras cargadas — se agregan a medida que aparecen.</p>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
