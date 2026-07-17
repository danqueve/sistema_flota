<?php

require_once __DIR__ . '/../../includes/auth.php';
requerirRol(['admin']);
require_once __DIR__ . '/../../includes/funciones.php';

$pdo = obtenerConexion();
$errores = [];
$gastos = [];
$totalGastos = 0.0;

$fleteId = (int) ($_GET['flete_id'] ?? $_POST['flete_id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT f.*, c.patente, ch.nombre AS chofer_nombre
     FROM fletes f
     JOIN camiones c ON c.id = f.camion_id
     JOIN choferes ch ON ch.id = f.chofer_id
     WHERE f.id = ?'
);
$stmt->execute([$fleteId]);
$flete = $stmt->fetch() ?: null;

$valores = ['categoria_id' => '', 'importe' => '', 'descripcion' => ''];

if ($flete) {
    $categorias = $pdo->query("SELECT id, nombre FROM categorias_gasto WHERE ambito='viaje' AND activo=1 ORDER BY id")->fetchAll();
    $valores['categoria_id'] = $categorias[0]['id'] ?? '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'agregar') {
        $categoriaId = (int) ($_POST['categoria_id'] ?? 0);
        $importe     = $_POST['importe'] !== '' ? (float) str_replace(',', '.', $_POST['importe']) : null;
        $descripcion = trim($_POST['descripcion'] ?? '');

        $valores = [
            'categoria_id' => $categoriaId ?: '',
            'importe'      => $_POST['importe'] ?? '',
            'descripcion'  => $descripcion,
        ];

        if (!$categoriaId) {
            $errores[] = 'Elegí una categoría.';
        }
        if ($importe === null || $importe <= 0) {
            $errores[] = 'El importe tiene que ser mayor a 0.';
        }

        if (!$errores) {
            $stmt = $pdo->prepare('INSERT INTO gastos_viaje (flete_id, categoria_id, importe, descripcion) VALUES (?, ?, ?, ?)');
            $stmt->execute([$fleteId, $categoriaId, $importe, $descripcion ?: null]);
            header('Location: gastos.php?flete_id=' . $fleteId);
            exit;
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar') {
        $stmt = $pdo->prepare('DELETE FROM gastos_viaje WHERE id = ? AND flete_id = ?');
        $stmt->execute([(int) $_POST['gasto_id'], $fleteId]);
        header('Location: gastos.php?flete_id=' . $fleteId);
        exit;
    }

    $stmtGastos = $pdo->prepare(
        'SELECT g.*, cg.nombre AS categoria_nombre
         FROM gastos_viaje g
         JOIN categorias_gasto cg ON cg.id = g.categoria_id
         WHERE g.flete_id = ?
         ORDER BY g.id DESC'
    );
    $stmtGastos->execute([$fleteId]);
    $gastos = $stmtGastos->fetchAll();
    $totalGastos = (float) array_sum(array_column($gastos, 'importe'));
}

$scriptsPagina = [BASE_URL . '/assets/js/segmentado.js'];
$tituloPagina = 'Gastos del viaje';
require __DIR__ . '/../../includes/header.php';
?>

<h1>Gastos del viaje</h1>

<?php if (!$flete): ?>

  <p class="nota">No encontré ese flete. Volvé a <a href="nuevo.php">Nuevo flete</a> y usá "Guardar y cargar gastos del viaje".</p>

<?php else: ?>

  <div class="item">
    <div class="l1">
      <span class="num"><?= htmlspecialchars($flete['patente']) ?> · <?= htmlspecialchars($flete['chofer_nombre']) ?></span>
      <span><?= formatearFecha($flete['fecha']) ?></span>
    </div>
    <div class="l2">
      <span><?= htmlspecialchars($flete['destino']) ?></span>
      <span><?= formatearImporte((float) $flete['importe_bruto']) ?></span>
    </div>
  </div>

  <div class="consumo">
    <div><small>Viático adelantado</small><b><?= formatearImporte((float) $flete['viatico_adelanto']) ?></b></div>
    <div><small>Gastos cargados</small><b><?= formatearImporte($totalGastos) ?></b></div>
  </div>

  <?php foreach ($errores as $error): ?>
    <p class="login-error"><?= htmlspecialchars($error) ?></p>
  <?php endforeach; ?>

  <?php if (!$categorias): ?>
    <p class="nota">No hay categorías de gasto de viaje activas para elegir.</p>
  <?php else: ?>
  <form method="post" class="seccion">
    <input type="hidden" name="accion" value="agregar">
    <input type="hidden" name="flete_id" value="<?= $fleteId ?>">

    <label>Categoría</label>
    <div class="seg" data-input="categoria_id">
      <?php foreach ($categorias as $categoria): ?>
        <button type="button" data-value="<?= $categoria['id'] ?>" class="<?= (string) $valores['categoria_id'] === (string) $categoria['id'] ? 'on' : '' ?>"><?= htmlspecialchars($categoria['nombre']) ?></button>
      <?php endforeach; ?>
    </div>
    <input type="hidden" name="categoria_id" id="categoria_id" value="<?= htmlspecialchars((string) $valores['categoria_id']) ?>">

    <div class="fila">
      <div>
        <label for="importe">Importe</label>
        <input class="campo-input" type="number" id="importe" name="importe" min="0" step="0.01" inputmode="decimal" required
          value="<?= htmlspecialchars((string) $valores['importe']) ?>">
      </div>
      <div>
        <label for="descripcion">Descripción (opcional)</label>
        <input class="campo-input" type="text" id="descripcion" name="descripcion" maxlength="150"
          value="<?= htmlspecialchars($valores['descripcion']) ?>">
      </div>
    </div>

    <button type="submit" class="btn">Agregar gasto</button>
  </form>
  <?php endif; ?>

  <h1 class="seccion">Gastos cargados</h1>

  <?php foreach ($gastos as $gasto): ?>
    <div class="item">
      <div class="l1">
        <span class="num"><?= htmlspecialchars($gasto['categoria_nombre']) ?></span>
        <span class="imp"><?= formatearImporte((float) $gasto['importe']) ?></span>
      </div>
      <div class="l2">
        <span><?= htmlspecialchars($gasto['descripcion'] ?: '—') ?></span>
      </div>
      <div class="acciones">
        <form method="post" onsubmit="return confirm('¿Eliminar este gasto?');">
          <input type="hidden" name="accion" value="eliminar">
          <input type="hidden" name="flete_id" value="<?= $fleteId ?>">
          <input type="hidden" name="gasto_id" value="<?= $gasto['id'] ?>">
          <button type="submit">Eliminar</button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>

  <?php if (!$gastos): ?>
    <p class="nota">Todavía no cargaste gastos para este viaje.</p>
  <?php endif; ?>

  <a href="nuevo.php" class="btn sec">+ Nuevo flete</a>

<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
