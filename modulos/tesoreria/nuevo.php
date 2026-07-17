<?php

require_once __DIR__ . '/../../includes/auth.php';
requerirRol(['admin']);
require_once __DIR__ . '/../../includes/funciones.php';

$pdo = obtenerConexion();
$errores = [];

$cuentas    = $pdo->query('SELECT id, nombre FROM cuentas WHERE activo=1 ORDER BY tipo, nombre')->fetchAll();
$categorias = $pdo->query('SELECT id, nombre FROM categorias_gasto WHERE activo=1 ORDER BY nombre')->fetchAll();

$valores = [
    'cuenta_id'    => $cuentas[0]['id'] ?? '',
    'tipo'         => 'egreso',
    'categoria_id' => '',
    'importe'      => '',
    'fecha'        => date('Y-m-d'),
    'descripcion'  => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cuentaId    = (int) ($_POST['cuenta_id'] ?? 0);
    $tipo        = ($_POST['tipo'] ?? '') === 'ingreso' ? 'ingreso' : 'egreso';
    $categoriaId = ($_POST['categoria_id'] ?? '') !== '' ? (int) $_POST['categoria_id'] : null;
    $importe     = $_POST['importe'] !== '' ? (float) str_replace(',', '.', $_POST['importe']) : null;
    $fecha       = $_POST['fecha'] ?? '';
    $descripcion = trim($_POST['descripcion'] ?? '');

    $valores = [
        'cuenta_id'    => $cuentaId ?: '',
        'tipo'         => $tipo,
        'categoria_id' => $categoriaId ?? '',
        'importe'      => $_POST['importe'] ?? '',
        'fecha'        => $fecha,
        'descripcion'  => $descripcion,
    ];

    if (!$cuentaId) {
        $errores[] = 'Elegí una cuenta.';
    }
    if ($importe === null || $importe <= 0) {
        $errores[] = 'El importe tiene que ser mayor a 0.';
    }
    if ($fecha === '') {
        $errores[] = 'La fecha es obligatoria.';
    }

    if (!$errores) {
        $stmt = $pdo->prepare(
            'INSERT INTO movimientos_tesoreria (fecha, cuenta_id, tipo, categoria_id, importe, referencia_tipo, descripcion, usuario_id)
             VALUES (?, ?, ?, ?, ?, "otro", ?, ?)'
        );
        $stmt->execute([$fecha, $cuentaId, $tipo, $categoriaId, $importe, $descripcion ?: null, usuarioActual()['id']]);
        header('Location: listado.php');
        exit;
    }
}

$activo       = 'nuevo';
$tituloPagina = 'Nuevo movimiento';
require __DIR__ . '/../../includes/header.php';
?>

<h1>Nuevo movimiento</h1>

<?php foreach ($errores as $error): ?>
  <p class="login-error"><?= htmlspecialchars($error) ?></p>
<?php endforeach; ?>

<?php if (!$cuentas): ?>
  <p class="nota">Hace falta al menos una cuenta activa. Cargala en <a href="<?= BASE_URL ?>/modulos/cheques/cuentas.php">Cuentas</a>.</p>
<?php else: ?>

<form method="post">
  <label>Tipo</label>
  <div class="seg" data-input="tipo">
    <button type="button" data-value="ingreso" class="<?= $valores['tipo'] === 'ingreso' ? 'on' : '' ?>">Ingreso</button>
    <button type="button" data-value="egreso" class="<?= $valores['tipo'] === 'egreso' ? 'on' : '' ?>">Egreso</button>
  </div>
  <input type="hidden" name="tipo" id="tipo" value="<?= htmlspecialchars($valores['tipo']) ?>">

  <label>Cuenta</label>
  <div class="seg" data-input="cuenta_id">
    <?php foreach ($cuentas as $cuenta): ?>
      <button type="button" data-value="<?= $cuenta['id'] ?>" class="<?= (string) $valores['cuenta_id'] === (string) $cuenta['id'] ? 'on' : '' ?>"><?= htmlspecialchars($cuenta['nombre']) ?></button>
    <?php endforeach; ?>
  </div>
  <input type="hidden" name="cuenta_id" id="cuenta_id" value="<?= htmlspecialchars((string) $valores['cuenta_id']) ?>">

  <label for="categoria_id">Categoría</label>
  <select class="campo-input" id="categoria_id" name="categoria_id">
    <option value="">— Sin categoría —</option>
    <?php foreach ($categorias as $categoria): ?>
      <option value="<?= $categoria['id'] ?>" <?= (string) $valores['categoria_id'] === (string) $categoria['id'] ? 'selected' : '' ?>><?= htmlspecialchars($categoria['nombre']) ?></option>
    <?php endforeach; ?>
  </select>

  <div class="fila">
    <div>
      <label for="importe">Importe</label>
      <input class="campo-input" type="number" id="importe" name="importe" min="0" step="0.01" inputmode="decimal" required
        value="<?= htmlspecialchars((string) $valores['importe']) ?>">
    </div>
    <div>
      <label for="fecha">Fecha</label>
      <input class="campo-input" type="date" id="fecha" name="fecha" required
        value="<?= htmlspecialchars($valores['fecha']) ?>">
    </div>
  </div>

  <label for="descripcion">Descripción (opcional)</label>
  <input class="campo-input" type="text" id="descripcion" name="descripcion" maxlength="200"
    value="<?= htmlspecialchars($valores['descripcion']) ?>">

  <button type="submit" class="btn">Guardar movimiento</button>
</form>

<?php endif; ?>

<a href="listado.php" class="btn sec">‹ Volver al listado</a>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
