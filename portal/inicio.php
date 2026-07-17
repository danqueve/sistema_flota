<?php

require_once __DIR__ . '/includes/auth_portal.php';
requerirLoginPortal();
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/datos_empresa.php';

$usuarioPortal = usuarioPortalActual();
$pdo = obtenerConexion();

// Siempre filtrado por el cliente_id de la sesión del portal — nunca por un
// parámetro de la petición. Es la única fuente de datos que puede leer.
$stmt = $pdo->prepare('SELECT * FROM v_pallets_stock WHERE cliente_id = ?');
$stmt->execute([$usuarioPortal['cliente_id']]);
$stock = $stmt->fetch() ?: ['sanos' => 0, 'rotos' => 0, 'reacondicionados' => 0, 'separadores' => 0];

$total = (int) $stock['sanos'] + (int) $stock['rotos'] + (int) $stock['reacondicionados'];

$tituloPagina = 'Inicio';
require __DIR__ . '/includes/header_portal.php';
?>

<h1>Tarimas (pallets) en Tucumán</h1>
<p class="nota">Actualizado al <?= date('d/m/Y H:i') ?> hs.</p>

<div class="tarjetas-grandes">
  <div class="tarjeta-grande ok">
    <small>Sanas</small>
    <strong><?= (int) $stock['sanos'] ?></strong>
  </div>
  <div class="tarjeta-grande alerta">
    <small>Rotas</small>
    <strong><?= (int) $stock['rotos'] ?></strong>
  </div>
  <div class="tarjeta-grande info">
    <small>Reacondicionadas</small>
    <strong><?= (int) $stock['reacondicionados'] ?></strong>
  </div>
  <div class="tarjeta-grande">
    <small>Separadores</small>
    <strong><?= (int) $stock['separadores'] ?></strong>
  </div>
</div>

<div class="tarjeta-total">
  <small>Total tarimas</small>
  <strong><?= $total ?></strong>
</div>

<a href="remitos.php" class="btn sec seccion">Ver historial de remitos</a>

<?php require __DIR__ . '/includes/footer_portal.php'; ?>
