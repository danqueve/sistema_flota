<?php

require_once __DIR__ . '/../../includes/auth.php';
requerirRol(['admin']);
require_once __DIR__ . '/../../includes/funciones.php';

$pdo = obtenerConexion();
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar') {
    $id          = ($_POST['id'] ?? '') !== '' ? (int) $_POST['id'] : null;
    $nombre      = trim($_POST['nombre'] ?? '');
    $localidad   = trim($_POST['localidad'] ?? '');
    $tieneCtaCte = isset($_POST['tiene_cta_cte']) ? 1 : 0;

    if ($nombre === '') {
        $errores[] = 'El nombre es obligatorio.';
    }

    if (!$errores) {
        if ($id) {
            $stmt = $pdo->prepare('UPDATE estaciones SET nombre=?, localidad=?, tiene_cta_cte=? WHERE id=?');
            $stmt->execute([$nombre, $localidad ?: null, $tieneCtaCte, $id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO estaciones (nombre, localidad, tiene_cta_cte) VALUES (?, ?, ?)');
            $stmt->execute([$nombre, $localidad ?: null, $tieneCtaCte]);
        }
        header('Location: estaciones.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cambiar_estado') {
    $stmt = $pdo->prepare('UPDATE estaciones SET activo=? WHERE id=?');
    $stmt->execute([(int) $_POST['activo'], (int) $_POST['id']]);
    header('Location: estaciones.php');
    exit;
}

$registroEditar = null;
if (isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM estaciones WHERE id=?');
    $stmt->execute([(int) $_GET['id']]);
    $registroEditar = $stmt->fetch() ?: null;
}

$estaciones = $pdo->query('SELECT * FROM estaciones ORDER BY activo DESC, nombre')->fetchAll();

$activo = 'estaciones';
$tituloPagina = 'Estaciones';
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/tabs.php';
?>

<h1><?= $registroEditar ? 'Editar estación' : 'Nueva estación' ?></h1>

<?php foreach ($errores as $error): ?>
  <p class="login-error"><?= htmlspecialchars($error) ?></p>
<?php endforeach; ?>

<form method="post">
  <input type="hidden" name="accion" value="guardar">
  <input type="hidden" name="id" value="<?= $registroEditar['id'] ?? '' ?>">

  <label for="nombre">Nombre</label>
  <input class="campo-input" type="text" id="nombre" name="nombre" maxlength="80" required
    value="<?= htmlspecialchars($registroEditar['nombre'] ?? '') ?>">

  <label for="localidad">Localidad</label>
  <input class="campo-input" type="text" id="localidad" name="localidad" maxlength="80"
    value="<?= htmlspecialchars($registroEditar['localidad'] ?? '') ?>">

  <label class="campo">
    <span>Tiene cuenta corriente</span>
    <input type="checkbox" name="tiene_cta_cte" value="1"
      <?= !empty($registroEditar['tiene_cta_cte']) ? 'checked' : '' ?>>
  </label>

  <button type="submit" class="btn">Guardar estación</button>
  <?php if ($registroEditar): ?>
    <a href="estaciones.php" class="btn sec">Cancelar edición</a>
  <?php endif; ?>
</form>

<h1 class="seccion">Estaciones cargadas</h1>

<?php foreach ($estaciones as $estacion): ?>
  <div class="item <?= $estacion['activo'] ? '' : 'inactivo' ?>">
    <div class="l1">
      <span class="num"><?= htmlspecialchars($estacion['nombre']) ?></span>
      <?php if ($estacion['tiene_cta_cte']): ?><span class="chip cartera">cta. cte.</span><?php endif; ?>
    </div>
    <div class="l2">
      <span><?= htmlspecialchars($estacion['localidad'] ?: 'Sin localidad') ?></span>
      <?php if (!$estacion['activo']): ?><span class="chip rech">inactivo</span><?php endif; ?>
    </div>
    <div class="acciones">
      <a href="estaciones.php?id=<?= $estacion['id'] ?>">Editar</a>
      <form method="post">
        <input type="hidden" name="accion" value="cambiar_estado">
        <input type="hidden" name="id" value="<?= $estacion['id'] ?>">
        <input type="hidden" name="activo" value="<?= $estacion['activo'] ? 0 : 1 ?>">
        <button type="submit"><?= $estacion['activo'] ? 'Desactivar' : 'Activar' ?></button>
      </form>
    </div>
  </div>
<?php endforeach; ?>

<?php if (!$estaciones): ?>
  <p class="nota">Todavía no hay estaciones cargadas.</p>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
