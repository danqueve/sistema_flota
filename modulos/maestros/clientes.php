<?php

require_once __DIR__ . '/../../includes/auth.php';
requerirRol(['admin']);
require_once __DIR__ . '/../../includes/funciones.php';

$pdo = obtenerConexion();
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar') {
    $id              = ($_POST['id'] ?? '') !== '' ? (int) $_POST['id'] : null;
    $razonSocial     = trim($_POST['razon_social'] ?? '');
    $cuit            = trim($_POST['cuit'] ?? '');
    $localidad       = trim($_POST['localidad'] ?? '');
    $telefono        = trim($_POST['telefono'] ?? '');
    $esPortalPallets = isset($_POST['es_portal_pallets']) ? 1 : 0;

    if ($razonSocial === '') {
        $errores[] = 'La razón social es obligatoria.';
    }

    if (!$errores) {
        if ($id) {
            $stmt = $pdo->prepare('UPDATE clientes SET razon_social=?, cuit=?, localidad=?, telefono=?, es_portal_pallets=? WHERE id=?');
            $stmt->execute([$razonSocial, $cuit ?: null, $localidad ?: null, $telefono ?: null, $esPortalPallets, $id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO clientes (razon_social, cuit, localidad, telefono, es_portal_pallets) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$razonSocial, $cuit ?: null, $localidad ?: null, $telefono ?: null, $esPortalPallets]);
        }
        header('Location: clientes.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cambiar_estado') {
    $stmt = $pdo->prepare('UPDATE clientes SET activo=? WHERE id=?');
    $stmt->execute([(int) $_POST['activo'], (int) $_POST['id']]);
    header('Location: clientes.php');
    exit;
}

$registroEditar = null;
if (isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM clientes WHERE id=?');
    $stmt->execute([(int) $_GET['id']]);
    $registroEditar = $stmt->fetch() ?: null;
}

$clientes = $pdo->query('SELECT * FROM clientes ORDER BY activo DESC, razon_social')->fetchAll();

$activo = 'clientes';
$tituloPagina = 'Clientes';
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/tabs.php';
?>

<h1><?= $registroEditar ? 'Editar cliente' : 'Nuevo cliente' ?></h1>

<?php foreach ($errores as $error): ?>
  <p class="login-error"><?= htmlspecialchars($error) ?></p>
<?php endforeach; ?>

<form method="post">
  <input type="hidden" name="accion" value="guardar">
  <input type="hidden" name="id" value="<?= $registroEditar['id'] ?? '' ?>">

  <label for="razon_social">Razón social</label>
  <input class="campo-input" type="text" id="razon_social" name="razon_social" maxlength="120" required
    value="<?= htmlspecialchars($registroEditar['razon_social'] ?? '') ?>">

  <div class="fila">
    <div>
      <label for="cuit">CUIT</label>
      <input class="campo-input" type="text" id="cuit" name="cuit" maxlength="15"
        value="<?= htmlspecialchars($registroEditar['cuit'] ?? '') ?>">
    </div>
    <div>
      <label for="telefono">Teléfono</label>
      <input class="campo-input" type="text" id="telefono" name="telefono" maxlength="30"
        value="<?= htmlspecialchars($registroEditar['telefono'] ?? '') ?>">
    </div>
  </div>

  <label for="localidad">Localidad</label>
  <input class="campo-input" type="text" id="localidad" name="localidad" maxlength="80"
    value="<?= htmlspecialchars($registroEditar['localidad'] ?? '') ?>">

  <label class="campo">
    <span>Tiene acceso al portal de pallets (empresa de Entre Ríos)</span>
    <input type="checkbox" name="es_portal_pallets" value="1"
      <?= !empty($registroEditar['es_portal_pallets']) ? 'checked' : '' ?>>
  </label>

  <button type="submit" class="btn">Guardar cliente</button>
  <?php if ($registroEditar): ?>
    <a href="clientes.php" class="btn sec">Cancelar edición</a>
  <?php endif; ?>
</form>

<h1 class="seccion">Clientes cargados</h1>

<?php foreach ($clientes as $cliente): ?>
  <div class="item <?= $cliente['activo'] ? '' : 'inactivo' ?>">
    <div class="l1">
      <span class="num"><?= htmlspecialchars($cliente['razon_social']) ?></span>
      <?php if ($cliente['es_portal_pallets']): ?><span class="chip dep">portal pallets</span><?php endif; ?>
    </div>
    <div class="l2">
      <span><?= htmlspecialchars($cliente['localidad'] ?: 'Sin localidad') ?></span>
      <?php if (!$cliente['activo']): ?><span class="chip rech">inactivo</span><?php endif; ?>
    </div>
    <div class="acciones">
      <a href="clientes.php?id=<?= $cliente['id'] ?>">Editar</a>
      <form method="post">
        <input type="hidden" name="accion" value="cambiar_estado">
        <input type="hidden" name="id" value="<?= $cliente['id'] ?>">
        <input type="hidden" name="activo" value="<?= $cliente['activo'] ? 0 : 1 ?>">
        <button type="submit"><?= $cliente['activo'] ? 'Desactivar' : 'Activar' ?></button>
      </form>
    </div>
  </div>
<?php endforeach; ?>

<?php if (!$clientes): ?>
  <p class="nota">Todavía no hay clientes cargados.</p>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
