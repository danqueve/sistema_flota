<?php

require_once __DIR__ . '/../../includes/auth.php';
requerirRol(['admin']);
require_once __DIR__ . '/../../includes/funciones.php';

$pdo = obtenerConexion();
$errores = [];

$estacionesCtaCte = $pdo->query("SELECT id, nombre FROM estaciones WHERE tiene_cta_cte=1 AND activo=1 ORDER BY nombre")->fetchAll();
$cuentasPago      = $pdo->query('SELECT id, nombre FROM cuentas WHERE activo=1 ORDER BY tipo, nombre')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar') {
    $estacionId   = (int) ($_POST['estacion_id'] ?? 0);
    $periodo      = $_POST['periodo'] ?? '';
    $importeTotal = $_POST['importe_total'] !== '' ? (float) str_replace(',', '.', $_POST['importe_total']) : null;

    if (!$estacionId) {
        $errores[] = 'Elegí una estación.';
    }
    if (!preg_match('/^\d{4}-\d{2}$/', $periodo)) {
        $errores[] = 'El período es obligatorio.';
    }
    if ($importeTotal === null || $importeTotal <= 0) {
        $errores[] = 'El importe informado por la estación tiene que ser mayor a 0.';
    }

    if (!$errores) {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO resumenes_estacion (estacion_id, periodo, importe_total) VALUES (?, ?, ?)'
            );
            $stmt->execute([$estacionId, $periodo, $importeTotal]);
            header('Location: resumenes.php');
            exit;
        } catch (PDOException $e) {
            $errores[] = $e->getCode() === '23000'
                ? 'Ya existe un resumen cargado para esa estación en ese período.'
                : 'No se pudo guardar el resumen.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'pagar') {
    $resumenId = (int) ($_POST['resumen_id'] ?? 0);
    $cuentaId  = (int) ($_POST['cuenta_id'] ?? 0);

    $stmt = $pdo->prepare('SELECT * FROM resumenes_estacion WHERE id = ? AND pagado = 0');
    $stmt->execute([$resumenId]);
    $resumen = $stmt->fetch() ?: null;

    if (!$resumen) {
        $errores[] = 'Ese resumen ya no está pendiente de pago.';
    } elseif (!$cuentaId) {
        $errores[] = 'Elegí de qué cuenta sale el pago.';
    } else {
        try {
            $pdo->beginTransaction();

            $categoriaId = obtenerCategoriaGastoPorNombre($pdo, 'Combustible');
            $stmt = $pdo->prepare(
                'INSERT INTO movimientos_tesoreria (fecha, cuenta_id, tipo, categoria_id, importe, referencia_tipo, referencia_id, descripcion, usuario_id)
                 VALUES (CURDATE(), ?, "egreso", ?, ?, "resumen_estacion", ?, ?, ?)'
            );
            $stmt->execute([
                $cuentaId,
                $categoriaId,
                $resumen['importe_total'],
                $resumenId,
                'Pago resumen de estación ' . $resumen['periodo'],
                usuarioActual()['id'],
            ]);
            $movimientoId = (int) $pdo->lastInsertId();

            $pdo->prepare('UPDATE resumenes_estacion SET pagado=1, movimiento_id=? WHERE id=?')
                ->execute([$movimientoId, $resumenId]);

            $pdo->commit();
            header('Location: resumenes.php');
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errores[] = 'No se pudo registrar el pago.';
        }
    }
}

$resumenes = $pdo->query(
    "SELECT re.*, e.nombre AS estacion_nombre
     FROM resumenes_estacion re
     JOIN estaciones e ON e.id = re.estacion_id
     ORDER BY re.pagado ASC, re.periodo DESC"
)->fetchAll();

// Acumulado calculado en vivo (mismo cálculo que la pantalla de carga), para comparar contra lo que informa la estación.
$acumuladoPorEstacionYPeriodo = [];
$stmt = $pdo->query(
    "SELECT estacion_id, DATE_FORMAT(fecha, '%Y-%m') AS periodo, SUM(importe) AS total
     FROM cargas_combustible
     WHERE modalidad = 'cta_cte' AND estacion_id IS NOT NULL
     GROUP BY estacion_id, DATE_FORMAT(fecha, '%Y-%m')"
);
foreach ($stmt->fetchAll() as $fila) {
    $acumuladoPorEstacionYPeriodo[$fila['estacion_id'] . '-' . $fila['periodo']] = (float) $fila['total'];
}

