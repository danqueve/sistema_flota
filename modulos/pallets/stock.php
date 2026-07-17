<?php

require_once __DIR__ . '/../../includes/auth.php';
requerirRol(['admin']);
require_once __DIR__ . '/../../includes/funciones.php';

$pdo = obtenerConexion();

$clientes = $pdo->query(
    'SELECT id, razon_social FROM clientes WHERE es_portal_pallets = 1 AND activo = 1 ORDER BY razon_social'
)->fetchAll();

$stockPorCliente = [];
$stmt = $pdo->query('SELECT * FROM v_pallets_stock');
foreach ($stmt->fetchAll() as $fila) {
    $stockPorCliente[(int) $fila['cliente_id']] = $fila;
}

$ultimoRemitoPorCliente = [];
$stmt = $pdo->query(
    'SELECT r1.cliente_id, r1.id, r1.numero, r1.tipo, r1.fecha
     FROM remitos r1
     WHERE r1.id = (SELECT r2.id FROM remitos r2 WHERE r2.cliente_id = r1.cliente_id ORDER BY r2.numero DESC LIMIT 1)'
);
foreach ($stmt->fetchAll() as $fila) {
    $ultimoRemitoPorCliente[(int) $fila['cliente_id']] = $fila;
}

$activo       = 'stock';
$tituloPagina = 'Stock de tarimas';
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/tabs.php';
?>

<h1>Stock de tarimas (pallets)</h1>
<p class="nota">Es la misma información que ve la empresa de Entre Ríos en su portal.</p>

<?php foreach ($clientes as $cliente):
    $stock = $stockPorCliente[(int) $cliente['id']] ?? ['sanos' => 0, 'rotos' => 0, 'reacondicionados' => 0, 'separadores' => 0];
    $ultimo = $ultimoRemitoPorCliente[(int) $cliente['id']] ?? null;
?>
  <h1 class="seccion"><?= htmlspecialchars($cliente['razon_social']) ?></h1>

  <div class="fila">
    <div class="item"><div class="l1"><span>Sanas</span></div><div class="l1"><strong><?= (int) $stock['sanos'] ?></strong></div></div>
    <div class="item"><div class="l1"><span>Rotas</span></div><div class="l1"><strong><?= (int) $stock['rotos'] ?></strong></div></div>
  </div>
  <div class="fila">
    <div class="item"><div class="l1"><span>Reacondicionadas</span></div><div class="l1"><strong><?= (int) $stock['reacondicionados'] ?></strong></div></div>
    <div class="item"><div class="l1"><span>Separadores</span></div><div class="l1"><strong><?= (int) $stock['separadores'] ?></strong></div></div>
  </div>

  <div class="totalbar">
    <span>Total tarimas (sin separadores)</span>
    <b><?= (int) $stock['sanos'] + (int) $stock['rotos'] + (int) $stock['reacondicionados'] ?></b>
  </div>

  <?php if ($ultimo): ?>
    <p class="nota">
      Último remito: <a href="detalle.php?id=<?= $ultimo['id'] ?>">Nº <?= str_pad((string) $ultimo['numero'], 6, '0', STR_PAD_LEFT) ?></a>
      (<?= $ultimo['tipo'] === 'recepcion' ? 'recepción' : 'devolución' ?>, <?= formatearFecha($ultimo['fecha']) ?>)
    </p>
  <?php endif; ?>

  <a href="listado.php?cliente_id=<?= $cliente['id'] ?>" class="btn sec">Ver remitos de este cliente</a>
<?php endforeach; ?>

<?php if (!$clientes): ?>
  <p class="nota">No hay clientes con acceso al portal de tarimas.</p>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
