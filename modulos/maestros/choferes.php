<?php

require_once __DIR__ . '/../../includes/auth.php';
requerirRol(['admin']);
require_once __DIR__ . '/../../includes/funciones.php';

$pdo = obtenerConexion();
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar') {
    $id       = ($_POST['id'] ?? '') !== '' ? (int) $_POST['id'] : null;
    $nombre   = trim($_POST['nombre'] ?? '');
    $dni      = trim($_POST['dni'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');

    if ($nombre === '') {
        $errores[] = 'El nombre es obligatorio.';
    }

    if (!$errores) {
        if ($id) {
            $stmt = $pdo->prepare('UPDATE choferes SET nombre=?, dni=?, telefono=? WHERE id=?');
            $stmt->execute([$nombre, $dni ?: null, $telefono ?: null, $id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO choferes (nombre, dni, telefono) VALUES (?, ?, ?)');
            $stmt->execute([$nombre, $dni ?: null, $telefono ?: null]);
        }
        header('Location: choferes.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cambiar_estado') {
    $stmt = $pdo->prepare('UPDATE choferes SET activo=? WHERE id=?');
    $stmt->execute([(int) $_POST['activo'], (int) $_POST['id']]);
    header('Location: choferes.php');
    exit;
}

$registroEditar = null;
if (isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM choferes WHERE id=?');
    $stmt->execute([(int) $_GET['id']]);
    $registroEditar = $stmt->fetch() ?: null;
}

$choferes = $pdo->query('SELECT * FROM choferes ORDER BY activo DESC, nombre')->fetchAll();

$activo = 'choferes';
$tituloPagina = 'Choferes';
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/tabs.php';
?>

<h1><?= $registroEditar ? 'Editar chofer' : 'Nuevo chofer' ?></h1>

<?php foreach ($errores as $error): ?>
  <p class="login-error"><?= htmlspecialchars($error) ?></p>
<?php endforeach; ?>

<form method="post">
  <input type="hidden" name="accion" value="guardar">
  <input type="hidden" name="id" value="<?= $registroEditar['id'] ?? '' ?>">

  <label for="nombre">Nombre</label>
  <input class="campo-input" type="text" id="nombre" name="nombre" maxlength="80" required
    value="<?= htmlspecialchars($registroEditar['nombre'] ?? '') ?>">

  <div class="fila">
    <div>
      <label for="dni">DNI</label>
      <input class="campo-input" type="text" id="dni" name="dni" maxlength="15"
        value="<?= htmlspecialchars($registroEditar['dni'] ?? '') ?>">
    </div>
    <div>
      <label for="telefono">Teléfono</label>
      <input class="campo-input" type="text" id="telefono" name="telefono" maxlength="30"
        value="<?= htmlspecialchars($registroEditar['telefono'] ?? '') ?>">
    </div>
  </div>

  <button type="submit" class="btn">Guardar chofer</button>
  <?php if ($registroEditar): ?>
    <a href="choferes.php" class="btn sec">Cancelar edición</a>
  <?php endif; ?>
</form>

<h1 class="seccion">Choferes cargados</h1>

<?php foreach ($choferes as $chofer): ?>
  <div class="item <?= $chofer['activo'] ? '' : 'inactivo' ?>">
    <div class="l1">
      <span class="num"><?= htmlspecialchars($chofer['nombre']) ?></span>
    </div>
    <div class="l2">
      <span><?= htmlspecialchars($chofer['dni'] ?: 'Sin DNI') ?> · <?= htmlspecialchars($chofer['telefono'] ?: 'Sin teléfono') ?></span>
      <?php if (!$chofer['activo']): ?><span class="chip rech">inactivo</span><?php endif; ?>
    </div>
    <div class="acciones">
      <a href="choferes.php?id=<?= $chofer['id'] ?>">Editar</a>
      <form method="post">
        <input type="hidden" name="accion" value="cambiar_estado">
        <input type="hidden" name="id" value="<?= $chofer['id'] ?>">
        <input type="hidden" name="activo" value="<?= $chofer['activo'] ? 0 : 1 ?>">
        <button type="submit"><?= $chofer['activo'] ? 'Desactivar' : 'Activar' ?></button>
      </form>
    </div>
  </div>
<?php endforeach; ?>

<?php if (!$choferes): ?>
  <p class="nota">Todavía no hay choferes cargados.</p>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
