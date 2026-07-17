<?php

require_once __DIR__ . '/../../includes/auth.php';
requerirRol(['admin']);
require_once __DIR__ . '/../../includes/funciones.php';

$pdo = obtenerConexion();
$errores = [];
$guardado = null;

$clientes = $pdo->query(
    'SELECT id, razon_social FROM clientes WHERE es_portal_pallets = 1 AND activo = 1 ORDER BY razon_social'
)->fetchAll();

$stockPorCliente = [];
$stmt = $pdo->query('SELECT * FROM v_pallets_stock');
foreach ($stmt->fetchAll() as $fila) {
    $stockPorCliente[(int) $fila['cliente_id']] = [
        'sanos'            => (int) $fila['sanos'],
        'rotos'            => (int) $fila['rotos'],
        'reacondicionados' => (int) $fila['reacondicionados'],
        'separadores'      => (int) $fila['separadores'],
    ];
}
foreach ($clientes as $cliente) {
    if (!isset($stockPorCliente[(int) $cliente['id']])) {
        $stockPorCliente[(int) $cliente['id']] = ['sanos' => 0, 'rotos' => 0, 'reacondicionados' => 0, 'separadores' => 0];
    }
}

$valores = [
    'tipo'              => 'recepcion',
    'cliente_id'        => $clientes[0]['id'] ?? '',
    'transporte_origen' => '',
    'transporte_cuit'   => '',
    'chofer_nombre'     => '',
    'chofer_dni'        => '',
    'hoja_ruta'         => '',
    'documentacion'     => '',
    'peajes'            => '',
    'sanos'             => '0',
    'rotos'             => '0',
    'reacondicionados'  => '0',
    'separadores'       => '0',
    'observaciones'     => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo             = ($_POST['tipo'] ?? '') === 'devolucion' ? 'devolucion' : 'recepcion';
    $clienteId        = (int) ($_POST['cliente_id'] ?? 0);
    $transporteOrigen = trim($_POST['transporte_origen'] ?? '');
    $transporteCuit   = trim($_POST['transporte_cuit'] ?? '');
    $choferNombre     = trim($_POST['chofer_nombre'] ?? '');
    $choferDni        = trim($_POST['chofer_dni'] ?? '');
    $hojaRuta         = trim($_POST['hoja_ruta'] ?? '');
    $documentacion    = trim($_POST['documentacion'] ?? '');
    $peajes           = trim($_POST['peajes'] ?? '');
    $sanos            = (int) ($_POST['sanos'] ?? 0);
    $rotos            = (int) ($_POST['rotos'] ?? 0);
    $reacondicionados = (int) ($_POST['reacondicionados'] ?? 0);
    $separadores      = (int) ($_POST['separadores'] ?? 0);
    $observaciones    = trim($_POST['observaciones'] ?? '');

    $valores = [
        'tipo'              => $tipo,
        'cliente_id'        => $clienteId ?: '',
        'transporte_origen' => $transporteOrigen,
        'transporte_cuit'   => $transporteCuit,
        'chofer_nombre'     => $choferNombre,
        'chofer_dni'        => $choferDni,
        'hoja_ruta'         => $hojaRuta,
        'documentacion'     => $documentacion,
        'peajes'            => $peajes,
        'sanos'             => (string) $sanos,
        'rotos'             => (string) $rotos,
        'reacondicionados'  => (string) $reacondicionados,
        'separadores'       => (string) $separadores,
        'observaciones'     => $observaciones,
    ];

    $clienteValido = null;
    foreach ($clientes as $c) {
        if ((int) $c['id'] === $clienteId) {
            $clienteValido = $c;
            break;
        }
    }

    if (!$clienteValido) {
        $errores[] = 'Elegí un cliente con acceso al portal de tarimas.';
    }
    if ($sanos < 0 || $rotos < 0 || $reacondicionados < 0 || $separadores < 0) {
        $errores[] = 'Las cantidades no pueden ser negativas.';
    }
    if ($sanos === 0 && $rotos === 0 && $reacondicionados === 0 && $separadores === 0) {
        $errores[] = 'Cargá al menos una cantidad mayor a 0.';
    }

    if (!$errores && $tipo === 'devolucion') {
        $stock = $stockPorCliente[$clienteId] ?? ['sanos' => 0, 'rotos' => 0, 'reacondicionados' => 0, 'separadores' => 0];
        if ($sanos > $stock['sanos']) {
            $errores[] = 'No hay ' . $stock['sanos'] . ' tarimas sanas disponibles para devolver.';
        }
        if ($rotos > $stock['rotos']) {
            $errores[] = 'No hay ' . $stock['rotos'] . ' tarimas rotas disponibles para devolver.';
        }
        if ($reacondicionados > $stock['reacondicionados']) {
            $errores[] = 'No hay ' . $stock['reacondicionados'] . ' tarimas reacondicionadas disponibles para devolver.';
        }
        if ($separadores > $stock['separadores']) {
            $errores[] = 'No hay ' . $stock['separadores'] . ' separadores disponibles para devolver.';
        }
    }

    if (!$errores) {
        $intentos = 0;
        $remitoId = null;

        while ($remitoId === null && $intentos < 3) {
            $intentos++;
            try {
                $pdo->beginTransaction();

                // Bloquea la fila de mayor numero para evitar que dos altas simultáneas
                // calculen el mismo próximo número; el UNIQUE de remitos.numero es el
                // resguardo final si igual llegaran a chocar (se reintenta abajo).
                $maxNumero = (int) $pdo->query('SELECT MAX(numero) FROM remitos FOR UPDATE')->fetchColumn();
                $numero    = $maxNumero + 1;

                $stmt = $pdo->prepare(
                    'INSERT INTO remitos (numero, tipo, fecha, cliente_id, transporte_origen, transporte_cuit, chofer_nombre, chofer_dni, hoja_ruta, documentacion, peajes, usuario_id)
                     VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $numero,
                    $tipo,
                    $clienteId,
                    $transporteOrigen ?: null,
                    $transporteCuit ?: null,
                    $choferNombre ?: null,
                    $choferDni ?: null,
                    $hojaRuta ?: null,
                    $documentacion ?: null,
                    $peajes ?: null,
                    usuarioActual()['id'],
                ]);
                $nuevoRemitoId = (int) $pdo->lastInsertId();

                $stmt = $pdo->prepare(
                    'INSERT INTO pallets_movimientos (remito_id, sanos, rotos, reacondicionados, separadores, observaciones)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$nuevoRemitoId, $sanos, $rotos, $reacondicionados, $separadores, $observaciones ?: null]);

                $pdo->commit();
                $remitoId = $nuevoRemitoId;
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($e->getCode() !== '23000' || $intentos >= 3) {
                    $errores[] = 'No se pudo guardar el remito.';
                    break;
                }
                // Duplicado de numero por carrera: reintenta con el próximo número.
            }
        }

        if ($remitoId !== null) {
            header('Location: nuevo.php?guardado=' . $remitoId);
            exit;
        }
    }
}

