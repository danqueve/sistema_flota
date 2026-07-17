<?php

require_once __DIR__ . '/../../includes/auth.php';
requerirRol(['admin']);
require_once __DIR__ . '/../../includes/funciones.php';

$pdo = obtenerConexion();
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion   = $_POST['accion'] ?? '';
    $chequeId = (int) ($_POST['cheque_id'] ?? 0);

    $stmt = $pdo->prepare("SELECT * FROM cheques WHERE id = ? AND tipo = 'recibido'");
    $stmt->execute([$chequeId]);
    $cheque = $stmt->fetch() ?: null;

    if (!$cheque) {
        $errores[] = 'No encontré ese cheque.';
    } else {
        $usuarioId      = usuarioActual()['id'];
        $estadoAnterior = $cheque['estado'];

        try {
            switch ($accion) {
                case 'depositar':
                    $cuentaId = (int) ($_POST['cuenta_id'] ?? 0);
                    if (!$cuentaId) {
                        throw new RuntimeException('Elegí una cuenta para depositar.');
                    }
                    if (!transicionChequeRecibidoValida($estadoAnterior, 'depositado')) {
                        throw new RuntimeException('Ese cheque ya no está en cartera.');
                    }

                    $pdo->beginTransaction();
                    $pdo->prepare('UPDATE cheques SET estado="depositado", cuenta_deposito_id=? WHERE id=?')
                        ->execute([$cuentaId, $chequeId]);
                    registrarMovimientoCheque($pdo, $chequeId, $estadoAnterior, 'depositado', $usuarioId);
                    $pdo->commit();
                    break;

                case 'acreditar':
                    if (!transicionChequeRecibidoValida($estadoAnterior, 'acreditado')) {
                        throw new RuntimeException('Ese cheque no está depositado.');
                    }

                    $pdo->beginTransaction();
                    $pdo->prepare('UPDATE cheques SET estado="acreditado" WHERE id=?')->execute([$chequeId]);
                    registrarMovimientoCheque($pdo, $chequeId, $estadoAnterior, 'acreditado', $usuarioId);
                    $pdo->prepare(
                        'INSERT INTO movimientos_tesoreria (fecha, cuenta_id, tipo, importe, referencia_tipo, referencia_id, descripcion, usuario_id)
                         VALUES (CURDATE(), ?, "ingreso", ?, "cheque", ?, ?, ?)'
                    )->execute([$cheque['cuenta_deposito_id'], $cheque['importe'], $chequeId, 'Cheque acreditado ' . $cheque['numero'], $usuarioId]);
                    if ($cheque['flete_id']) {
                        $pdo->prepare('UPDATE fletes SET estado_cobro="cobrado" WHERE id=?')->execute([$cheque['flete_id']]);
                    }
                    $pdo->commit();
                    break;

                case 'vender':
                    $financieraId = (int) ($_POST['financiera_id'] ?? 0);
                    $cuentaId     = (int) ($_POST['cuenta_id'] ?? 0);
                    $montoNeto    = $_POST['monto_neto'] !== '' ? (float) str_replace(',', '.', $_POST['monto_neto']) : null;

                    if (!$financieraId) {
                        throw new RuntimeException('Elegí una financiera.');
                    }
                    if (!$cuentaId) {
                        throw new RuntimeException('Elegí en qué cuenta entra el neto.');
                    }
                    if ($montoNeto === null || $montoNeto <= 0) {
                        throw new RuntimeException('El monto neto recibido tiene que ser mayor a 0.');
                    }
                    if (!transicionChequeRecibidoValida($estadoAnterior, 'vendido')) {
                        throw new RuntimeException('Ese cheque ya no está en cartera.');
                    }

                    $pdo->beginTransaction();
                    $pdo->prepare('UPDATE cheques SET estado="vendido", financiera_id=?, monto_neto_venta=? WHERE id=?')
                        ->execute([$financieraId, $montoNeto, $chequeId]);
                    registrarMovimientoCheque($pdo, $chequeId, $estadoAnterior, 'vendido', $usuarioId);
                    $pdo->prepare(
                        'INSERT INTO movimientos_tesoreria (fecha, cuenta_id, tipo, importe, referencia_tipo, referencia_id, descripcion, usuario_id)
                         VALUES (CURDATE(), ?, "ingreso", ?, "cheque", ?, ?, ?)'
                    )->execute([$cuentaId, $montoNeto, $chequeId, 'Venta a financiera del cheque ' . $cheque['numero'], $usuarioId]);
                    if ($cheque['flete_id']) {
                        $pdo->prepare('UPDATE fletes SET estado_cobro="cobrado" WHERE id=?')->execute([$cheque['flete_id']]);
                    }
                    $pdo->commit();
                    break;

                case 'endosar':
                    $destinatario = trim($_POST['destinatario'] ?? '');
                    if ($destinatario === '') {
                        throw new RuntimeException('Decime a quién se lo entregaste.');
                    }
                    if (!transicionChequeRecibidoValida($estadoAnterior, 'endosado')) {
                        throw new RuntimeException('Ese cheque ya no está en cartera.');
                    }

                    $pdo->beginTransaction();
                    $pdo->prepare('UPDATE cheques SET estado="endosado", destinatario=? WHERE id=?')
                        ->execute([$destinatario, $chequeId]);
                    registrarMovimientoCheque($pdo, $chequeId, $estadoAnterior, 'endosado', $usuarioId);
                    if ($cheque['flete_id']) {
                        $pdo->prepare('UPDATE fletes SET estado_cobro="cobrado" WHERE id=?')->execute([$cheque['flete_id']]);
                    }
                    $pdo->commit();
                    break;

                case 'rechazar':
                    $gastos = $_POST['gastos_asociados'] !== '' ? (float) str_replace(',', '.', $_POST['gastos_asociados']) : 0.0;
                    if (!transicionChequeRecibidoValida($estadoAnterior, 'rechazado')) {
                        throw new RuntimeException('Ese cheque no está depositado.');
                    }

                    $pdo->beginTransaction();
                    $pdo->prepare('UPDATE cheques SET estado="rechazado" WHERE id=?')->execute([$chequeId]);
                    registrarMovimientoCheque($pdo, $chequeId, $estadoAnterior, 'rechazado', $usuarioId, $gastos > 0 ? $gastos : null);
                    if ($gastos > 0) {
                        $categoriaId = obtenerCategoriaGastoPorNombre($pdo, 'Gastos bancarios');
                        $pdo->prepare(
                            'INSERT INTO movimientos_tesoreria (fecha, cuenta_id, tipo, categoria_id, importe, referencia_tipo, referencia_id, descripcion, usuario_id)
                             VALUES (CURDATE(), ?, "egreso", ?, ?, "cheque", ?, ?, ?)'
                        )->execute([$cheque['cuenta_deposito_id'], $categoriaId, $gastos, $chequeId, 'Gastos por rechazo del cheque ' . $cheque['numero'], $usuarioId]);
                    }
                    $pdo->commit();
                    break;

                case 'recuperar':
                    if (!transicionChequeRecibidoValida($estadoAnterior, 'recuperado')) {
                        throw new RuntimeException('Ese cheque no está rechazado.');
                    }

                    $pdo->beginTransaction();
                    $pdo->prepare('UPDATE cheques SET estado="recuperado" WHERE id=?')->execute([$chequeId]);
                    registrarMovimientoCheque($pdo, $chequeId, $estadoAnterior, 'recuperado', $usuarioId);
                    $pdo->commit();
                    break;

                default:
                    throw new RuntimeException('Acción no reconocida.');
            }

            header('Location: cartera.php');
            exit;
        } catch (RuntimeException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errores[] = $e->getMessage();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errores[] = 'No se pudo completar la acción.';
        }
    }
}

