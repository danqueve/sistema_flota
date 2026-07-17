<?php

require_once __DIR__ . '/../../includes/auth.php';
requerirRol(['admin']);
require_once __DIR__ . '/../../includes/funciones.php';

$pdo = obtenerConexion();
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar') {
    $id           = ($_POST['id'] ?? '') !== '' ? (int) $_POST['id'] : null;
    $tipo         = ($_POST['tipo'] ?? '') === 'caja' ? 'caja' : 'banco';
    $nombre       = trim($_POST['nombre'] ?? '');
    $saldoInicial = $_POST['saldo_inicial'] !== '' ? (float) str_replace(',', '.', $_POST['saldo_inicial']) : 0.0;

    if ($nombre === '') {
        $errores[] = 'El nombre es obligatorio.';
    }

    if (!$errores) {
        if ($id) {
            $stmt = $pdo->prepare('UPDATE cuentas SET tipo=?, nombre=?, saldo_inicial=? WHERE id=?');
            $stmt->execute([$tipo, $nombre, $saldoInicial, $id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO cuentas (tipo, nombre, saldo_inicial) VALUES (?, ?, ?)');
            $stmt->execute([$tipo, $nombre, $saldoInicial]);
        }
        header('Location: cuentas.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cambiar_estado') {
    $stmt = $pdo->prepare('UPDATE cuentas SET activo=? WHERE id=?');
    $stmt->execute([(int) $_POST['activo'], (int) $_POST['id']]);
    header('Location: cuentas.php');
    exit;
}

$registroEditar = null;
if (isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM cuentas WHERE id=?');
    $stmt->execute([(int) $_GET['id']]);
    $registroEditar = $stmt->fetch() ?: null;
}

$cuentas = $pdo->query('SELECT * FROM cuentas ORDER BY activo DESC, tipo, nombre')->fetchAll();

$activo = 'cuentas';
$scriptsPagina = [BASE_URL . '/assets/js/segmentado.js'];
$tituloPagina = 'Cuentas';
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/tabs.php';
?>

<h1><?= $registroEditar ? 'Editar cuenta' : 'Nueva cuenta' ?></h1>

<?php foreach ($errores as $error): ?>
  <p class="login-error"><?= htmlspecialchars($error) ?></p>
<?php endforeach; ?>

<form method="post">
  <input type="hidden" name="accion" value="guardar">
  <input type="hidden" name="id" value="<?= $registroEditar['id'] ?? '' ?>">

  <label>Tipo</label>
  <div class="seg" data-input="tipo">
    <button type="button" data-value="banco" class="<?= ($registroEditar['tipo'] ?? 'banco') === 'banco' ? 'on' : '' ?>">Banco</button>
    <button type="button" data-value="caja" class="<?= ($registroEditar['tipo'] ?? '') === 'caja' ? 'on' : '' ?>">Caja</button>
  </div>
  <input type="hidden" name="tipo" id="tipo" value="<?= htmlspecialchars($registroEditar['tipo'] ?? 'banco') ?>">

  <label for="nombre">Nombre</label>
  <input class="campo-input" type="text" id="nombre" name="nombre" maxlength="80" required
    value="<?= htmlspecialchars($registroEditar['nombre'] ?? '') ?>">

  <label for="saldo_inicial">Saldo inicial</label>
  <input class="campo-input" type="number" id="saldo_inicial" name="saldo_inicial" min="0" step="0.01" inputmode="decimal"
    value="<?= htmlspecialchars((string) ($registroEditar['saldo_inicial'] ?? '0')) ?>">

  <button type="submit" class="btn">Guardar cuenta</button>
  <?php if ($registroEditar): ?>
    <a href="cuentas.php" class="btn sec">Cancelar edición</a>
  <?php endif; ?>
</form>

<h1 class="seccion">Cuentas cargadas</h1>

<?php foreach ($cuentas as $cuenta): ?>
  <div class="item <?= $cuenta['activo'] ? '' : 'inactivo' ?>">
    <div class="l1">
      <span class="num"><?= htmlspecialchars($cuenta['nombre']) ?></span>
      <span class="chip <?= $cuenta['tipo'] === 'banco' ? 'dep' : 'cartera' ?>"><?= htmlspecialchars($cuenta['tipo']) ?></span>
    </div>
    <div class="l2">
      <span>Saldo inicial <?= formatearImporte((float) $cuenta['saldo_inicial']) ?></span>
      <?php if (!$cuenta['activo']): ?><span class="chip rech">inactiva</span><?php endif; ?>
    </div>
    <div class="acciones">
      <a href="cuentas.php?id=<?= $cuenta['id'] ?>">Editar</a>
      <form method="post">
        <input type="hidden" name="accion" value="cambiar_estado">
        <input type="hidden" name="id" value="<?= $cuenta['id'] ?>">
        <input type="hidden" name="activo" value="<?= $cuenta['activo'] ? 0 : 1 ?>">
        <button type="submit"><?= $cuenta['activo'] ? 'Desactivar' : 'Activar' ?></button>
      </form>
    </div>
  </div>
<?php endforeach; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