if (isset($_GET['guardado'])) {
    $stmt = $pdo->prepare(
        'SELECT r.*, cl.razon_social
         FROM remitos r
         JOIN clientes cl ON cl.id = r.cliente_id
         WHERE r.id = ?'
    );
    $stmt->execute([(int) $_GET['guardado']]);
    $guardado = $stmt->fetch() ?: null;
}

$activo        = 'nuevo';
$scriptsPagina = [BASE_URL . '/assets/js/segmentado.js', BASE_URL . '/assets/js/pallets-nuevo.js'];
$tituloPagina  = 'Nuevo remito';
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/tabs.php';
?>

<h1>Nuevo remito — Tarimas (pallets)</h1>

<?php if ($guardado): ?>
  <p class="exito">
    Remito Nº <?= str_pad((string) $guardado['numero'], 6, '0', STR_PAD_LEFT) ?> guardado
    (<?= $guardado['tipo'] === 'recepcion' ? 'recepción' : 'devolución' ?> — <?= htmlspecialchars($guardado['razon_social']) ?>).
    El PDF se arma en el próximo paso.
  </p>
<?php endif; ?>

<?php foreach ($errores as $error): ?>
  <p class="login-error"><?= htmlspecialchars($error) ?></p>
<?php endforeach; ?>

<?php if (!$clientes): ?>
  <p class="nota">No hay clientes con acceso al portal de tarimas. Marcá "es_portal_pallets" en <a href="<?= BASE_URL ?>/modulos/maestros/clientes.php">Maestros</a>.</p>
<?php else: ?>

