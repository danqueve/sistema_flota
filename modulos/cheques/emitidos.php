<?php

require_once __DIR__ . '/../../includes/auth.php';
requerirRol(['admin']);
require_once __DIR__ . '/../../includes/funciones.php';

$pdo = obtenerConexion();
$errores = [];
$guardado = null;

$cuentasBanco = $pdo->query("SELECT id, nombre FROM cuentas WHERE tipo='banco' AND activo=1 ORDER BY nombre")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar') {
    $formato      = ($_POST['formato'] ?? '') === 'echeq' ? 'echeq' : 'fisico';
    $numero       = trim($_POST['numero'] ?? '');
    $cuentaId     = (int) ($_POST['cuenta_id'] ?? 0);
    $destinatario = trim($_POST['destinatario'] ?? '');
    $importe      = $_POST['importe'] !== '' ? (float) str_replace(',', '.', $_POST['importe']) : null;
    $fechaPago    = $_POST['fecha_pago'] ?? '';

    if ($numero === '') {
        $errores[] = 'El número de cheque es obligatorio.';
    }
    if (!$cuentaId) {
        $errores[] = 'Elegí la cuenta emisora.';
    }
    if ($destinatario === '') {
        $errores[] = 'Decime a quién se lo entregaste.';
    }
    if ($importe === null || $importe <= 0) {
        $errores[] = 'El importe tiene que ser mayor a 0.';
    }
    if ($fechaPago === '') {
        $errores[] = 'La fecha de débito es obligatoria.';
    }

    if (!$errores) {
        $stmt = $pdo->prepare(
            'INSERT INTO cheques (tipo, formato, numero, cuenta_id, destinatario, importe, fecha_emision, fecha_pago, estado)
             VALUES ("emitido", ?, ?, ?, ?, ?, CURDATE(), ?, "emitido")'
        );
        $stmt->execute([$formato, $numero, $cuentaId, $destinatario, $importe, $fechaPago]);
        $chequeId = (int) $pdo->lastInsertId();

        header('Location: emitidos.php?guardado=' . $chequeId);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['accion'] ?? '', ['debitar', 'rechazar'], true)) {
    $accion   = $_POST['accion'];
    $chequeId = (int) ($_POST['cheque_id'] ?? 0);

    $stmt = $pdo->prepare("SELECT * FROM cheques WHERE id = ? AND tipo = 'emitido'");
    $stmt->execute([$chequeId]);
    $cheque = $stmt->fetch() ?: null;

    if (!$cheque) {
        $errores[] = 'No encontré ese cheque.';
    } else {
        $usuarioId      = usuarioActual()['id'];
        $estadoAnterior = $cheque['estado'];
        $estadoNuevo    = $accion === 'debitar' ? 'debitado' : 'rechazado';

        if (!transicionChequeEmitidoValida($estadoAnterior, $estadoNuevo)) {
            $errores[] = 'Ese cheque ya no está pendiente de débito.';
        } else {
            try {
                $pdo->beginTransaction();
                $pdo->prepare('UPDATE cheques SET estado=? WHERE id=?')->execute([$estadoNuevo, $chequeId]);

                if ($accion === 'debitar') {
                    registrarMovimientoCheque($pdo, $chequeId, $estadoAnterior, 'debitado', $usuarioId);
                    $pdo->prepare(
                        'INSERT INTO movimientos_tesoreria (fecha, cuenta_id, tipo, importe, referencia_tipo, referencia_id, descripcion, usuario_id)
                         VALUES (CURDATE(), ?, "egreso", ?, "cheque", ?, ?, ?)'
                    )->execute([$cheque['cuenta_id'], $cheque['importe'], $chequeId, 'Cheque propio debitado ' . $cheque['numero'], $usuarioId]);
                } else {
                    $gastos = $_POST['gastos_asociados'] !== '' ? (float) str_replace(',', '.', $_POST['gastos_asociados']) : 0.0;
                    registrarMovimientoCheque($pdo, $chequeId, $estadoAnterior, 'rechazado', $usuarioId, $gastos > 0 ? $gastos : null);
                    if ($gastos > 0) {
                        $categoriaId = obtenerCategoriaGastoPorNombre($pdo, 'Gastos bancarios');
                        $pdo->prepare(
                            'INSERT INTO movimientos_tesoreria (fecha, cuenta_id, tipo, categoria_id, importe, referencia_tipo, referencia_id, descripcion, usuario_id)
                             VALUES (CURDATE(), ?, "egreso", ?, ?, "cheque", ?, ?, ?)'
                        )->execute([$cheque['cuenta_id'], $categoriaId, $gastos, $chequeId, 'Gastos por rechazo del cheque propio ' . $cheque['numero'], $usuarioId]);
                    }
                }

                $pdo->commit();
                header('Location: emitidos.php');
                exit;
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errores[] = 'No se pudo completar la acción.';
            }
        }
    }
}

if (isset($_GET['guardado'])) {
    $stmt = $pdo->prepare('SELECT * FROM cheques WHERE id = ?');
    $stmt->execute([(int) $_GET['guardado']]);
    $guardado = $stmt->fetch() ?: null;
}

