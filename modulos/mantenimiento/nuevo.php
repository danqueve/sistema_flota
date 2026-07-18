<?php

require_once __DIR__ . '/../../includes/auth.php';
requerirRol(['admin']);
require_once __DIR__ . '/../../includes/funciones.php';

$pdo = obtenerConexion();
$errores = [];

$camiones = $pdo->query('SELECT id, patente, km_actual FROM camiones WHERE activo=1 ORDER BY patente')->fetchAll();
$tipos    = $pdo->query('SELECT id, nombre FROM tipos_service ORDER BY nombre')->fetchAll();
$cuentas  = $pdo->query('SELECT id, nombre FROM cuentas WHERE activo=1 ORDER BY tipo, nombre')->fetchAll();

$kmActualPorCamion = [];
foreach ($camiones as $camion) {
    $kmActualPorCamion[(int) $camion['id']] = $camion['km_actual'] !== null ? (int) $camion['km_actual'] : null;
}

$valores = [
    'camion_id'       => $camiones[0]['id'] ?? '',
    'tipo_service_id' => $tipos[0]['id'] ?? '',
    'fecha'           => date('Y-m-d'),
    'km'              => '',
    'taller'          => '',
    'costo'           => '',
    'observaciones'   => '',
    'cuenta_id'       => $cuentas[0]['id'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $camionId      = (int) ($_POST['camion_id'] ?? 0);
    $tipoServiceId = (int) ($_POST['tipo_service_id'] ?? 0);
    $fecha         = $_POST['fecha'] ?? '';
    $km            = $_POST['km'] !== '' ? (int) $_POST['km'] : null;
    $kmConfirmado  = isset($_POST['km_confirmado']);
    $taller        = trim($_POST['taller'] ?? '');
    $costo         = $_POST['costo'] !== '' ? (float) str_replace(',', '.', $_POST['costo']) : null;
    $observaciones = trim($_POST['observaciones'] ?? '');
    $generarEgreso = isset($_POST['generar_egreso']);
    $cuentaId      = ($_POST['cuenta_id'] ?? '') !== '' ? (int) $_POST['cuenta_id'] : null;

    $valores = [
        'camion_id'       => $camionId ?: '',
        'tipo_service_id' => $tipoServiceId ?: '',
        'fecha'           => $fecha,
        'km'              => $_POST['km'] ?? '',
        'taller'          => $taller,
        'costo'           => $_POST['costo'] ?? '',
        'observaciones'   => $observaciones,
        'cuenta_id'       => $cuentaId ?: '',
    ];

    if (!$camionId || !$tipoServiceId) {
        $errores[] = 'Elegí un camión y un tipo de service.';
    }
    if ($fecha === '' || strtotime($fecha) === false) {
        $errores[] = 'La fecha es obligatoria.';
    }

    $kmActual = $kmActualPorCamion[$camionId] ?? null;
    if ($km !== null && $km <= 0) {
        $errores[] = 'El km tiene que ser mayor a 0.';
    }
    if ($km !== null && $kmActual !== null && $km < $kmActual && !$kmConfirmado) {
        $errores[] = 'El km ingresado (' . number_format($km, 0, ',', '.') . ') es menor al actual del camión (' . number_format($kmActual, 0, ',', '.') . '). Si el odómetro fue reemplazado o hubo un error de tipeo previo, confirmalo abajo para continuar.';
    }

    if ($generarEgreso) {
        if ($costo === null || $costo <= 0) {
            $errores[] = 'Para generar el egreso en tesorería, cargá un costo mayor a 0.';
        }
        if (!$cuentaId) {
            $errores[] = 'Elegí de qué cuenta sale el pago del service.';
        }
    }

    if (!$errores) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'INSERT INTO services (camion_id, tipo_service_id, fecha, km, costo, taller, observaciones)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $camionId,
                $tipoServiceId,
                $fecha,
                $km,
                $costo,
                $taller ?: null,
                $observaciones ?: null,
            ]);
            $serviceId = (int) $pdo->lastInsertId();

            if ($km !== null) {
                $pdo->prepare('UPDATE camiones SET km_actual = ? WHERE id = ?')->execute([$km, $camionId]);
            }

            if ($generarEgreso) {
                $tipoNombre = '';
                foreach ($tipos as $t) {
                    if ((int) $t['id'] === $tipoServiceId) {
                        $tipoNombre = $t['nombre'];
                    }
                }
                $patente = '';
                foreach ($camiones as $c) {
                    if ((int) $c['id'] === $camionId) {
                        $patente = $c['patente'];
                    }
                }

                $categoriaId = obtenerCategoriaGastoPorNombre($pdo, 'Mantenimiento');
                $pdo->prepare(
                    'INSERT INTO movimientos_tesoreria (fecha, cuenta_id, tipo, categoria_id, importe, referencia_tipo, descripcion, usuario_id)
                     VALUES (?, ?, "egreso", ?, ?, "otro", ?, ?)'
                )->execute([
                    $fecha,
                    $cuentaId,
                    $categoriaId,
                    $costo,
                    'Service ' . $tipoNombre . ' — ' . $patente,
                    usuarioActual()['id'],
                ]);
            }

            $pdo->commit();
            header('Location: repuestos.php?service_id=' . $serviceId);
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errores[] = 'No se pudo guardar el service.';
        }
    }
}

