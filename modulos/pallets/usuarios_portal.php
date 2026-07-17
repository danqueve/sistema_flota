<?php

require_once __DIR__ . '/../../includes/auth.php';
requerirRol(['admin']);
require_once __DIR__ . '/../../includes/funciones.php';

$pdo = obtenerConexion();
$errores = [];

$clientes = $pdo->query(
    'SELECT id, razon_social FROM clientes WHERE es_portal_pallets = 1 AND activo = 1 ORDER BY razon_social'
)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar') {
    $clienteId = (int) ($_POST['cliente_id'] ?? 0);
    $nombre    = trim($_POST['nombre'] ?? '');
    $usuario   = trim($_POST['usuario'] ?? '');
    $clave     = (string) ($_POST['clave'] ?? '');

    if (!$clienteId) {
        $errores[] = 'Elegí un cliente.';
    }
    if ($nombre === '') {
        $errores[] = 'El nombre es obligatorio.';
    }
    if ($usuario === '') {
        $errores[] = 'El usuario es obligatorio.';
    }
    if (strlen($clave) < 6) {
        $errores[] = 'La clave tiene que tener al menos 6 caracteres.';
    }

    if (!$errores) {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO usuarios_portal (cliente_id, nombre, usuario, clave_hash) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$clienteId, $nombre, $usuario, password_hash($clave, PASSWORD_DEFAULT)]);
            header('Location: usuarios_portal.php');
            exit;
        } catch (PDOException $e) {
            $errores[] = $e->getCode() === '23000'
                ? 'Ya existe un usuario de portal con ese nombre de usuario.'
                : 'No se pudo guardar el usuario.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cambiar_estado') {
    $stmt = $pdo->prepare('UPDATE usuarios_portal SET activo=? WHERE id=?');
    $stmt->execute([(int) $_POST['activo'], (int) $_POST['id']]);
    header('Location: usuarios_portal.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'resetear_clave') {
    $id    = (int) ($_POST['id'] ?? 0);
    $clave = (string) ($_POST['clave_nueva'] ?? '');

    if (strlen($clave) < 6) {
        $errores[] = 'La clave nueva tiene que tener al menos 6 caracteres.';
    } else {
        $stmt = $pdo->prepare('UPDATE usuarios_portal SET clave_hash=?, intentos_fallidos=0, bloqueado_hasta=NULL WHERE id=?');
        $stmt->execute([password_hash($clave, PASSWORD_DEFAULT), $id]);
        header('Location: usuarios_portal.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'desbloquear') {
    $stmt = $pdo->prepare('UPDATE usuarios_portal SET intentos_fallidos=0, bloqueado_hasta=NULL WHERE id=?');
    $stmt->execute([(int) $_POST['id']]);
    header('Location: usuarios_portal.php');
    exit;
}

$usuariosPortal = $pdo->query(
    'SELECT up.*, cl.razon_social
     FROM usuarios_portal up
     JOIN clientes cl ON cl.id = up.cliente_id
     ORDER BY up.activo DESC, cl.razon_social, up.nombre'
)->fetchAll();

$activo        = 'usuarios';
$scriptsPagina = [BASE_URL . '/assets/js/segmentado.js'];
$tituloPagina  = 'Usuarios del portal';
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/tabs.php';
?>

<h1>Nuevo usuario de portal</h1>

<?php foreach ($errores as $error): ?>
  <p class="login-error"><?= htmlspecialchars($error) ?></p>
<?php endforeach; ?>

<?php if (!$clientes): ?>
  <p class="nota">No hay clientes con acceso al portal de tarimas.</p>
<?php else: ?>

<form method="post">
  <input type="hidden" name="accion" value="guardar">

  <label>Cliente</label>
  <div class="seg" data-input="cliente_id">
    <?php foreach ($clientes as $i => $cliente): ?>
      <button type="button" data-value="<?= $cliente['id'] ?>" class="<?= $i === 0 ? 'on' : '' ?>"><?= htmlspecialchars($cliente['razon_social']) ?></button>
    <?php endforeach; ?>
  </div>
  <input type="hidden" name="cliente_id" id="cliente_id" value="<?= $clientes[0]['id'] ?? '' ?>">

  <div class="fila">
    <div>
      <label for="nombre">Nombre</label>
      <input class="campo-input" type="text" id="nombre" name="nombre" maxlength="80" required>
    </div>
    <div>
      <label for="usuario">Usuario</label>
      <input class="campo-input" type="text" id="usuario" name="usuario" maxlength="40" required autocomplete="off">
    </div>
  </div>

  <label for="clave">Clave</label>
  <input class="campo-input" type="text" id="clave" name="clave" minlength="6" required autocomplete="off">

  <button type="submit" class="btn">Guardar usuario</button>
</form>

<?php endif; ?>

<h1 class="seccion">Usuarios cargados</h1>

<?php foreach ($usuariosPortal as $up):
    $bloqueado = $up['bloqueado_hasta'] && strtotime($up['bloqueado_hasta']) > time();
?>
  <div class="item <?= $up['activo'] ? '' : 'inactivo' ?>">
    <div class="l1">
      <span class="num"><?= htmlspecialchars($up['nombre']) ?> (<?= htmlspecialchars($up['usuario']) ?>)</span>
      <?php if (!$up['activo']): ?><span class="chip rech">inactivo</span>
      <?php elseif ($bloqueado): ?><span class="chip rech">bloqueado</span>
      <?php else: ?><span class="chip ok">activo</span><?php endif; ?>
    </div>
    <div class="l2">
      <span><?= htmlspecialchars($up['razon_social']) ?></span>
    </div>
    <div class="acciones">
      <button type="button" data-accion="resetear" data-id="<?= $up['id'] ?>">Resetear clave</button>
      <form method="post">
        <input type="hidden" name="accion" value="cambiar_estado">
        <input type="hidden" name="id" value="<?= $up['id'] ?>">
        <input type="hidden" name="activo" value="<?= $up['activo'] ? 0 : 1 ?>">
        <button type="submit"><?= $up['activo'] ? 'Desactivar' : 'Activar' ?></button>
      </form>
      <?php if ($bloqueado): ?>
        <form method="post">
          <input type="hidden" name="accion" value="desbloquear">
          <input type="hidden" name="id" value="<?= $up['id'] ?>">
          <button type="submit" class="p">Desbloquear</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>

<?php if (!$usuariosPortal): ?>
  <p class="nota">Todavía no hay usuarios de portal cargados.</p>
<?php endif; ?>

<dialog id="dialogResetear">
  <h3>Resetear clave</h3>
  <form method="post">
    <input type="hidden" name="accion" value="resetear_clave">
    <input type="hidden" name="id" id="reset_id">
    <label for="clave_nueva">Clave nueva</label>
    <input class="campo-input" type="text" id="clave_nueva" name="clave_nueva" minlength="6" required autocomplete="off">
    <button type="submit" class="btn">Confirmar</button>
    <button type="button" class="btn-cerrar">Cancelar</button>
  </form>
</dialog>

<script>
  document.querySelectorAll('[data-accion="resetear"]').forEach(function (boton) {
    boton.addEventListener('click', function () {
      document.getElementById('reset_id').value = boton.dataset.id;
      document.getElementById('clave_nueva').value = '';
      document.getElementById('dialogResetear').showModal();
    });
  });
  document.querySelectorAll('#dialogResetear .btn-cerrar').forEach(function (boton) {
    boton.addEventListener('click', function () {
      document.getElementById('dialogResetear').close();
    });
  });
</script>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
