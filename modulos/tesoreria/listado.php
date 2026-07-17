<?php

require_once __DIR__ . '/../../includes/auth.php';
requerirRol(['admin']);
require_once __DIR__ . '/../../includes/funciones.php';

function etiquetaReferencia(?string $tipo, ?int $id): string
{
    if (!$tipo || $tipo === 'otro' || !$id) {
        return '';
    }

    $nombres = [
        'cheque'           => 'cheque',
        'flete'            => 'flete',
        'liquidacion'      => 'liquidación',
        'resumen_estacion' => 'resumen de estación',
        'stock'            => 'stock',
    ];

    return ($nombres[$tipo] ?? $tipo) . ' #' . $id;
}

$pdo = obtenerConexion();

$posicion = $pdo->query('SELECT * FROM v_posicion')->fetch();

$cuentas    = $pdo->query('SELECT id, nombre FROM cuentas ORDER BY tipo, nombre')->fetchAll();
$categorias = $pdo->query('SELECT id, nombre FROM categorias_gasto ORDER BY nombre')->fetchAll();

$cuentaId    = (int) ($_GET['cuenta_id'] ?? 0);
$tipo        = in_array($_GET['tipo'] ?? '', ['ingreso', 'egreso'], true) ? $_GET['tipo'] : '';
$categoriaId = (int) ($_GET['categoria_id'] ?? 0);
$periodo     = $_GET['periodo'] ?? '';
if ($periodo !== '' && !preg_match('/^\d{4}-\d{2}$/', $periodo)) {
    $periodo = '';
}

$condiciones = [];
$parametros  = [];

if ($cuentaId) {
    $condiciones[] = 'mt.cuenta_id = ?';
    $parametros[]  = $cuentaId;
}
if ($tipo) {
    $condiciones[] = 'mt.tipo = ?';
    $parametros[]  = $tipo;
}
if ($categoriaId) {
    $condiciones[] = 'mt.categoria_id = ?';
    $parametros[]  = $categoriaId;
}
if ($periodo) {
    $condiciones[] = 'DATE_FORMAT(mt.fecha, "%Y-%m") = ?';
    $parametros[]  = $periodo;
}

$where = $condiciones ? ('WHERE ' . implode(' AND ', $condiciones)) : '';

$stmt = $pdo->prepare(
    "SELECT mt.*, c.nombre AS cuenta_nombre, cg.nombre AS categoria_nombre
     FROM movimientos_tesoreria mt
     JOIN cuentas c ON c.id = mt.cuenta_id
     LEFT JOIN categorias_gasto cg ON cg.id = mt.categoria_id
     $where
     ORDER BY mt.fecha DESC, mt.id DESC"
);
$stmt->execute($parametros);
$movimientos = $stmt->fetchAll();

$totalIngresos = 0.0;
$totalEgresos  = 0.0;
foreach ($movimientos as $mov) {
    if ($mov['tipo'] === 'ingreso') {
        $totalIngresos += (float) $mov['importe'];
    } else {
        $totalEgresos += (float) $mov['importe'];
    }
}

$activo        = 'listado';
$scriptsPagina = [BASE_URL . '/assets/js/segmentado.js', BASE_URL . '/assets/js/filtros-auto.js'];
$tituloPagina  = 'Tesorería';
require __DIR__ . '/../../includes/header.php';
?>

<h1>Tesorería</h1>

<div class="consumo">
  <div><small>Saldo actual</small><b><?= formatearImporte((float) $posicion['saldo_actual']) ?></b></div>
  <div><small>Por entrar</small><b><?= formatearImporte((float) $posicion['por_entrar']) ?></b></div>
  <div><small>Por salir</small><b><?= formatearImporte((float) $posicion['por_salir']) ?></b></div>
</div>

<form method="get" id="formFiltros" class="seccion">
  <label>Cuenta</label>
  <div class="seg" data-input="cuenta_id">
    <button type="button" data-value="" class="<?= $cuentaId === 0 ? 'on' : '' ?>">Todas</button>
    <?php foreach ($cuentas as $cuenta): ?>
      <button type="button" data-value="<?= $cuenta['id'] ?>" class="<?= $cuentaId === (int) $cuenta['id'] ? 'on' : '' ?>"><?= htmlspecialchars($cuenta['nombre']) ?></button>
    <?php endforeach; ?>
  </div>
  <input type="hidden" name="cuenta_id" id="cuenta_id" class="filtro-auto" value="<?= $cuentaId ?: '' ?>">

  <label>Tipo</label>
  <div class="seg" data-input="tipo">
    <button type="button" data-value="" class="<?= $tipo === '' ? 'on' : '' ?>">Todos</button>
    <button type="button" data-value="ingreso" class="<?= $tipo === 'ingreso' ? 'on' : '' ?>">Ingreso</button>
    <button type="button" data-value="egreso" class="<?= $tipo === 'egreso' ? 'on' : '' ?>">Egreso</button>
  </div>
  <input type="hidden" name="tipo" id="tipo" class="filtro-auto" value="<?= htmlspecialchars($tipo) ?>">

  <label for="categoria_id">Categoría</label>
  <select class="campo-input filtro-auto" id="categoria_id" name="categoria_id">
    <option value="">Todas</option>
    <?php foreach ($categorias as $categoria): ?>
      <option value="<?= $categoria['id'] ?>" <?= $categoriaId === (int) $categoria['id'] ? 'selected' : '' ?>><?= htmlspecialchars($categoria['nombre']) ?></option>
    <?php endforeach; ?>
  </select>

  <label for="periodo">Período</label>
  <input class="campo-input filtro-auto" type="month" id="periodo" name="periodo" value="<?= htmlspecialchars($periodo) ?>">
</form>

<div class="consumo seccion">
  <div><small>Ingresos (filtro)</small><b><?= formatearImporte($totalIngresos) ?></b></div>
  <div><small>Egresos (filtro)</small><b><?= formatearImporte($totalEgresos) ?></b></div>
</div>

<h1 class="seccion">Movimientos</h1>

<?php foreach ($movimientos as $mov): ?>
  <div class="item">
    <div class="l1">
      <span class="num"><?= htmlspecialchars($mov['categoria_nombre'] ?? 'Sin categoría') ?></span>
      <span class="imp"><?= $mov['tipo'] === 'egreso' ? '-' : '' ?><?= formatearImporte((float) $mov['importe']) ?></span>
    </div>
    <div class="l2">
      <span><?= formatearFecha($mov['fecha']) ?> · <?= htmlspecialchars($mov['cuenta_nombre']) ?></span>
      <span class="chip <?= $mov['tipo'] === 'ingreso' ? 'ok' : 'rech' ?>"><?= htmlspecialchars($mov['tipo']) ?></span>
    </div>
    <?php if ($mov['descripcion'] || etiquetaReferencia($mov['referencia_tipo'], $mov['referencia_id'])): ?>
      <div class="l2">
        <span><?= htmlspecialchars($mov['descripcion'] ?? '') ?></span>
        <span><?= htmlspecialchars(etiquetaReferencia($mov['referencia_tipo'], $mov['referencia_id'])) ?></span>
      </div>
    <?php endif; ?>
  </div>
<?php endforeach; ?>

<?php if (!$movimientos): ?>
  <p class="nota">No hay movimientos para esos filtros.</p>
<?php endif; ?>

<a href="nuevo.php" class="btn seccion">+ Nuevo movimiento</a>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
