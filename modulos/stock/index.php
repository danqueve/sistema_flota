<?php

require_once __DIR__ . '/../../includes/auth.php';
requerirRol(['admin', 'taller']);
require_once __DIR__ . '/../../includes/funciones.php';

$pdo = obtenerConexion();
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'movimiento') {
    $repuestoId    = (int) ($_POST['repuesto_id'] ?? 0);
    $tipo          = $_POST['tipo'] ?? '';
    $cantidad      = (int) ($_POST['cantidad'] ?? 0);
    $camionId      = ($_POST['camion_id'] ?? '') !== '' ? (int) $_POST['camion_id'] : null;
    $observaciones = trim($_POST['observaciones'] ?? '');

    if (!in_array($tipo, ['ingreso', 'egreso', 'ajuste'], true)) {
        $errores[] = 'Tipo de movimiento inválido.';
    }
    if ($cantidad <= 0) {
        $errores[] = $tipo === 'ajuste' ? 'Ingresá el stock real (puede ser 0, pero no vacío).' : 'La cantidad tiene que ser mayor a 0.';
    }
    if ($tipo === 'egreso' && !$camionId) {
        $errores[] = 'Para un egreso, elegí el camión de destino.';
    }

    if (!$errores) {
        $stmt = $pdo->prepare('SELECT stock_actual FROM repuestos WHERE id = ?');
        $stmt->execute([$repuestoId]);
        $stockActual = $stmt->fetchColumn();

        if ($stockActual === false) {
            $errores[] = 'No encontré ese repuesto.';
        } else {
            $stockActual = (int) $stockActual;

            if ($tipo === 'ajuste') {
                $nuevoStock          = $cantidad;
                $cantidadMovimiento  = abs($nuevoStock - $stockActual);
            } else {
                $delta              = $tipo === 'egreso' ? -$cantidad : $cantidad;
                $nuevoStock          = $stockActual + $delta;
                $cantidadMovimiento  = $cantidad;
            }

            if ($nuevoStock < 0) {
                $errores[] = 'No hay stock suficiente (' . $stockActual . ' disponible).';
            } else {
                try {
                    $pdo->beginTransaction();
                    $pdo->prepare(
                        'INSERT INTO movimientos_stock (repuesto_id, tipo, cantidad, camion_id, usuario_id, observaciones)
                         VALUES (?, ?, ?, ?, ?, ?)'
                    )->execute([$repuestoId, $tipo, $cantidadMovimiento, $camionId, usuarioActual()['id'], $observaciones ?: null]);
                    $pdo->prepare('UPDATE repuestos SET stock_actual = ? WHERE id = ?')->execute([$nuevoStock, $repuestoId]);
                    $pdo->commit();

                    header('Location: index.php');
                    exit;
                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $errores[] = 'No se pudo registrar el movimiento.';
                }
            }
        }
    }
}

$repuestos = $pdo->query(
    "SELECT * FROM repuestos WHERE activo = 1 ORDER BY categoria, nombre"
)->fetchAll();

$camiones = $pdo->query('SELECT id, patente FROM camiones WHERE activo=1 ORDER BY patente')->fetchAll();

$rol           = usuarioActual()['rol'];
$activo        = 'index';
$scriptsPagina = [BASE_URL . '/assets/js/segmentado.js', BASE_URL . '/assets/js/stock-index.js'];
$tituloPagina  = 'Stock';
require __DIR__ . '/../../includes/header.php';
?>

<h1>Stock de repuestos y cubiertas</h1>

<?php foreach ($errores as $error): ?>
  <p class="login-error"><?= htmlspecialchars($error) ?></p>
<?php endforeach; ?>

<?php if ($rol === 'admin'): ?>
  <a href="repuestos.php" class="btn sec">Administrar repuestos</a>
<?php endif; ?>

<label for="buscador">Buscar</label>
<input class="campo-input" type="search" id="buscador" placeholder="Nombre, marca o código…">

<div id="listaRepuestos" class="seccion">
  <?php foreach ($repuestos as $repuesto):
      $bajoMinimo = $repuesto['stock_actual'] <= $repuesto['stock_minimo'];
      $busqueda   = mb_strtolower($repuesto['nombre'] . ' ' . $repuesto['marca'] . ' ' . $repuesto['codigo'] . ' ' . $repuesto['compatible_con']);
  ?>
    <div class="item" data-busqueda="<?= htmlspecialchars($busqueda) ?>">
      <div class="l1">
        <span class="num"><?= htmlspecialchars($repuesto['nombre']) ?></span>
        <span class="<?= $bajoMinimo ? 'venc' : '' ?>"><?= (int) $repuesto['stock_actual'] ?> en stock</span>
      </div>
      <div class="l2">
        <span><?= htmlspecialchars($repuesto['marca'] ?: 'Sin marca') ?> · <?= $repuesto['categoria'] === 'cubierta' ? 'Cubierta' : 'Repuesto' ?><?= $repuesto['ubicacion'] ? ' · ' . htmlspecialchars($repuesto['ubicacion']) : '' ?></span>
        <?php if ($bajoMinimo): ?><span class="chip rech">bajo mínimo</span><?php endif; ?>
      </div>
      <div class="acciones">
        <button type="button" class="p" data-accion="movimiento" data-repuesto-id="<?= $repuesto['id'] ?>" data-repuesto-nombre="<?= htmlspecialchars($repuesto['nombre']) ?>">Movimiento</button>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php if (!$repuestos): ?>
  <p class="nota">Todavía no hay repuestos cargados.</p>
<?php endif; ?>

<dialog id="dialogMovimiento">
  <h3 id="movimientoRepuestoNombre">Repuesto</h3>
  <form method="post">
    <input type="hidden" name="accion" value="movimiento">
    <input type="hidden" name="repuesto_id" id="movimiento_repuesto_id">

    <label>Tipo</label>
    <div class="seg" data-input="mov_tipo">
      <button type="button" data-value="ingreso" class="on">Ingreso</button>
      <button type="button" data-value="egreso">Egreso</button>
      <button type="button" data-value="ajuste">Ajuste</button>
    </div>
    <input type="hidden" name="tipo" id="mov_tipo" value="ingreso">

    <label for="mov_cantidad" id="etiquetaCantidad">Cantidad</label>
    <input class="campo-input" type="number" id="mov_cantidad" name="cantidad" min="0" step="1" inputmode="numeric" required>

    <div id="camionWrap" class="oculto">
      <label>Camión</label>
      <div class="seg" data-input="mov_camion">
        <?php foreach ($camiones as $camion): ?>
          <button type="button" data-value="<?= $camion['id'] ?>"><?= htmlspecialchars($camion['patente']) ?></button>
        <?php endforeach; ?>
      </div>
      <input type="hidden" name="camion_id" id="mov_camion">
    </div>

    <label for="mov_observaciones">Observaciones (opcional)</label>
    <input class="campo-input" type="text" id="mov_observaciones" name="observaciones" maxlength="200">

    <button type="submit" class="btn">Guardar movimiento</button>
    <button type="button" class="btn-cerrar">Cancelar</button>
  </form>
</dialog>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
