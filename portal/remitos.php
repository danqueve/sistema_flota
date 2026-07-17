<?php

require_once __DIR__ . '/includes/auth_portal.php';
requerirLoginPortal();
require_once __DIR__ . '/../includes/funciones.php';

$usuarioPortal = usuarioPortalActual();
$pdo = obtenerConexion();

$tipo  = in_array($_GET['tipo'] ?? '', ['recepcion', 'devolucion'], true) ? $_GET['tipo'] : '';
$desde = $_GET['desde'] ?? '';
$hasta = $_GET['hasta'] ?? '';
if ($desde !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
    $desde = '';
}
if ($hasta !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
    $hasta = '';
}

// cliente_id siempre sale de la sesión del portal, nunca de la petición.
$condiciones = ['r.cliente_id = ?'];
$parametros  = [$usuarioPortal['cliente_id']];

if ($tipo) {
    $condiciones[] = 'r.tipo = ?';
    $parametros[]  = $tipo;
}
if ($desde) {
    $condiciones[] = 'r.fecha >= ?';
    $parametros[]  = $desde;
}
if ($hasta) {
    $condiciones[] = 'r.fecha <= ?';
    $parametros[]  = $hasta;
}

$stmt = $pdo->prepare(
    'SELECT r.*, pm.sanos, pm.rotos, pm.reacondicionados, pm.separadores
     FROM remitos r
     JOIN pallets_movimientos pm ON pm.remito_id = r.id
     WHERE ' . implode(' AND ', $condiciones) . '
     ORDER BY r.numero DESC'
);
$stmt->execute($parametros);
$remitos = $stmt->fetchAll();

$scriptsPagina = [BASE_URL . '/assets/js/segmentado.js', BASE_URL . '/assets/js/filtros-auto.js'];
$tituloPagina  = 'Remitos';
require __DIR__ . '/includes/header_portal.php';
?>

<h1>Historial de remitos</h1>

<form method="get" id="formFiltros">
  <label>Tipo</label>
  <div class="seg" data-input="tipo">
    <button type="button" data-value="" class="<?= $tipo === '' ? 'on' : '' ?>">Todos</button>
    <button type="button" data-value="recepcion" class="<?= $tipo === 'recepcion' ? 'on' : '' ?>">Recepción</button>
    <button type="button" data-value="devolucion" class="<?= $tipo === 'devolucion' ? 'on' : '' ?>">Devolución</button>
  </div>
  <input type="hidden" name="tipo" id="tipo" class="filtro-auto" value="<?= htmlspecialchars($tipo) ?>">

  <div class="fila">
    <div>
      <label for="desde">Desde</label>
      <input class="campo-input filtro-auto" type="date" id="desde" name="desde" value="<?= htmlspecialchars($desde) ?>">
    </div>
    <div>
      <label for="hasta">Hasta</label>
      <input class="campo-input filtro-auto" type="date" id="hasta" name="hasta" value="<?= htmlspecialchars($hasta) ?>">
    </div>
  </div>
</form>

<?php foreach ($remitos as $remito): ?>
  <div class="item">
    <div class="l1">
      <span class="num">Nº <?= str_pad((string) $remito['numero'], 6, '0', STR_PAD_LEFT) ?></span>
      <span class="chip <?= $remito['tipo'] === 'recepcion' ? 'ok' : 'dep' ?>"><?= $remito['tipo'] === 'recepcion' ? 'recepción' : 'devolución' ?></span>
    </div>
    <div class="l2">
      <span><?= formatearFecha($remito['fecha']) ?></span>
      <span><?= (int) $remito['sanos'] + (int) $remito['rotos'] + (int) $remito['reacondicionados'] ?> tarimas · <?= (int) $remito['separadores'] ?> separadores</span>
    </div>
    <div class="acciones">
      <a href="pdf.php?id=<?= $remito['id'] ?>" target="_blank" rel="noopener">Descargar PDF</a>
    </div>
  </div>
<?php endforeach; ?>

<?php if (!$remitos): ?>
  <p class="nota">No hay remitos para esos filtros.</p>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer_portal.php'; ?>