$stmt = $pdo->query(
    "SELECT ch.*, cl.razon_social
     FROM cheques ch
     LEFT JOIN clientes cl ON cl.id = ch.cliente_id
     WHERE ch.tipo = 'recibido'
     ORDER BY CASE ch.estado WHEN 'en_cartera' THEN 0 WHEN 'depositado' THEN 0 WHEN 'rechazado' THEN 0 ELSE 1 END,
              ch.fecha_pago ASC"
);
$cheques = $stmt->fetchAll();

$hoy = new DateTimeImmutable('today');
foreach ($cheques as &$cheque) {
    $cheque['dias_al_cobro'] = (int) $hoy->diff(new DateTimeImmutable($cheque['fecha_pago']))->format('%r%a');
}
unset($cheque);

$cuentasBanco       = $pdo->query("SELECT id, nombre FROM cuentas WHERE tipo='banco' AND activo=1 ORDER BY nombre")->fetchAll();
$cuentasTodas       = $pdo->query('SELECT id, nombre FROM cuentas WHERE activo=1 ORDER BY tipo, nombre')->fetchAll();
$financierasActivas = $pdo->query('SELECT id, nombre FROM financieras WHERE activo=1 ORDER BY nombre')->fetchAll();
$totalPorEntrar     = (float) $pdo->query("SELECT COALESCE(SUM(importe),0) FROM cheques WHERE tipo='recibido' AND estado IN ('en_cartera','depositado')")->fetchColumn();

