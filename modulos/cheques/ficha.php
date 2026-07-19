<?php

require_once __DIR__ . '/../../includes/auth.php';
requerirRol(['admin']);
require_once __DIR__ . '/../../includes/funciones.php';

$pdo = obtenerConexion();

$chequeId = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare(
    'SELECT ch.*, cl.razon_social, cu.nombre AS cuenta_nombre, cd.nombre AS cuenta_deposito_nombre, fin.nombre AS financiera_nombre
     FROM cheques ch
     LEFT JOIN clientes cl ON cl.id = ch.cliente_id
     LEFT JOIN cuentas cu ON cu.id = ch.cuenta_id
     LEFT JOIN cuentas cd ON cd.id = ch.cuenta_deposito_id
     LEFT JOIN financieras fin ON fin.id = ch.financiera_id
     WHERE ch.id = ?'
);
$stmt->execute([$chequeId]);
$cheque = $stmt->fetch() ?: null;

$movimientos = [];
if ($cheque) {
    $stmt = $pdo->prepare(
        'SELECT cm.*, u.nombre AS usuario_nombre
         FROM cheques_movimientos cm
         JOIN usuarios u ON u.id = cm.usuario_id
         WHERE cm.cheque_id = ?
         ORDER BY cm.fecha ASC, cm.id ASC'
    );
    $stmt->execute([$chequeId]);
    $movimientos = $stmt->fetchAll();
}

$chipPorEstado = [
    'en_cartera' => ['cartera', 'en cartera'],
    'depositado' => ['dep', 'depositado'],
    'acreditado' => ['ok', 'acreditado'],
    'rechazado'  => ['rech', 'rechazado'],
    'recuperado' => ['ok', 'recuperado'],
    'vendido'    => ['dep', 'vendido'],
    'endosado'   => ['dep', 'endosado'],
    'emitido'    => ['cartera', 'emitido'],
    'debitado'   => ['ok', 'debitado'],
];

$activo       = '';
$tituloPagina = 'Ficha del cheque';
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/tabs.php';
?>

<?php if (!$cheque): ?>
  <h1>Ficha del cheque</h1>
  <p class="nota">No encontré ese cheque.</p>
<?php else: ?>

  <h1>Cheque <?= htmlspecialchars($cheque['numero']) ?></h1>

  <?php [$chipClase, $chipTexto] = $chipPorEstado[$cheque['estado']] ?? ['cartera', $cheque['estado']]; ?>

  <div class="item">
    <div class="l1">
      <span class="num">
        <?= $cheque['tipo'] === 'recibido' ? htmlspecialchars($cheque['banco_librador']) : htmlspecialchars($cheque['cuenta_nombre'] ?? '') ?>
        · <?= htmlspecialchars($cheque['numero']) ?>
      </span>
      <span class="imp"><?= formatearImporte((float) $cheque['importe']) ?></span>
    </div>
    <div class="l2">
      <span><?= $cheque['tipo'] === 'recibido' ? htmlspecialchars($cheque['razon_social'] ?? 'Sin cliente') : htmlspecialchars($cheque['destinatario'] ?? '') ?></span>
      <span class="chip <?= $chipClase ?>"><?= htmlspecialchars($chipTexto) ?></span>
    </div>
  </div>

  <div class="item">
    <div class="l1"><span>Tipo</span><span><?= $cheque['tipo'] === 'recibido' ? 'Recibido' : 'Emitido' ?> · <?= $cheque['formato'] === 'echeq' ? 'ECHEQ' : 'Físico' ?></span></div>
  </div>
  <div class="item">
    <div class="l1"><span>Fecha de emisión</span><span><?= formatearFecha($cheque['fecha_emision']) ?></span></div>
  </div>
  <div class="item">
    <div class="l1"><span>Fecha de <?= $cheque['tipo'] === 'recibido' ? 'cobro' : 'débito' ?></span><span><?= formatearFecha($cheque['fecha_pago']) ?></span></div>
  </div>
  <?php if ($cheque['cuenta_deposito_nombre']): ?>
    <div class="item">
      <div class="l1"><span>Depositado en</span><span><?= htmlspecialchars($cheque['cuenta_deposito_nombre']) ?></span></div>
    </div>
  <?php endif; ?>
  <?php if ($cheque['financiera_nombre']): ?>
    <div class="item">
      <div class="l1"><span>Vendido a</span><span><?= htmlspecialchars($cheque['financiera_nombre']) ?></span></div>
      <div class="l2"><span>Neto recibido</span><span><?= formatearImporte((float) $cheque['monto_neto_venta']) ?></span></div>
    </div>
  <?php endif; ?>
  <?php if ($cheque['flete_id']): ?>
    <div class="item">
      <div class="l1"><span>Flete</span><span><a href="<?= BASE_URL ?>/modulos/fletes/gastos.php?flete_id=<?= $cheque['flete_id'] ?>">#<?= $cheque['flete_id'] ?></a></span></div>
    </div>
  <?php endif; ?>

  <h1 class="seccion">Historial</h1>

  <?php foreach ($movimientos as $mov): ?>
    <div class="item">
      <div class="l1">
        <span class="num"><?= $mov['estado_anterior'] ? htmlspecialchars($mov['estado_anterior']) . ' → ' : 'Alta · ' ?><?= htmlspecialchars($mov['estado_nuevo']) ?></span>
        <span><?= formatearFechaHora($mov['fecha']) ?></span>
      </div>
      <div class="l2">
        <span><?= htmlspecialchars($mov['usuario_nombre']) ?></span>
        <?php if ($mov['gastos_asociados']): ?><span>gastos <?= formatearImporte((float) $mov['gastos_asociados']) ?></span><?php endif; ?>
      </div>
      <?php if ($mov['observaciones']): ?>
        <div class="nota"><?= htmlspecialchars($mov['observaciones']) ?></div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

  <?php if (!$movimientos): ?>
    <p class="nota">Este cheque todavía no tiene cambios de estado registrados.</p>
  <?php endif; ?>

<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