// Compromisos futuros: emitidos pendientes de débito, agrupados por semana.
$pendientes = $pdo->query("SELECT * FROM cheques WHERE tipo='emitido' AND estado='emitido' ORDER BY fecha_pago ASC")->fetchAll();

$porSemana = [];
foreach ($pendientes as $cheque) {
    $fecha           = new DateTimeImmutable($cheque['fecha_pago']);
    $diasDesdeLunes  = ((int) $fecha->format('N')) - 1;
    $lunes           = $fecha->modify('-' . $diasDesdeLunes . ' days');
    $clave           = $lunes->format('Y-m-d');

    if (!isset($porSemana[$clave])) {
        $porSemana[$clave] = ['inicio' => $lunes, 'cheques' => [], 'total' => 0.0];
    }
    $porSemana[$clave]['cheques'][] = $cheque;
    $porSemana[$clave]['total']    += (float) $cheque['importe'];
}
ksort($porSemana);

$activo        = 'emitidos';
$scriptsPagina = [BASE_URL . '/assets/js/segmentado.js', BASE_URL . '/assets/js/cheques-emitidos.js'];
$tituloPagina  = 'Cheques emitidos';
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/tabs.php';
?>

<h1>Nuevo cheque emitido</h1>

<?php if ($guardado): ?>
  <p class="exito">Cheque guardado: <?= htmlspecialchars($guardado['numero']) ?> · <?= htmlspecialchars($guardado['destinatario']) ?> · <?= formatearImporte((float) $guardado['importe']) ?></p>
<?php endif; ?>

<?php foreach ($errores as $error): ?>
  <p class="login-error"><?= htmlspecialchars($error) ?></p>
<?php endforeach; ?>

<?php if (!$cuentasBanco): ?>
  <p class="nota">Hace falta al menos una cuenta banco activa. Cargala en <a href="cuentas.php">Cuentas</a>.</p>
<?php else: ?>

<form method="post">
  <input type="hidden" name="accion" value="guardar">

  <label>Formato</label>
  <div class="seg" data-input="formato">
    <button type="button" data-value="fisico" class="on">Físico</button>
    <button type="button" data-value="echeq">ECHEQ</button>
  </div>
  <input type="hidden" name="formato" id="formato" value="fisico">

  <div class="fila">
    <div>
      <label for="numero">N° cheque</label>
      <input class="campo-input" type="text" id="numero" name="numero" maxlength="30" required>
    </div>
    <div>
      <label for="importe">Importe</label>
      <input class="campo-input" type="number" id="importe" name="importe" min="0" step="0.01" inputmode="decimal" required>
    </div>
  </div>

  <label>Cuenta emisora</label>
  <div class="seg" data-input="cuenta_id">
    <?php foreach ($cuentasBanco as $i => $cuenta): ?>
      <button type="button" data-value="<?= $cuenta['id'] ?>" class="<?= $i === 0 ? 'on' : '' ?>"><?= htmlspecialchars($cuenta['nombre']) ?></button>
    <?php endforeach; ?>
  </div>
  <input type="hidden" name="cuenta_id" id="cuenta_id" value="<?= $cuentasBanco[0]['id'] ?? '' ?>">

  <label for="destinatario">Entregado a</label>
  <input class="campo-input" type="text" id="destinatario" name="destinatario" maxlength="120" required>

  <label for="fecha_pago">Fecha de débito</label>
  <input class="campo-input" type="date" id="fecha_pago" name="fecha_pago" required>

  <button type="submit" class="btn">Guardar cheque emitido</button>
</form>

<?php endif; ?>

<h1 class="seccion">Compromisos futuros</h1>

<?php if (!$porSemana): ?>
  <p class="nota">No hay cheques propios pendientes de débito.</p>
<?php endif; ?>

<?php foreach ($porSemana as $semana): ?>
  <div class="totalbar">
    <span>Semana del <?= $semana['inicio']->format('d/m') ?> al <?= $semana['inicio']->modify('+6 days')->format('d/m') ?></span>
    <b><?= formatearImporte($semana['total']) ?></b>
  </div>

  <?php foreach ($semana['cheques'] as $cheque): ?>
    <div class="item">
      <div class="l1">
        <span class="num"><a href="ficha.php?id=<?= $cheque['id'] ?>"><?= htmlspecialchars($cheque['numero']) ?> · <?= htmlspecialchars($cheque['destinatario']) ?></a></span>
        <span class="imp"><?= formatearImporte((float) $cheque['importe']) ?></span>
      </div>
      <div class="l2">
        <span>Débito <?= formatearFecha($cheque['fecha_pago']) ?></span>
      </div>
      <div class="acciones">
        <form method="post" onsubmit="return confirm('¿Marcar este cheque como debitado?');">
          <input type="hidden" name="accion" value="debitar">
          <input type="hidden" name="cheque_id" value="<?= $cheque['id'] ?>">
          <button type="submit" class="p">Debitado</button>
        </form>
        <button type="button" data-accion="rechazar" data-cheque-id="<?= $cheque['id'] ?>">Rechazado</button>
      </div>
    </div>
  <?php endforeach; ?>
<?php endforeach; ?>

<dialog id="dialogRechazar">
  <h3>Rechazar cheque propio</h3>
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