<form method="post">
  <label>Tipo</label>
  <div class="seg" data-input="tipo">
    <button type="button" data-value="recepcion" class="<?= $valores['tipo'] === 'recepcion' ? 'on' : '' ?>">Recepción</button>
    <button type="button" data-value="devolucion" class="<?= $valores['tipo'] === 'devolucion' ? 'on' : '' ?>">Devolución</button>
  </div>
  <input type="hidden" name="tipo" id="tipo" value="<?= htmlspecialchars($valores['tipo']) ?>">

  <label for="cliente_id">Cliente</label>
  <select class="campo-input" id="cliente_id" name="cliente_id">
    <?php foreach ($clientes as $cliente): ?>
      <option value="<?= $cliente['id'] ?>" <?= (string) $valores['cliente_id'] === (string) $cliente['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cliente['razon_social']) ?></option>
    <?php endforeach; ?>
  </select>

  <div id="disponibleWrap" class="oculto">
    <div class="consumo">
      <div><small>Sanas disp.</small><b id="dispSanos">0</b></div>
      <div><small>Rotas disp.</small><b id="dispRotos">0</b></div>
      <div><small>Reac. disp.</small><b id="dispReacondicionados">0</b></div>
      <div><small>Separ. disp.</small><b id="dispSeparadores">0</b></div>
    </div>
  </div>

  <div class="fila3">
    <div>
      <label for="sanos">Tarimas sanas</label>
      <input class="campo-input" type="number" id="sanos" name="sanos" min="0" step="1" inputmode="numeric" value="<?= htmlspecialchars($valores['sanos']) ?>">
    </div>
    <div>
      <label for="rotos">Tarimas rotas</label>
      <input class="campo-input" type="number" id="rotos" name="rotos" min="0" step="1" inputmode="numeric" value="<?= htmlspecialchars($valores['rotos']) ?>">
    </div>
    <div>
      <label for="reacondicionados">Reacondicionadas</label>
      <input class="campo-input" type="number" id="reacondicionados" name="reacondicionados" min="0" step="1" inputmode="numeric" value="<?= htmlspecialchars($valores['reacondicionados']) ?>">
    </div>
  </div>

  <label for="separadores">Separadores</label>
  <input class="campo-input" type="number" id="separadores" name="separadores" min="0" step="1" inputmode="numeric" value="<?= htmlspecialchars($valores['separadores']) ?>">

  <label for="transporte_origen">Transporte que entrega</label>
  <input class="campo-input" type="text" id="transporte_origen" name="transporte_origen" maxlength="120" value="<?= htmlspecialchars($valores['transporte_origen']) ?>">

  <div class="fila">
    <div>
      <label for="transporte_cuit">CUIT transporte</label>
      <input class="campo-input" type="text" id="transporte_cuit" name="transporte_cuit" maxlength="15" value="<?= htmlspecialchars($valores['transporte_cuit']) ?>">
    </div>
    <div>
      <label for="hoja_ruta">Hoja de ruta</label>
      <input class="campo-input" type="text" id="hoja_ruta" name="hoja_ruta" maxlength="40" value="<?= htmlspecialchars($valores['hoja_ruta']) ?>">
    </div>
  </div>

  <div class="fila">
    <div>
      <label for="chofer_nombre">Chofer</label>
      <input class="campo-input" type="text" id="chofer_nombre" name="chofer_nombre" maxlength="80" value="<?= htmlspecialchars($valores['chofer_nombre']) ?>">
    </div>
    <div>
      <label for="chofer_dni">DNI chofer</label>
      <input class="campo-input" type="text" id="chofer_dni" name="chofer_dni" maxlength="15" value="<?= htmlspecialchars($valores['chofer_dni']) ?>">
    </div>
  </div>

  <div class="fila">
    <div>
      <label for="documentacion">Documentación</label>
      <input class="campo-input" type="text" id="documentacion" name="documentacion" maxlength="150" value="<?= htmlspecialchars($valores['documentacion']) ?>">
    </div>
    <div>
      <label for="peajes">Peajes</label>
      <input class="campo-input" type="text" id="peajes" name="peajes" maxlength="100" value="<?= htmlspecialchars($valores['peajes']) ?>">
    </div>
  </div>

  <label for="observaciones">Observaciones</label>
  <input class="campo-input" type="text" id="observaciones" name="observaciones" maxlength="200" value="<?= htmlspecialchars($valores['observaciones']) ?>">

  <button type="submit" class="btn">Guardar remito</button>
</form>

<script>
  window.STOCK_POR_CLIENTE = <?= json_encode($stockPorCliente) ?>;
</script>

<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