$chipPorEstado = [
    'en_cartera' => ['cartera', 'en cartera'],
    'depositado' => ['dep', 'depositado'],
    'acreditado' => ['ok', 'acreditado'],
    'rechazado'  => ['rech', 'rechazado'],
    'recuperado' => ['ok', 'recuperado'],
    'vendido'    => ['dep', 'vendido'],
    'endosado'   => ['dep', 'endosado'],
];

$activo        = 'cartera';
$scriptsPagina = [BASE_URL . '/assets/js/segmentado.js', BASE_URL . '/assets/js/cheques-cartera.js'];
$tituloPagina  = 'Cheques recibidos';
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/tabs.php';
?>

<h1>Cheques recibidos</h1>

<?php foreach ($errores as $error): ?>
  <p class="login-error"><?= htmlspecialchars($error) ?></p>
<?php endforeach; ?>

<?php foreach ($cheques as $cheque):
    $enCurso = in_array($cheque['estado'], ['en_cartera', 'depositado'], true);
    $urgente = $enCurso && $cheque['dias_al_cobro'] <= 7;
    [$chipClase, $chipTexto] = $chipPorEstado[$cheque['estado']] ?? ['cartera', $cheque['estado']];
?>
  <div class="item">
    <div class="l1">
      <span class="num"><a href="ficha.php?id=<?= $cheque['id'] ?>"><?= htmlspecialchars($cheque['banco_librador']) ?> · <?= htmlspecialchars($cheque['numero']) ?></a></span>
      <span class="imp"><?= formatearImporte((float) $cheque['importe']) ?></span>
    </div>
    <div class="l2">
      <span><?= htmlspecialchars($cheque['razon_social'] ?? 'Sin cliente') ?><?= $cheque['flete_id'] ? ' · flete #' . $cheque['flete_id'] : '' ?></span>
      <?php if ($enCurso): ?>
        <span class="<?= $urgente ? 'venc' : '' ?>">
          cobro <?= formatearFecha($cheque['fecha_pago']) ?>
          <?php if ($urgente): ?>
            · <?= $cheque['dias_al_cobro'] < 0 ? '¡vencido!' : ($cheque['dias_al_cobro'] === 0 ? '¡hoy!' : '¡en ' . $cheque['dias_al_cobro'] . ' días!') ?>
          <?php endif; ?>
        </span>
      <?php else: ?>
        <span class="chip <?= $chipClase ?>"><?= htmlspecialchars($chipTexto) ?></span>
      <?php endif; ?>
    </div>

    <?php if ($cheque['estado'] === 'en_cartera'): ?>
      <div class="acciones">
        <button type="button" class="p" data-accion="depositar" data-cheque-id="<?= $cheque['id'] ?>">Depositar</button>
        <button type="button" data-accion="vender" data-cheque-id="<?= $cheque['id'] ?>" data-importe="<?= $cheque['importe'] ?>">Vender</button>
        <button type="button" data-accion="endosar" data-cheque-id="<?= $cheque['id'] ?>">Endosar</button>
      </div>
    <?php elseif ($cheque['estado'] === 'depositado'): ?>
      <div class="acciones">
        <form method="post" onsubmit="return confirm('¿Acreditar este cheque?');">
          <input type="hidden" name="accion" value="acreditar">
          <input type="hidden" name="cheque_id" value="<?= $cheque['id'] ?>">
          <button type="submit" class="p">Acreditar</button>
        </form>
        <button type="button" data-accion="rechazar" data-cheque-id="<?= $cheque['id'] ?>">Rechazar</button>
      </div>
    <?php elseif ($cheque['estado'] === 'rechazado'): ?>
      <div class="acciones">
        <form method="post" onsubmit="return confirm('¿Marcar este cheque como recuperado?');">
          <input type="hidden" name="accion" value="recuperar">
          <input type="hidden" name="cheque_id" value="<?= $cheque['id'] ?>">
          <button type="submit">Recuperar</button>
        </form>
      </div>
    <?php endif; ?>
  </div>
