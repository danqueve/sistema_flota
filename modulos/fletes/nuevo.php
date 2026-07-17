<?php

require_once __DIR__ . '/../../includes/auth.php';
requerirRol(['admin']);
require_once __DIR__ . '/../../includes/funciones.php';

$pdo = obtenerConexion();
$errores = [];
$guardado = null;

$camiones = $pdo->query('SELECT id, patente FROM camiones WHERE activo=1 ORDER BY patente')->fetchAll();
$choferes = $pdo->query('SELECT id, nombre FROM choferes WHERE activo=1 ORDER BY nombre')->fetchAll();
$clientes = $pdo->query('SELECT id, razon_social FROM clientes WHERE activo=1 ORDER BY razon_social')->fetchAll();

$valores = [
    'camion_id'        => $camiones[0]['id'] ?? '',
    'chofer_id'        => $choferes[0]['id'] ?? '',
    'cliente_id'       => '',
    'fecha'            => date('Y-m-d'),
    'destino'          => '',
    'importe_bruto'    => '',
    'viatico_adelanto' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $camionId        = (int) ($_POST['camion_id'] ?? 0);
    $choferId        = (int) ($_POST['chofer_id'] ?? 0);
    $clienteId       = ($_POST['cliente_id'] ?? '') !== '' ? (int) $_POST['cliente_id'] : null;
    $fecha           = $_POST['fecha'] ?? '';
    $destino         = trim($_POST['destino'] ?? '');
    $importeBruto    = $_POST['importe_bruto'] !== '' ? (float) str_replace(',', '.', $_POST['importe_bruto']) : null;
    $viaticoAdelanto = $_POST['viatico_adelanto'] !== '' ? (float) str_replace(',', '.', $_POST['viatico_adelanto']) : 0.0;
    $continuarGastos = ($_POST['accion'] ?? '') === 'guardar_gastos';

    $valores = [
        'camion_id'        => $camionId ?: '',
        'chofer_id'        => $choferId ?: '',
        'cliente_id'       => $clienteId ?? '',
        'fecha'            => $fecha,
        'destino'          => $destino,
        'importe_bruto'    => $_POST['importe_bruto'] ?? '',
        'viatico_adelanto' => $_POST['viatico_adelanto'] ?? '',
    ];

    if (!$camionId) {
        $errores[] = 'Elegí un camión.';
    }
    if (!$choferId) {
        $errores[] = 'Elegí un chofer.';
    }
    if ($fecha === '') {
        $errores[] = 'La fecha es obligatoria.';
    }
    if ($destino === '') {
        $errores[] = 'El destino es obligatorio.';
    }
    if ($importeBruto === null || $importeBruto <= 0) {
        $errores[] = 'El importe bruto tiene que ser mayor a 0.';
    }

    if (!$errores) {
        $pctComision    = (float) obtenerParametro('pct_comision_chofer', '0');
        $comisionChofer = round($importeBruto * $pctComision / 100, 2);

        $stmt = $pdo->prepare(
            'INSERT INTO fletes (fecha, camion_id, chofer_id, cliente_id, destino, importe_bruto, pct_comision, comision_chofer, viatico_adelanto)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$fecha, $camionId, $choferId, $clienteId, $destino, $importeBruto, $pctComision, $comisionChofer, $viaticoAdelanto]);
        $fleteId = (int) $pdo->lastInsertId();

        if ($continuarGastos) {
            header('Location: gastos.php?flete_id=' . $fleteId);
            exit;
        }

        header('Location: nuevo.php?guardado=' . $fleteId);
        exit;
    }
}

if (isset($_GET['guardado'])) {
    $stmt = $pdo->prepare(
        'SELECT f.*, c.patente, ch.nombre AS chofer_nombre
         FROM fletes f
         JOIN camiones c ON c.id = f.camion_id
         JOIN choferes ch ON ch.id = f.chofer_id
         WHERE f.id = ?'
    );
    $stmt->execute([(int) $_GET['guardado']]);
    $guardado = $stmt->fetch() ?: null;
}

$pctComisionVigente = (float) obtenerParametro('pct_comision_chofer', '0');
$pctComisionTexto = $pctComisionVigente == floor($pctComisionVigente)
    ? number_format($pctComisionVigente, 0)
    : number_format($pctComisionVigente, 2, ',', '.');

$scriptsPagina = [BASE_URL . '/assets/js/segmentado.js', BASE_URL . '/assets/js/fletes-nuevo.js'];
$tituloPagina = 'Nuevo flete';
require __DIR__ . '/../../includes/header.php';
?>

<h1>Nuevo flete</h1>

<?php if ($guardado): ?>
  <p class="exito">
    Flete guardado: <?= htmlspecialchars($guardado['patente']) ?> · <?= htmlspecialchars($guardado['chofer_nombre']) ?> ·
    <?= formatearImporte((float) $guardado['importe_bruto']) ?> · comisión <?= formatearImporte((float) $guardado['comision_chofer']) ?>
  </p>
<?php endif; ?>

<?php foreach ($errores as $error): ?>
  <p class="login-error"><?= htmlspecialchars($error) ?></p>
<?php endforeach; ?>

<?php if (!$camiones || !$choferes): ?>
  <p class="nota">Hace falta al menos un camión y un chofer activos para cargar un flete. Cargalos en <a href="<?= BASE_URL ?>/modulos/maestros/camiones.php">Maestros</a>.</p>
<?php else: ?>

<form method="post">
  <label>Camión</label>
  <div class="seg" data-input="camion_id">
    <?php foreach ($camiones as $camion): ?>
      <button type="button" data-value="<?= $camion['id'] ?>" class="<?= (string) $valores['camion_id'] === (string) $camion['id'] ? 'on' : '' ?>"><?= htmlspecialchars($camion['patente']) ?></button>
    <?php endforeach; ?>
  </div>
  <input type="hidden" name="camion_id" id="camion_id" value="<?= htmlspecialchars((string) $valores['camion_id']) ?>">

  <label>Chofer</label>
  <div class="seg" data-input="chofer_id">
    <?php foreach ($choferes as $chofer): ?>
      <button type="button" data-value="<?= $chofer['id'] ?>" class="<?= (string) $valores['chofer_id'] === (string) $chofer['id'] ? 'on' : '' ?>"><?= htmlspecialchars($chofer['nombre']) ?></button>
    <?php endforeach; ?>
  </div>
  <input type="hidden" name="chofer_id" id="chofer_id" value="<?= htmlspecialchars((string) $valores['chofer_id']) ?>">

  <div class="fila">
    <div>
      <label for="fecha">Fecha</label>
      <input class="campo-input" type="date" id="fecha" name="fecha" required
        value="<?= htmlspecialchars($valores['fecha']) ?>">
    </div>
    <div>
      <label for="cliente_id">Cliente</label>
      <select class="campo-input" id="cliente_id" name="cliente_id">
        <option value="">— Sin cliente —</option>
        <?php foreach ($clientes as $cliente): ?>
          <option value="<?= $cliente['id'] ?>" <?= (string) $valores['cliente_id'] === (string) $cliente['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cliente['razon_social']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <label for="destino">Destino</label>
  <input class="campo-input" type="text" id="destino" name="destino" maxlength="80" required
    value="<?= htmlspecialchars($valores['destino']) ?>">

  <div class="fila">
    <div>
      <label for="importe_bruto">Importe bruto</label>
      <input class="campo-input" type="number" id="importe_bruto" name="importe_bruto" min="0" step="0.01" inputmode="decimal" required
        value="<?= htmlspecialchars((string) $valores['importe_bruto']) ?>">
    </div>
    <div>
      <label for="viatico_adelanto">Viático adelantado</label>
      <input class="campo-input" type="number" id="viatico_adelanto" name="viatico_adelanto" min="0" step="0.01" inputmode="decimal"
        value="<?= htmlspecialchars((string) $valores['viatico_adelanto']) ?>">
    </div>
  </div>

  <input type="hidden" id="pctComisionVigente" value="<?= $pctComisionVigente ?>">
  <div class="auto">
    <div>
      <small id="comisionEtiqueta">Comisión chofer · <?= $pctComisionTexto ?>% del bruto</small>
      <strong id="comisionCalculada">$ 0,00</strong>
    </div>
    <span class="chip cartera">auto</span>
  </div>

  <button type="submit" name="accion" value="guardar" class="btn">Guardar flete</button>
  <button type="submit" name="accion" value="guardar_gastos" class="btn sec">Guardar y cargar gastos del viaje</button>
</form>

<p class="nota"><b>Camión y chofer son botones, no desplegables</b>: se eligen de un toque. La comisión se calcula sola con el % vigente y queda a la vista antes de guardar.</p>

<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
