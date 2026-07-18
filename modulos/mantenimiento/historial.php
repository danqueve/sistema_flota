<?php

require_once __DIR__ . '/../../includes/auth.php';
requerirRol(['admin']);
require_once __DIR__ . '/../../includes/funciones.php';

$pdo = obtenerConexion();

$camiones = $pdo->query('SELECT id, patente FROM camiones ORDER BY patente')->fetchAll();
$tipos    = $pdo->query('SELECT id, nombre FROM tipos_service ORDER BY nombre')->fetchAll();

$camionId = (int) ($_GET['camion_id'] ?? 0);
$tipoId   = (int) ($_GET['tipo_service_id'] ?? 0);
$periodo  = $_GET['periodo'] ?? '';
if ($periodo !== '' && !preg_match('/^\d{4}-\d{2}$/', $periodo)) {
    $periodo = '';
}

$condiciones = [];
$parametros  = [];

if ($camionId) {
    $condiciones[] = 's.camion_id = ?';
    $parametros[]  = $camionId;
}
if ($tipoId) {
    $condiciones[] = 's.tipo_service_id = ?';
    $parametros[]  = $tipoId;
}
if ($periodo) {
    $condiciones[] = 'DATE_FORMAT(s.fecha, "%Y-%m") = ?';
    $parametros[]  = $periodo;
}

$where = $condiciones ? ('WHERE ' . implode(' AND ', $condiciones)) : '';

$stmt = $pdo->prepare(
    "SELECT s.*, c.patente, ts.nombre AS tipo_nombre,
            (SELECT COALESCE(SUM(ms.cantidad * r.costo_unitario), 0)
             FROM movimientos_stock ms JOIN repuestos r ON r.id = ms.repuesto_id
             WHERE ms.service_id = s.id) AS costo_repuestos
     FROM services s
     JOIN camiones c ON c.id = s.camion_id
     JOIN tipos_service ts ON ts.id = s.tipo_service_id
     $where
     ORDER BY s.fecha DESC, s.id DESC"
);
$stmt->execute($parametros);
$services = $stmt->fetchAll();

$cantidad    = count($services);
$totalCosto  = (float) array_sum(array_column($services, 'costo'));

$activo         = 'historial';
$scriptsPagina  = [BASE_URL . '/assets/js/segmentado.js', BASE_URL . '/assets/js/filtros-auto.js'];
$tituloPagina   = 'Historial de mantenimiento';
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/tabs.php';
?>

<h1>Historial de mantenimiento</h1>

<form method="get" id="formFiltros">
  <label>Camión</label>
  <div class="seg" data-input="camion_id">
    <button type="button" data-value="" class="<?= $camionId === 0 ? 'on' : '' ?>">Todos</button>
    <?php foreach ($camiones as $camion): ?>
      <button type="button" data-value="<?= $camion['id'] ?>" class="<?= $camionId === (int) $camion['id'] ? 'on' : '' ?>"><?= htmlspecialchars($camion['patente']) ?></button>
    <?php endforeach; ?>
  </div>
  <input type="hidden" name="camion_id" id="camion_id" class="filtro-auto" value="<?= $camionId ?: '' ?>">

  <label>Tipo de service</label>
  <div class="seg" data-input="tipo_service_id">
    <button type="button" data-value="" class="<?= $tipoId === 0 ? 'on' : '' ?>">Todos</button>
    <?php foreach ($tipos as $tipo): ?>
      <button type="button" data-value="<?= $tipo['id'] ?>" class="<?= $tipoId === (int) $tipo['id'] ? 'on' : '' ?>"><?= htmlspecialchars($tipo['nombre']) ?></button>
    <?php endforeach; ?>
  </div>
  <input type="hidden" name="tipo_service_id" id="tipo_service_id" class="filtro-auto" value="<?= $tipoId ?: '' ?>">

  <label for="periodo">Período (opcional)</label>
  <input class="campo-input filtro-auto" type="month" id="periodo" name="periodo" value="<?= htmlspecialchars($periodo) ?>">

  <noscript><button type="submit" class="btn sec">Filtrar</button></noscript>
</form>

<div class="consumo seccion">
  <div><small>Services</small><b><?= $cantidad ?></b></div>
  <div><small>Total gastado</small><b><?= formatearImporte($totalCosto) ?></b></div>
</div>

<h1 class="seccion">Línea de tiempo</h1>

<?php foreach ($services as $service): ?>
  <div class="item">
    <div class="l1">
      <span class="num"><?= htmlspecialchars($service['patente']) ?> · <?= htmlspecialchars($service['tipo_nombre']) ?></span>
      <span class="imp"><?= $service['costo'] !== null ? formatearImporte((float) $service['costo']) : '—' ?></span>
    </div>
    <div class="l2">
      <span><?= formatearFecha($service['fecha']) ?><?= $service['km'] ? ' · km ' . number_format((float) $service['km'], 0, ',', '.') : '' ?></span>
      <span><?= $service['taller'] ? htmlspecialchars($service['taller']) : 'Sin taller informado' ?></span>
    </div>
    <?php if ($service['observaciones'] || (float) $service['costo_repuestos'] > 0): ?>
      <div class="l2">
        <span><?= htmlspecialchars($service['observaciones'] ?: '') ?></span>
        <?php if ((float) $service['costo_repuestos'] > 0): ?>
          <span>repuestos <?= formatearImporte((float) $service['costo_repuestos']) ?></span>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <div class="acciones">
      <a href="repuestos.php?service_id=<?= $service['id'] ?>">Ver repuestos</a>
    </div>
  </div>
<?php endforeach; ?>

<?php if (!$services): ?>
  <p class="nota">No hay services cargados para esos filtros.</p>
<?php endif; ?>

<a href="nuevo.php" class="btn seccion">+ Nuevo service</a>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
