<?php

require_once __DIR__ . '/../../includes/auth.php';
requerirRol(['admin']);
require_once __DIR__ . '/../../includes/funciones.php';

$pdo = obtenerConexion();

$remitoId = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare(
    'SELECT r.*, cl.razon_social, cl.cuit AS cliente_cuit, u.nombre AS usuario_nombre,
            pm.sanos, pm.rotos, pm.reacondicionados, pm.separadores, pm.observaciones
     FROM remitos r
     JOIN clientes cl ON cl.id = r.cliente_id
     JOIN usuarios u ON u.id = r.usuario_id
     JOIN pallets_movimientos pm ON pm.remito_id = r.id
     WHERE r.id = ?'
);
$stmt->execute([$remitoId]);
$remito = $stmt->fetch() ?: null;

$activo       = 'listado';
$tituloPagina = 'Detalle de remito';
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/tabs.php';
?>

<?php if (!$remito): ?>
  <h1>Detalle de remito</h1>
  <p class="nota">No encontré ese remito.</p>
<?php else: ?>

  <h1>Remito Nº <?= str_pad((string) $remito['numero'], 6, '0', STR_PAD_LEFT) ?></h1>

  <div class="item">
    <div class="l1">
      <span><?= $remito['tipo'] === 'recepcion' ? 'Recepción' : 'Devolución' ?></span>
      <span class="chip <?= $remito['tipo'] === 'recepcion' ? 'ok' : 'dep' ?>"><?= formatearFecha($remito['fecha']) ?></span>
    </div>
  </div>

  <div class="item">
    <div class="l1"><span>Cliente</span><span><?= htmlspecialchars($remito['razon_social']) ?></span></div>
    <div class="l2"><span>CUIT</span><span><?= htmlspecialchars($remito['cliente_cuit'] ?: '—') ?></span></div>
  </div>

  <div class="item">
    <div class="l1"><span>Tarimas sanas</span><span><?= (int) $remito['sanos'] ?></span></div>
    <div class="l1"><span>Tarimas rotas</span><span><?= (int) $remito['rotos'] ?></span></div>
    <div class="l1"><span>Reacondicionadas</span><span><?= (int) $remito['reacondicionados'] ?></span></div>
    <div class="l1"><span>Separadores</span><span><?= (int) $remito['separadores'] ?></span></div>
  </div>

  <div class="item">
    <div class="l1"><span>Transporte que entrega</span><span><?= htmlspecialchars($remito['transporte_origen'] ?: '—') ?></span></div>
    <div class="l2"><span>CUIT transporte</span><span><?= htmlspecialchars($remito['transporte_cuit'] ?: '—') ?></span></div>
  </div>

  <div class="item">
    <div class="l1"><span>Chofer</span><span><?= htmlspecialchars($remito['chofer_nombre'] ?: '—') ?></span></div>
    <div class="l2"><span>DNI</span><span><?= htmlspecialchars($remito['chofer_dni'] ?: '—') ?></span></div>
  </div>

  <div class="item">
    <div class="l1"><span>Hoja de ruta</span><span><?= htmlspecialchars($remito['hoja_ruta'] ?: '—') ?></span></div>
  </div>

  <div class="item">
    <div class="l1"><span>Documentación</span><span><?= htmlspecialchars($remito['documentacion'] ?: '—') ?></span></div>
  </div>

  <div class="item">
    <div class="l1"><span>Peajes</span><span><?= htmlspecialchars($remito['peajes'] ?: '—') ?></span></div>
  </div>

  <?php if ($remito['observaciones']): ?>
    <div class="item">
      <div class="l1"><span>Observaciones</span></div>
      <div class="nota"><?= htmlspecialchars($remito['observaciones']) ?></div>
    </div>
  <?php endif; ?>

  <div class="item">
    <div class="l2"><span>Cargado por</span><span><?= htmlspecialchars($remito['usuario_nombre']) ?></span></div>
  </div>

  <a href="pdf.php?id=<?= $remito['id'] ?>" class="btn" target="_blank" rel="noopener">Ver PDF</a>
  <a href="listado.php" class="btn sec">‹ Volver al listado</a>

<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
