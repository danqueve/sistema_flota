<?php

require_once __DIR__ . '/../../includes/auth.php';
requerirRol(['admin']);
require_once __DIR__ . '/../../includes/funciones.php';

$pdo = obtenerConexion();
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar') {
    $id            = ($_POST['id'] ?? '') !== '' ? (int) $_POST['id'] : null;
    $codigo        = trim($_POST['codigo'] ?? '');
    $nombre        = trim($_POST['nombre'] ?? '');
    $categoria     = ($_POST['categoria'] ?? '') === 'cubierta' ? 'cubierta' : 'repuesto';
    $marca         = trim($_POST['marca'] ?? '');
    $compatibleCon = trim($_POST['compatible_con'] ?? '');
    $stockMinimo   = $_POST['stock_minimo'] !== '' ? (int) $_POST['stock_minimo'] : 0;
    $ubicacion     = trim($_POST['ubicacion'] ?? '');
    $costoUnitario = $_POST['costo_unitario'] !== '' ? (float) str_replace(',', '.', $_POST['costo_unitario']) : null;

    if ($nombre === '') {
        $errores[] = 'El nombre es obligatorio.';
    }

    if (!$errores) {
        if ($id) {
            $stmt = $pdo->prepare(
                'UPDATE repuestos SET codigo=?, nombre=?, categoria=?, marca=?, compatible_con=?, stock_minimo=?, ubicacion=?, costo_unitario=? WHERE id=?'
            );
            $stmt->execute([$codigo ?: null, $nombre, $categoria, $marca ?: null, $compatibleCon ?: null, $stockMinimo, $ubicacion ?: null, $costoUnitario, $id]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO repuestos (codigo, nombre, categoria, marca, compatible_con, stock_minimo, ubicacion, costo_unitario)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$codigo ?: null, $nombre, $categoria, $marca ?: null, $compatibleCon ?: null, $stockMinimo, $ubicacion ?: null, $costoUnitario]);
        }
        header('Location: repuestos.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cambiar_estado') {
    $stmt = $pdo->prepare('UPDATE repuestos SET activo=? WHERE id=?');
    $stmt->execute([(int) $_POST['activo'], (int) $_POST['id']]);
    header('Location: repuestos.php');
    exit;
}

$registroEditar = null;
if (isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM repuestos WHERE id=?');
    $stmt->execute([(int) $_GET['id']]);
    $registroEditar = $stmt->fetch() ?: null;
}

$repuestos = $pdo->query('SELECT * FROM repuestos ORDER BY activo DESC, categoria, nombre')->fetchAll();

$tituloPagina = 'Repuestos';
require __DIR__ . '/../../includes/header.php';
?>

<h1><?= $registroEditar ? 'Editar repuesto' : 'Nuevo repuesto' ?></h1>

<?php foreach ($errores as $error): ?>
  <p class="login-error"><?= htmlspecialchars($error) ?></p>
<?php endforeach; ?>

<form method="post">
  <input type="hidden" name="accion" value="guardar">
  <input type="hidden" name="id" value="<?= $registroEditar['id'] ?? '' ?>">

  <label for="nombre">Nombre</label>
  <input class="campo-input" type="text" id="nombre" name="nombre" maxlength="120" required
    value="<?= htmlspecialchars($registroEditar['nombre'] ?? '') ?>">

  <div class="fila">
    <div>
      <label for="codigo">Código</label>
      <input class="campo-input" type="text" id="codigo" name="codigo" maxlength="30"
        value="<?= htmlspecialchars($registroEditar['codigo'] ?? '') ?>">
    </div>
    <div>
      <label for="marca">Marca</label>
      <input class="campo-input" type="text" id="marca" name="marca" maxlength="60"
        value="<?= htmlspecialchars($registroEditar['marca'] ?? '') ?>">
    </div>
  </div>

  <label>Categoría</label>
  <div class="seg" data-input="categoria">
    <button type="button" data-value="repuesto" class="<?= ($registroEditar['categoria'] ?? 'repuesto') === 'repuesto' ? 'on' : '' ?>">Repuesto</button>
    <button type="button" data-value="cubierta" class="<?= ($registroEditar['categoria'] ?? '') === 'cubierta' ? 'on' : '' ?>">Cubierta</button>
  </div>
  <input type="hidden" name="categoria" id="categoria" value="<?= htmlspecialchars($registroEditar['categoria'] ?? 'repuesto') ?>">

  <label for="compatible_con">Compatible con</label>
  <input class="campo-input" type="text" id="compatible_con" name="compatible_con" maxlength="120" placeholder="Ej. Scania G360 / todos"
    value="<?= htmlspecialchars($registroEditar['compatible_con'] ?? '') ?>">

  <div class="fila">
    <div>
      <label for="stock_minimo">Stock mínimo</label>
      <input class="campo-input" type="number" id="stock_minimo" name="stock_minimo" min="0" step="1"
        value="<?= htmlspecialchars((string) ($registroEditar['stock_minimo'] ?? '0')) ?>">
    </div>
    <div>
      <label for="ubicacion">Ubicación</label>
      <input class="campo-input" type="text" id="ubicacion" name="ubicacion" maxlength="60"
        value="<?= htmlspecialchars($registroEditar['ubicacion'] ?? '') ?>">
    </div>
  </div>

  <label for="costo_unitario">Costo unitario</label>
  <input class="campo-input" type="number" id="costo_unitario" name="costo_unitario" min="0" step="0.01" inputmode="decimal"
    value="<?= htmlspecialchars((string) ($registroEditar['costo_unitario'] ?? '')) ?>">

  <button type="submit" class="btn">Guardar repuesto</button>
  <?php if ($registroEditar): ?>
    <a href="repuestos.php" class="btn sec">Cancelar edición</a>
  <?php endif; ?>
</form>

<a href="index.php" class="btn sec seccion">‹ Ver stock</a>

<h1 class="seccion">Repuestos cargados</h1>

<?php foreach ($repuestos as $repuesto): ?>
  <div class="item <?= $repuesto['activo'] ? '' : 'inactivo' ?>">
    <div class="l1">
      <span class="num"><?= htmlspecialchars($repuesto['nombre']) ?></span>
      <span><?= (int) $repuesto['stock_actual'] ?> / mín. <?= (int) $repuesto['stock_minimo'] ?></span>
    </div>
    <div class="l2">
      <span><?= htmlspecialchars($repuesto['marca'] ?: 'Sin marca') ?> · <?= $repuesto['categoria'] === 'cubierta' ? 'Cubierta' : 'Repuesto' ?></span>
      <?php if (!$repuesto['activo']): ?><span class="chip rech">inactivo</span><?php endif; ?>
    </div>
    <div class="acciones">
      <a href="repuestos.php?id=<?= $repuesto['id'] ?>">Editar</a>
      <form method="post">
        <input type="hidden" name="accion" value="cambiar_estado">
        <input type="hidden" name="id" value="<?= $repuesto['id'] ?>">
        <input type="hidden" name="activo" value="<?= $repuesto['activo'] ? 0 : 1 ?>">
        <button type="submit"><?= $repuesto['activo'] ? 'Desactivar' : 'Activar' ?></button>
      </form>
    </div>
  </div>
<?php endforeach; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