$activo        = 'resumenes';
$scriptsPagina = [BASE_URL . '/assets/js/segmentado.js'];
$tituloPagina  = 'Resúmenes de estación';
require __DIR__ . '/../../includes/header.php';
?>

<h1>Resúmenes de estación</h1>

<?php foreach ($errores as $error): ?>
  <p class="login-error"><?= htmlspecialchars($error) ?></p>
<?php endforeach; ?>

<?php if (!$estacionesCtaCte): ?>
  <p class="nota">No hay estaciones con cuenta corriente activas.</p>
<?php else: ?>

<form method="post">
  <input type="hidden" name="accion" value="guardar">

  <label>Estación</label>
  <div class="seg" data-input="estacion_id">
    <?php foreach ($estacionesCtaCte as $i => $estacion): ?>
      <button type="button" data-value="<?= $estacion['id'] ?>" class="<?= $i === 0 ? 'on' : '' ?>"><?= htmlspecialchars($estacion['nombre']) ?></button>
    <?php endforeach; ?>
  </div>
  <input type="hidden" name="estacion_id" id="estacion_id" value="<?= $estacionesCtaCte[0]['id'] ?? '' ?>">

  <div class="fila">
    <div>
      <label for="periodo">Período</label>
      <input class="campo-input" type="month" id="periodo" name="periodo" value="<?= date('Y-m') ?>" required>
    </div>
    <div>
      <label for="importe_total">Importe informado por la estación</label>
      <input class="campo-input" type="number" id="importe_total" name="importe_total" min="0" step="0.01" inputmode="decimal" required>
    </div>
  </div>

  <p class="nota">Comparalo contra el acumulado que ya calcula el sistema en <a href="nuevo.php">Carga de combustible</a> antes de guardar — esta es la mini-conciliación.</p>

  <button type="submit" class="btn">Guardar resumen</button>
</form>

<?php endif; ?>

<h1 class="seccion">Resúmenes cargados</h1>

<?php foreach ($resumenes as $resumen):
    $claveAcumulado = $resumen['estacion_id'] . '-' . $resumen['periodo'];
    $acumuladoSistema = $acumuladoPorEstacionYPeriodo[$claveAcumulado] ?? 0.0;
    $diferencia = (float) $resumen['importe_total'] - $acumuladoSistema;
?>
  <div class="item">
    <div class="l1">
      <span class="num"><?= htmlspecialchars($resumen['estacion_nombre']) ?> · <?= htmlspecialchars($resumen['periodo']) ?></span>
      <span class="imp"><?= formatearImporte((float) $resumen['importe_total']) ?></span>
    </div>
    <div class="l2">
      <span>Sistema calculó <?= formatearImporte($acumuladoSistema) ?><?= abs($diferencia) > 0.009 ? ' · diferencia ' . formatearImporte($diferencia) : '' ?></span>
      <span class="chip <?= $resumen['pagado'] ? 'ok' : 'cartera' ?>"><?= $resumen['pagado'] ? 'pagado' : 'pendiente' ?></span>
    </div>
    <?php if (!$resumen['pagado']): ?>
      <div class="acciones">
        <button type="button" class="p" onclick="document.getElementById('cuenta_pago_<?= $resumen['id'] ?>').value=''; document.getElementById('dialogPagar<?= $resumen['id'] ?>').showModal();">Marcar pagado</button>
      </div>
      <dialog id="dialogPagar<?= $resumen['id'] ?>">
        <h3>Pagar resumen <?= htmlspecialchars($resumen['estacion_nombre']) ?></h3>
        <form method="post">
          <input type="hidden" name="accion" value="pagar">
          <input type="hidden" name="resumen_id" value="<?= $resumen['id'] ?>">
          <label>Cuenta de pago</label>
          <div class="seg" data-input="cuenta_pago_<?= $resumen['id'] ?>">
            <?php foreach ($cuentasPago as $cuenta): ?>
              <button type="button" data-value="<?= $cuenta['id'] ?>"><?= htmlspecialchars($cuenta['nombre']) ?></button>
            <?php endforeach; ?>
          </div>
          <input type="hidden" name="cuenta_id" id="cuenta_pago_<?= $resumen['id'] ?>">
          <button type="submit" class="btn">Confirmar pago</button>
          <button type="button" class="btn-cerrar" onclick="document.getElementById('dialogPagar<?= $resumen['id'] ?>').close()">Cancelar</button>
        </form>
      </dialog>
    <?php endif; ?>
  </div>
<?php endforeach; ?>

<?php if (!$resumenes): ?>
  <p class="nota">Todavía no hay resúmenes cargados.</p>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
