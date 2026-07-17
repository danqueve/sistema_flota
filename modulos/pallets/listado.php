<?php

require_once __DIR__ . '/../../includes/auth.php';
requerirRol(['admin']);
require_once __DIR__ . '/../../includes/funciones.php';

$pdo = obtenerConexion();

$clientes = $pdo->query(
    'SELECT id, razon_social FROM clientes WHERE es_portal_pallets = 1 ORDER BY razon_social'
)->fetchAll();

$tipo      = in_array($_GET['tipo'] ?? '', ['recepcion', 'devolucion'], true) ? $_GET['tipo'] : '';
$clienteId = (int) ($_GET['cliente_id'] ?? 0);
$periodo   = $_GET['periodo'] ?? '';
if ($periodo !== '' && !preg_match('/^\d{4}-\d{2}$/', $periodo)) {
    $periodo = '';
}

$condiciones = [];
$parametros  = [];

if ($tipo) {
    $condiciones[] = 'r.tipo = ?';
    $parametros[]  = $tipo;
}
if ($clienteId) {
    $condiciones[] = 'r.cliente_id = ?';
    $parametros[]  = $clienteId;
}
if ($periodo) {
    $condiciones[] = 'DATE_FORMAT(r.fecha, "%Y-%m") = ?';
    $parametros[]  = $periodo;
}

$where = $condiciones ? ('WHERE ' . implode(' AND ', $condiciones)) : '';

$stmt = $pdo->prepare(
    "SELECT r.*, cl.razon_social, pm.sanos, pm.rotos, pm.reacondicionados, pm.separadores
     FROM remitos r
     JOIN clientes cl ON cl.id = r.cliente_id
     JOIN pallets_movimientos pm ON pm.remito_id = r.id
     $where
     ORDER BY r.numero DESC"
);
$stmt->execute($parametros);
$remitos = $stmt->fetchAll();

$activo        = 'listado';
$scriptsPagina = [BASE_URL . '/assets/js/segmentado.js', BASE_URL . '/assets/js/filtros-auto.js'];
$tituloPagina  = 'Remitos';
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/tabs.php';
?>

<h1>Remitos de tarimas (pallets)</h1>

<form method="get" id="formFiltros">
  <label>Tipo</label>
  <div class="seg" data-input="tipo">
    <button type="button" data-value="" class="<?= $tipo === '' ? 'on' : '' ?>">Todos</button>
    <button type="button" data-value="recepcion" class="<?= $tipo === 'recepcion' ? 'on' : '' ?>">Recepción</button>
    <button type="button" data-value="devolucion" class="<?= $tipo === 'devolucion' ? 'on' : '' ?>">Devolución</button>
  </div>
  <input type="hidden" name="tipo" id="tipo" class="filtro-auto" value="<?= htmlspecialchars($tipo) ?>">

  <label for="cliente_id">Cliente</label>
  <select class="campo-input filtro-auto" id="cliente_id" name="cliente_id">
    <option value="">Todos</option>
    <?php foreach ($clientes as $cliente): ?>
      <option value="<?= $cliente['id'] ?>" <?= $clienteId === (int) $cliente['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cliente['razon_social']) ?></option>
    <?php endforeach; ?>
  </select>

  <label for="periodo">Período</label>
  <input class="campo-input filtro-auto" type="month" id="periodo" name="periodo" value="<?= htmlspecialchars($periodo) ?>">
</form>

<?php foreach ($remitos as $remito): ?>
  <div class="item">
    <div class="l1">
      <span class="num"><a href="detalle.php?id=<?= $remito['id'] ?>">Nº <?= str_pad((string) $remito['numero'], 6, '0', STR_PAD_LEFT) ?></a></span>
      <span class="chip <?= $remito['tipo'] === 'recepcion' ? 'ok' : 'dep' ?>"><?= $remito['tipo'] === 'recepcion' ? 'recepción' : 'devolución' ?></span>
    </div>
    <div class="l2">
      <span><?= formatearFecha($remito['fecha']) ?> · <?= htmlspecialchars($remito['razon_social']) ?></span>
      <span><?= (int) $remito['sanos'] + (int) $remito['rotos'] + (int) $remito['reacondicionados'] ?> tarimas · <?= (int) $remito['separadores'] ?> separadores</span>
    </div>
    <div class="acciones">
      <a href="detalle.php?id=<?= $remito['id'] ?>">Detalle</a>
      <a href="pdf.php?id=<?= $remito['id'] ?>" target="_blank" rel="noopener">Ver PDF</a>
    </div>
  </div>
<?php endforeach; ?>

<?php if (!$remitos): ?>
  <p class="nota">No hay remitos para esos filtros.</p>
<?php endif; ?>

<a href="nuevo.php" class="btn seccion">+ Nuevo remito</a>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
