<?php

require_once __DIR__ . '/../../includes/auth.php';
requerirRol(['admin']);
require_once __DIR__ . '/../../includes/funciones.php';

$pdo = obtenerConexion();
$errores = [];
$guardado = null;

$clientes = $pdo->query('SELECT id, razon_social FROM clientes WHERE activo=1 ORDER BY razon_social')->fetchAll();

// Fletes pendientes de cobro, agrupados por cliente, para vincular el cheque (opcional).
$fletesPorCliente = [];
$stmt = $pdo->query(
    "SELECT id, cliente_id, fecha, destino, importe_bruto
     FROM fletes
     WHERE estado_cobro = 'pendiente' AND cliente_id IS NOT NULL
     ORDER BY fecha DESC"
);
foreach ($stmt->fetchAll() as $fila) {
    $fletesPorCliente[(int) $fila['cliente_id']][] = [
        'id'    => (int) $fila['id'],
        'label' => '#' . $fila['id'] . ' · ' . formatearFecha($fila['fecha']) . ' · ' . $fila['destino'] . ' · ' . formatearImporte((float) $fila['importe_bruto']),
    ];
}

$valores = [
    'formato'        => 'fisico',
    'numero'         => '',
    'banco_librador' => '',
    'cliente_id'     => $clientes[0]['id'] ?? '',
    'flete_id'       => '',
    'importe'        => '',
    'fecha_pago'     => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formato       = ($_POST['formato'] ?? '') === 'echeq' ? 'echeq' : 'fisico';
    $numero        = trim($_POST['numero'] ?? '');
    $bancoLibrador = trim($_POST['banco_librador'] ?? '');
    $clienteId     = (int) ($_POST['cliente_id'] ?? 0);
    $fleteId       = ($_POST['flete_id'] ?? '') !== '' ? (int) $_POST['flete_id'] : null;
    $importe       = $_POST['importe'] !== '' ? (float) str_replace(',', '.', $_POST['importe']) : null;
    $fechaPago     = $_POST['fecha_pago'] ?? '';

    $valores = [
        'formato'        => $formato,
        'numero'         => $numero,
        'banco_librador' => $bancoLibrador,
        'cliente_id'     => $clienteId ?: '',
        'flete_id'       => $fleteId ?? '',
        'importe'        => $_POST['importe'] ?? '',
        'fecha_pago'     => $fechaPago,
    ];

    if ($numero === '') {
        $errores[] = 'El número de cheque es obligatorio.';
    }
    if ($bancoLibrador === '') {
        $errores[] = 'El banco librador es obligatorio.';
    }
    if (!$clienteId) {
        $errores[] = 'Elegí un cliente.';
    }
    if ($importe === null || $importe <= 0) {
        $errores[] = 'El importe tiene que ser mayor a 0.';
    }
    if ($fechaPago === '') {
        $errores[] = 'La fecha de cobro es obligatoria.';
    }

    if (!$errores && $fleteId) {
        $valido = false;
        foreach ($fletesPorCliente[$clienteId] ?? [] as $f) {
            if ($f['id'] === $fleteId) {
                $valido = true;
                break;
            }
        }
        if (!$valido) {
            $errores[] = 'El flete elegido no corresponde a ese cliente o ya no está pendiente de cobro.';
        }
    }

    if (!$errores) {
        $stmt = $pdo->prepare(
            'INSERT INTO cheques (tipo, formato, numero, banco_librador, cliente_id, flete_id, importe, fecha_emision, fecha_pago, estado)
             VALUES ("recibido", ?, ?, ?, ?, ?, ?, CURDATE(), ?, "en_cartera")'
        );
        $stmt->execute([$formato, $numero, $bancoLibrador, $clienteId, $fleteId, $importe, $fechaPago]);
        $chequeId = (int) $pdo->lastInsertId();

        header('Location: nuevo.php?guardado=' . $chequeId);
        exit;
    }
}

if (isset($_GET['guardado'])) {
    $stmt = $pdo->prepare(
        'SELECT ch.*, cl.razon_social FROM cheques ch LEFT JOIN clientes cl ON cl.id = ch.cliente_id WHERE ch.id = ?'
    );
    $stmt->execute([(int) $_GET['guardado']]);
    $guardado = $stmt->fetch() ?: null;
}

