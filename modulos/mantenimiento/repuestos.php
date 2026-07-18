<?php

require_once __DIR__ . '/../../includes/auth.php';
requerirRol(['admin']);
require_once __DIR__ . '/../../includes/funciones.php';

$pdo = obtenerConexion();
$errores = [];

$serviceId = (int) ($_GET['service_id'] ?? $_POST['service_id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT s.*, c.patente, ts.nombre AS tipo_nombre
     FROM services s
     JOIN camiones c ON c.id = s.camion_id
     JOIN tipos_service ts ON ts.id = s.tipo_service_id
     WHERE s.id = ?'
);
$stmt->execute([$serviceId]);
$service = $stmt->fetch() ?: null;

if ($service && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'agregar') {
    $repuestoId = (int) ($_POST['repuesto_id'] ?? 0);
    $cantidad   = (int) ($_POST['cantidad'] ?? 0);

    if ($cantidad <= 0) {
        $errores[] = 'La cantidad tiene que ser mayor a 0.';
    }

    if (!$errores) {
        $stmt = $pdo->prepare('SELECT stock_actual FROM repuestos WHERE id = ?');
        $stmt->execute([$repuestoId]);
        $stockActual = $stmt->fetchColumn();

        if ($stockActual === false) {
            $errores[] = 'No encontré ese repuesto.';
        } elseif ((int) $stockActual < $cantidad) {
            $errores[] = 'No hay stock suficiente (' . (int) $stockActual . ' disponible).';
        } else {
            try {
                $pdo->beginTransaction();
                $pdo->prepare(
                    'INSERT INTO movimientos_stock (repuesto_id, tipo, cantidad, camion_id, service_id, usuario_id)
                     VALUES (?, "egreso", ?, ?, ?, ?)'
                )->execute([$repuestoId, $cantidad, $service['camion_id'], $serviceId, usuarioActual()['id']]);
                $pdo->prepare('UPDATE repuestos SET stock_actual = stock_actual - ? WHERE id = ?')
                    ->execute([$cantidad, $repuestoId]);
                $pdo->commit();

                header('Location: repuestos.php?service_id=' . $serviceId);
                exit;
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errores[] = 'No se pudo registrar el repuesto usado.';
            }
        }
    }
}

if ($service && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'actualizar_costo') {
    $costo = $_POST['costo'] !== '' ? (float) str_replace(',', '.', $_POST['costo']) : null;

    if ($costo !== null && $costo < 0) {
        $errores[] = 'El costo no puede ser negativo.';
    } else {
        $pdo->prepare('UPDATE services SET costo = ? WHERE id = ?')->execute([$costo, $serviceId]);
        header('Location: repuestos.php?service_id=' . $serviceId);
        exit;
    }
}

if ($service && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar') {
    $movimientoId = (int) ($_POST['movimiento_id'] ?? 0);

    $stmt = $pdo->prepare('SELECT * FROM movimientos_stock WHERE id = ? AND service_id = ?');
    $stmt->execute([$movimientoId, $serviceId]);
    $movimiento = $stmt->fetch() ?: null;

    if ($movimiento) {
        try {
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE repuestos SET stock_actual = stock_actual + ? WHERE id = ?')
                ->execute([$movimiento['cantidad'], $movimiento['repuesto_id']]);
            $pdo->prepare('DELETE FROM movimientos_stock WHERE id = ?')->execute([$movimientoId]);
            $pdo->commit();

            header('Location: repuestos.php?service_id=' . $serviceId);
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errores[] = 'No se pudo quitar el repuesto.';
        }
    }
}

$repuestos = $pdo->query('SELECT * FROM repuestos WHERE activo = 1 ORDER BY categoria, nombre')->fetchAll();

$usados = [];
$totalUsados = 0.0;
if ($service) {
    $stmt = $pdo->prepare(
        'SELECT ms.*, r.nombre, r.costo_unitario
         FROM movimientos_stock ms
         JOIN repuestos r ON r.id = ms.repuesto_id
         WHERE ms.service_id = ?
         ORDER BY ms.id DESC'
    );
    $stmt->execute([$serviceId]);
    $usados = $stmt->fetchAll();
    foreach ($usados as $u) {
        $totalUsados += (float) $u['cantidad'] * (float) ($u['costo_unitario'] ?? 0);
    }
}

$activo         = 'nuevo';
$scriptsPagina  = [BASE_URL . '/assets/js/segmentado.js', BASE_URL . '/assets/js/mantenimiento-repuestos.js'];
$tituloPagina   = 'Repuestos del service';
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/tabs.php';
?>

<h1>Repuestos usados</h1>

<?php if (!$service): ?>

  <p class="nota">No encontré ese service. Volvé a <a href="nuevo.php">Nuevo service</a>.</p>

<?php else: ?>

  <div class="item">
    <div class="l1">
      <span class="num"><?= htmlspecialchars($service['patente']) ?> · <?= htmlspecialchars($service['tipo_nombre']) ?></span>
      <span><?= formatearFecha($service['fecha']) ?></span>
    </div>
    <div class="l2">
      <span><?= $service['taller'] ? htmlspecialchars($service['taller']) : 'Sin taller informado' ?></span>
      <span><?= $service['costo'] !== null ? formatearImporte((float) $service['costo']) : 'Sin costo cargado' ?></span>
    </div>
  </div>

  <?php foreach ($errores as $error): ?>
    <p class="login-error"><?= htmlspecialchars($error) ?></p>
  <?php endforeach; ?>

  <label for="buscador">Buscar repuesto</label>
  <input class="campo-input" type="search" id="buscador" placeholder="Nombre, marca o código…">

  <div id="listaRepuestos" class="seccion">
    <?php foreach ($repuestos as $repuesto):
        $busqueda = mb_strtolower($repuesto['nombre'] . ' ' . $repuesto['marca'] . ' ' . $repuesto['codigo']);
    ?>
      <div class="item" data-busqueda="<?= htmlspecialchars($busqueda) ?>">
        <div class="l1">
          <span class="num"><?= htmlspecialchars($repuesto['nombre']) ?></span>
          <span><?= (int) $repuesto['stock_actual'] ?> en stock</span>
        </div>
        <div class="l2">
          <span><?= htmlspecialchars($repuesto['marca'] ?: 'Sin marca') ?></span>
        </div>
        <div class="acciones">
          <button type="button" class="p" data-accion="usar" data-repuesto-id="<?= $repuesto['id'] ?>" data-repuesto-nombre="<?= htmlspecialchars($repuesto['nombre']) ?>">Usar</button>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if (!$repuestos): ?>
    <p class="nota">Todavía no hay repuestos cargados en stock.</p>
  <?php endif; ?>

  <dialog id="dialogUsar">
    <h3 id="usarRepuestoNombre">Repuesto</h3>
    <form method="post">
      <input type="hidden" name="accion" value="agregar">
      <input type="hidden" name="service_id" value="<?= $serviceId ?>">
      <input type="hidden" name="repuesto_id" id="usar_repuesto_id">

      <label for="usar_cantidad">Cantidad usada</label>
      <input class="campo-input" type="number" id="usar_cantidad" name="cantidad" min="1" step="1" inputmode="numeric" required>

      <button type="submit" class="btn">Registrar uso</button>
      <button type="button" class="btn-cerrar">Cancelar</button>
    </form>
  </dialog>

  <h1 class="seccion">Repuestos cargados en este service</h1>

  <?php if (!$usados): ?>
    <p class="nota">Todavía no cargaste repuestos usados en este service.</p>
  <?php endif; ?>

  <?php foreach ($usados as $u): ?>
    <div class="item">
      <div class="l1">
        <span class="num"><?= htmlspecialchars($u['nombre']) ?></span>
        <span class="imp"><?= formatearImporte((float) $u['cantidad'] * (float) ($u['costo_unitario'] ?? 0)) ?></span>
      </div>
      <div class="l2">
        <span><?= (int) $u['cantidad'] ?> unidad(es)</span>
      </div>
      <div class="acciones">
        <form method="post" onsubmit="return confirm('¿Quitar este repuesto del service? El stock vuelve a sumarse.');">
          <input type="hidden" name="accion" value="eliminar">
          <input type="hidden" name="service_id" value="<?= $serviceId ?>">
          <input type="hidden" name="movimiento_id" value="<?= $u['id'] ?>">
          <button type="submit">Quitar</button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>

  <?php if ($usados): ?>
    <div class="totalbar">
      <span>Total repuestos usados</span>
      <b><?= formatearImporte($totalUsados) ?></b>
    </div>
  <?php endif; ?>

  <h1 class="seccion">Costo del service</h1>

  <form method="post">
    <input type="hidden" name="accion" value="actualizar_costo">
    <input type="hidden" name="service_id" value="<?= $serviceId ?>">

    <label for="costo_final">Costo total (repuestos <?= formatearImporte($totalUsados) ?> + mano de obra)</label>
    <input class="campo-input" type="number" id="costo_final" name="costo" min="0" step="0.01" inputmode="decimal"
      value="<?= htmlspecialchars((string) ($service['costo'] ?? '')) ?>">

    <button type="submit" class="btn sec">Guardar costo</button>
  </form>

  <p class="nota">Si este costo implica un pago, cargalo como egreso desde <a href="<?= BASE_URL ?>/modulos/tesoreria/nuevo.php">Tesorería</a>.</p>

  <a href="nuevo.php" class="btn sec">+ Nuevo service</a>

<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
