<?php

require_once __DIR__ . '/../../includes/auth.php';
requerirRol(['admin']);
require_once __DIR__ . '/../../includes/funciones.php';

$pdo = obtenerConexion();

$camiones = $pdo->query('SELECT id, patente FROM camiones ORDER BY patente')->fetchAll();
$choferes = $pdo->query('SELECT id, nombre FROM choferes ORDER BY nombre')->fetchAll();

$periodo = $_GET['periodo'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $periodo)) {
    $periodo = date('Y-m');
}
$camionId = (int) ($_GET['camion_id'] ?? 0);
$choferId = (int) ($_GET['chofer_id'] ?? 0);

$condiciones = ['DATE_FORMAT(f.fecha, "%Y-%m") = ?'];
$parametros  = [$periodo];

if ($camionId) {
    $condiciones[] = 'f.camion_id = ?';
    $parametros[]  = $camionId;
}
if ($choferId) {
    $condiciones[] = 'f.chofer_id = ?';
    $parametros[]  = $choferId;
}

$stmt = $pdo->prepare(
    'SELECT f.*, c.patente, ch.nombre AS chofer_nombre, cl.razon_social
     FROM fletes f
     JOIN camiones c ON c.id = f.camion_id
     JOIN choferes ch ON ch.id = f.chofer_id
     LEFT JOIN clientes cl ON cl.id = f.cliente_id
     WHERE ' . implode(' AND ', $condiciones) . '
     ORDER BY f.fecha DESC, f.id DESC'
);
$stmt->execute($parametros);
$fletes = $stmt->fetchAll();

$cantidad      = count($fletes);
$totalBruto    = (float) array_sum(array_column($fletes, 'importe_bruto'));
$totalComision = (float) array_sum(array_column($fletes, 'comision_chofer'));

$scriptsPagina = [BASE_URL . '/assets/js/segmentado.js', BASE_URL . '/assets/js/filtros-auto.js'];
$tituloPagina  = 'Fletes del mes';
require __DIR__ . '/../../includes/header.php';
?>

<h1>Fletes del mes</h1>

<form method="get" id="formFiltros">
  <label for="periodo">Período</label>
  <input class="campo-input filtro-auto" type="month" id="periodo" name="periodo" value="<?= htmlspecialchars($periodo) ?>">

  <label>Camión</label>
  <div class="seg" data-input="camion_id">
    <button type="button" data-value="" class="<?= $camionId === 0 ? 'on' : '' ?>">Todos</button>
    <?php foreach ($camiones as $camion): ?>
      <button type="button" data-value="<?= $camion['id'] ?>" class="<?= $camionId === (int) $camion['id'] ? 'on' : '' ?>"><?= htmlspecialchars($camion['patente']) ?></button>
    <?php endforeach; ?>
  </div>
  <input type="hidden" name="camion_id" id="camion_id" class="filtro-auto" value="<?= $camionId ?: '' ?>">

  <label>Chofer</label>
  <div class="seg" data-input="chofer_id">
    <button type="button" data-value="" class="<?= $choferId === 0 ? 'on' : '' ?>">Todos</button>
    <?php foreach ($choferes as $chofer): ?>
      <button type="button" data-value="<?= $chofer['id'] ?>" class="<?= $choferId === (int) $chofer['id'] ? 'on' : '' ?>"><?= htmlspecialchars($chofer['nombre']) ?></button>
    <?php endforeach; ?>
  </div>
  <input type="hidden" name="chofer_id" id="chofer_id" class="filtro-auto" value="<?= $choferId ?: '' ?>">

  <noscript><button type="submit" class="btn sec">Filtrar</button></noscript>
</form>

<div class="consumo seccion">
  <div><small>Fletes</small><b><?= $cantidad ?></b></div>
  <div><small>Facturado</small><b><?= formatearImporte($totalBruto) ?></b></div>
  <div><small>Comisiones</small><b><?= formatearImporte($totalComision) ?></b></div>
</div>

<h1 class="seccion">Detalle</h1>

<?php foreach ($fletes as $flete): ?>
  <div class="item">
    <div class="l1">
      <span class="num"><?= htmlspecialchars($flete['patente']) ?> · <?= htmlspecialchars($flete['chofer_nombre']) ?></span>
      <span class="imp"><?= formatearImporte((float) $flete['importe_bruto']) ?></span>
    </div>
    <div class="l2">
      <span><?= formatearFecha($flete['fecha']) ?> · <?= htmlspecialchars($flete['destino']) ?></span>
      <span>comisión <?= formatearImporte((float) $flete['comision_chofer']) ?></span>
    </div>
    <div class="l2">
      <span><?= htmlspecialchars($flete['razon_social'] ?? 'Sin cliente') ?></span>
      <span class="chip <?= $flete['estado_cobro'] === 'cobrado' ? 'ok' : 'cartera' ?>"><?= $flete['estado_cobro'] === 'cobrado' ? 'cobrado' : 'pendiente' ?></span>
    </div>
    <div class="acciones">
      <a href="gastos.php?flete_id=<?= $flete['id'] ?>">Gastos del viaje</a>
    </div>
  </div>
<?php endforeach; ?>

<?php if (!$fletes): ?>
  <p class="nota">No hay fletes cargados para ese período con esos filtros.</p>
<?php endif; ?>

<a href="nuevo.php" class="btn seccion">+ Nuevo flete</a>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