$activo        = 'nuevo';
$scriptsPagina = [BASE_URL . '/assets/js/segmentado.js', BASE_URL . '/assets/js/cheques-nuevo.js'];
$tituloPagina  = 'Nuevo cheque recibido';
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/tabs.php';
?>

<h1>Nuevo cheque recibido</h1>

<?php if ($guardado): ?>
  <p class="exito">
    Cheque guardado: <?= htmlspecialchars($guardado['banco_librador']) ?> · <?= htmlspecialchars($guardado['numero']) ?> ·
    <?= formatearImporte((float) $guardado['importe']) ?> · <?= htmlspecialchars($guardado['razon_social'] ?? 'sin cliente') ?>
  </p>
<?php endif; ?>

<?php foreach ($errores as $error): ?>
  <p class="login-error"><?= htmlspecialchars($error) ?></p>
<?php endforeach; ?>

<?php if (!$clientes): ?>
  <p class="nota">Hace falta al menos un cliente activo. Cargalo en <a href="<?= BASE_URL ?>/modulos/maestros/clientes.php">Maestros</a>.</p>
<?php else: ?>

<form method="post">
  <label>Formato</label>
  <div class="seg" data-input="formato">
    <button type="button" data-value="fisico" class="<?= $valores['formato'] === 'fisico' ? 'on' : '' ?>">Físico</button>
    <button type="button" data-value="echeq" class="<?= $valores['formato'] === 'echeq' ? 'on' : '' ?>">ECHEQ</button>
  </div>
  <input type="hidden" name="formato" id="formato" value="<?= htmlspecialchars($valores['formato']) ?>">

  <div class="fila">
    <div>
      <label for="numero">N° cheque</label>
      <input class="campo-input" type="text" id="numero" name="numero" maxlength="30" required
        value="<?= htmlspecialchars($valores['numero']) ?>">
    </div>
    <div>
      <label for="banco_librador">Banco librador</label>
      <input class="campo-input" type="text" id="banco_librador" name="banco_librador" maxlength="80" required
        value="<?= htmlspecialchars($valores['banco_librador']) ?>">
    </div>
  </div>

  <label for="cliente_id">Cliente</label>
  <select class="campo-input" id="cliente_id" name="cliente_id" required>
    <?php foreach ($clientes as $cliente): ?>
      <option value="<?= $cliente['id'] ?>" <?= (string) $valores['cliente_id'] === (string) $cliente['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cliente['razon_social']) ?></option>
    <?php endforeach; ?>
  </select>

  <div id="fleteWrap">
    <label>Flete (opcional)</label>
    <div class="seg" id="fleteSeg"></div>
  </div>
  <input type="hidden" name="flete_id" id="flete_id" value="<?= htmlspecialchars((string) $valores['flete_id']) ?>">

  <div class="fila">
    <div>
      <label for="importe">Importe</label>
      <input class="campo-input" type="number" id="importe" name="importe" min="0" step="0.01" inputmode="decimal" required
        value="<?= htmlspecialchars((string) $valores['importe']) ?>">
    </div>
    <div>
      <label for="fecha_pago">Fecha de cobro</label>
      <input class="campo-input" type="date" id="fecha_pago" name="fecha_pago" required
        value="<?= htmlspecialchars($valores['fecha_pago']) ?>">
    </div>
  </div>

  <div class="auto">
    <div><small>Queda en estado</small><strong>EN CARTERA</strong></div>
    <span class="chip cartera" id="diasCobro"></span>
  </div>

  <button type="submit" class="btn">Guardar cheque</button>
</form>

<p class="nota"><b>Un solo formulario, campos mínimos.</b> El estado inicial lo pone el sistema; los días al cobro se calculan solos. Vender, depositar o endosar se hace después, desde la cartera, con un toque.</p>

<script>
  window.FLETES_POR_CLIENTE = <?= json_encode($fletesPorCliente) ?>;
</script>

<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