<?php endforeach; ?>

<?php if (!$cheques): ?>
  <p class="nota">Todavía no hay cheques recibidos cargados.</p>
<?php endif; ?>

<div class="totalbar">
  <span>En cartera (por entrar)</span>
  <b><?= formatearImporte($totalPorEntrar) ?></b>
</div>

<a href="nuevo.php" class="btn seccion">+ Nuevo cheque</a>

<dialog id="dialogDepositar">
  <h3>Depositar cheque</h3>
  <form method="post">
    <input type="hidden" name="accion" value="depositar">
    <input type="hidden" name="cheque_id">
    <label>Cuenta</label>
    <div class="seg" data-input="cuenta_id_depositar">
      <?php foreach ($cuentasBanco as $cuenta): ?>
        <button type="button" data-value="<?= $cuenta['id'] ?>"><?= htmlspecialchars($cuenta['nombre']) ?></button>
      <?php endforeach; ?>
    </div>
    <input type="hidden" name="cuenta_id" id="cuenta_id_depositar">
    <button type="submit" class="btn">Confirmar depósito</button>
    <button type="button" class="btn-cerrar">Cancelar</button>
  </form>
</dialog>

<dialog id="dialogVender">
  <h3>Vender a financiera</h3>
  <form method="post">
    <input type="hidden" name="accion" value="vender">
    <input type="hidden" name="cheque_id">
    <label>Financiera</label>
    <div class="seg" data-input="financiera_id">
      <?php foreach ($financierasActivas as $financiera): ?>
        <button type="button" data-value="<?= $financiera['id'] ?>"><?= htmlspecialchars($financiera['nombre']) ?></button>
      <?php endforeach; ?>
    </div>
    <input type="hidden" name="financiera_id" id="financiera_id">

    <label for="monto_neto">Monto neto recibido</label>
    <input class="campo-input" type="number" id="monto_neto" name="monto_neto" min="0" step="0.01" inputmode="decimal">

    <div class="auto">
      <div><small>Costo del descuento</small><strong id="descuentoVenta">$ 0,00</strong></div>
    </div>

    <label>Cuenta destino</label>
    <div class="seg" data-input="cuenta_id_vender">
      <?php foreach ($cuentasTodas as $cuenta): ?>
        <button type="button" data-value="<?= $cuenta['id'] ?>"><?= htmlspecialchars($cuenta['nombre']) ?></button>
      <?php endforeach; ?>
    </div>
    <input type="hidden" name="cuenta_id" id="cuenta_id_vender">

    <button type="submit" class="btn">Confirmar venta</button>
    <button type="button" class="btn-cerrar">Cancelar</button>
  </form>
</dialog>

<dialog id="dialogEndosar">
  <h3>Endosar cheque</h3>
  <form method="post">
    <input type="hidden" name="accion" value="endosar">
    <input type="hidden" name="cheque_id">
    <label for="destinatario">Entregado a</label>
    <input class="campo-input" type="text" id="destinatario" name="destinatario" maxlength="120" required>
    <button type="submit" class="btn">Confirmar endoso</button>
    <button type="button" class="btn-cerrar">Cancelar</button>
  </form>
</dialog>

<dialog id="dialogRechazar">
  <h3>Rechazar cheque</h3>
  <form method="post">
    <input type="hidden" name="accion" value="rechazar">
    <input type="hidden" name="cheque_id">
    <label for="gastos_asociados">Gastos/comisiones asociados</label>
    <input class="campo-input" type="number" id="gastos_asociados" name="gastos_asociados" min="0" step="0.01" inputmode="decimal" value="0">
    <button type="submit" class="btn">Confirmar rechazo</button>
    <button type="button" class="btn-cerrar">Cancelar</button>
  </form>
</dialog>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