$activo         = 'nuevo';
$scriptsPagina  = [BASE_URL . '/assets/js/segmentado.js', BASE_URL . '/assets/js/mantenimiento-nuevo.js'];
$tituloPagina   = 'Nuevo service';
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/tabs.php';
?>

<h1>Nuevo service</h1>

<?php foreach ($errores as $error): ?>
  <p class="login-error"><?= htmlspecialchars($error) ?></p>
<?php endforeach; ?>

<?php if (!$camiones || !$tipos): ?>
  <p class="nota">Hace falta al menos un camión activo y un tipo de service cargado (<a href="tipos.php">Tipos</a>).</p>
<?php else: ?>

<form method="post">
  <label>Camión</label>
  <div class="seg" data-input="camion_id">
    <?php foreach ($camiones as $camion): ?>
      <button type="button" data-value="<?= $camion['id'] ?>" class="<?= (string) $valores['camion_id'] === (string) $camion['id'] ? 'on' : '' ?>"><?= htmlspecialchars($camion['patente']) ?></button>
    <?php endforeach; ?>
  </div>
  <input type="hidden" name="camion_id" id="camion_id" value="<?= htmlspecialchars((string) $valores['camion_id']) ?>">

  <label>Tipo de service</label>
  <div class="seg" data-input="tipo_service_id">
    <?php foreach ($tipos as $tipo): ?>
      <button type="button" data-value="<?= $tipo['id'] ?>" class="<?= (string) $valores['tipo_service_id'] === (string) $tipo['id'] ? 'on' : '' ?>"><?= htmlspecialchars($tipo['nombre']) ?></button>
    <?php endforeach; ?>
  </div>
  <input type="hidden" name="tipo_service_id" id="tipo_service_id" value="<?= htmlspecialchars((string) $valores['tipo_service_id']) ?>">

  <div class="fila">
    <div>
      <label for="fecha">Fecha</label>
      <input class="campo-input" type="date" id="fecha" name="fecha" required value="<?= htmlspecialchars($valores['fecha']) ?>">
    </div>
    <div>
      <label for="km">Km</label>
      <input class="campo-input" type="number" id="km" name="km" min="0" step="1" inputmode="numeric"
        value="<?= htmlspecialchars((string) $valores['km']) ?>">
    </div>
  </div>

  <p class="nota" id="kmActualTexto"></p>

  <div id="kmConfirmadoWrap" class="oculto">
    <label class="campo">
      <span>Confirmo que el km es menor al actual (odómetro reemplazado o corrección de un error previo)</span>
      <input type="checkbox" id="km_confirmado" name="km_confirmado" value="1">
    </label>
  </div>

  <label for="taller">Taller (opcional)</label>
  <input class="campo-input" type="text" id="taller" name="taller" maxlength="80" value="<?= htmlspecialchars($valores['taller']) ?>">

  <label for="costo">Costo (opcional, se puede completar después de cargar los repuestos)</label>
  <input class="campo-input" type="number" id="costo" name="costo" min="0" step="0.01" inputmode="decimal"
    value="<?= htmlspecialchars((string) $valores['costo']) ?>">

  <label for="observaciones">Observaciones (opcional)</label>
  <input class="campo-input" type="text" id="observaciones" name="observaciones" maxlength="255" value="<?= htmlspecialchars($valores['observaciones']) ?>">

  <label class="campo">
    <span>Generar egreso en tesorería por este costo</span>
    <input type="checkbox" id="generar_egreso" name="generar_egreso" value="1">
  </label>

  <div id="cuentaWrap" class="oculto">
    <label>Cuenta</label>
    <div class="seg" data-input="cuenta_id">
      <?php foreach ($cuentas as $cuenta): ?>
        <button type="button" data-value="<?= $cuenta['id'] ?>" class="<?= (string) $valores['cuenta_id'] === (string) $cuenta['id'] ? 'on' : '' ?>"><?= htmlspecialchars($cuenta['nombre']) ?></button>
      <?php endforeach; ?>
    </div>
    <input type="hidden" name="cuenta_id" id="cuenta_id" value="<?= htmlspecialchars((string) $valores['cuenta_id']) ?>">
  </div>

  <button type="submit" class="btn">Guardar service</button>
</form>

<p class="nota">Después de guardar vas a poder cargar los repuestos usados desde el stock.</p>

<script>
  window.KM_ACTUAL_CAMION = <?= json_encode($kmActualPorCamion, JSON_NUMERIC_CHECK) ?>;
</script>

<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
