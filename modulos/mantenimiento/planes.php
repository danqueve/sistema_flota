<?php

require_once __DIR__ . '/../../includes/auth.php';
requerirRol(['admin']);
require_once __DIR__ . '/../../includes/funciones.php';

$pdo = obtenerConexion();
$errores = [];

$camiones = $pdo->query('SELECT id, patente FROM camiones WHERE activo=1 ORDER BY patente')->fetchAll();
$tipos    = $pdo->query('SELECT id, nombre FROM tipos_service ORDER BY nombre')->fetchAll();

// Último service por camión+tipo (fecha y km), calculado en PHP: el volumen
// de esta tabla es bajo y evita subconsultas correlacionadas complejas.
$ultimoServicio = [];
$stmt = $pdo->query('SELECT camion_id, tipo_service_id, fecha, km FROM services ORDER BY fecha ASC, id ASC');
foreach ($stmt->fetchAll() as $s) {
    $ultimoServicio[$s['camion_id'] . '_' . $s['tipo_service_id']] = ['fecha' => $s['fecha'], 'km' => $s['km']];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar') {
    $id             = ($_POST['id'] ?? '') !== '' ? (int) $_POST['id'] : null;
    $camionId       = (int) ($_POST['camion_id'] ?? 0);
    $tipoServiceId  = (int) ($_POST['tipo_service_id'] ?? 0);
    $intervaloKm    = $_POST['intervalo_km'] !== '' ? (int) $_POST['intervalo_km'] : null;
    $intervaloMeses = $_POST['intervalo_meses'] !== '' ? (int) $_POST['intervalo_meses'] : null;
    $kmPartida      = $_POST['km_partida'] !== '' ? (int) $_POST['km_partida'] : null;
    $fechaPartida   = trim($_POST['fecha_partida'] ?? '');

    if (!$camionId || !$tipoServiceId) {
        $errores[] = 'Elegí un camión y un tipo de service.';
    }
    if (!$intervaloKm && !$intervaloMeses) {
        $errores[] = 'Cargá un intervalo en km, en meses, o ambos.';
    }

    $tieneHistorial = isset($ultimoServicio[$camionId . '_' . $tipoServiceId]);

    if (!$id && !$tieneHistorial) {
        if (!$kmPartida || $kmPartida <= 0) {
            $errores[] = 'Este camión no tiene service registrado para este tipo: indicá el km del punto de partida.';
        }
        if ($fechaPartida === '' || strtotime($fechaPartida) === false) {
            $errores[] = 'Indicá la fecha del punto de partida (último service conocido, aunque sea aproximado).';
        }
    }

    if (!$errores) {
        try {
            $pdo->beginTransaction();

            if (!$id && !$tieneHistorial) {
                $pdo->prepare('INSERT INTO services (camion_id, tipo_service_id, fecha, km) VALUES (?, ?, ?, ?)')
                    ->execute([$camionId, $tipoServiceId, $fechaPartida, $kmPartida]);
            }

            if ($id) {
                $pdo->prepare('UPDATE planes_mantenimiento SET intervalo_km=?, intervalo_meses=? WHERE id=?')
                    ->execute([$intervaloKm, $intervaloMeses, $id]);
            } else {
                $pdo->prepare('INSERT INTO planes_mantenimiento (camion_id, tipo_service_id, intervalo_km, intervalo_meses) VALUES (?, ?, ?, ?)')
                    ->execute([$camionId, $tipoServiceId, $intervaloKm, $intervaloMeses]);
            }

            $pdo->commit();
            header('Location: planes.php');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errores[] = $e->getCode() === '23000'
                ? 'Ya existe un plan para ese camión y ese tipo de service. Editalo desde la lista de abajo.'
                : 'No se pudo guardar el plan.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar') {
    $pdo->prepare('DELETE FROM planes_mantenimiento WHERE id=?')->execute([(int) $_POST['id']]);
    header('Location: planes.php');
    exit;
}

$registroEditar = null;
if (isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM planes_mantenimiento WHERE id=?');
    $stmt->execute([(int) $_GET['id']]);
    $registroEditar = $stmt->fetch() ?: null;
}

$planes = $pdo->query(
    "SELECT pm.*, c.patente, ts.nombre AS tipo_nombre
     FROM planes_mantenimiento pm
     JOIN camiones c ON c.id = pm.camion_id
     JOIN tipos_service ts ON ts.id = pm.tipo_service_id
     ORDER BY c.patente, ts.nombre"
)->fetchAll();

$planesPorCamion = [];
foreach ($planes as $plan) {
    $planesPorCamion[$plan['patente']][] = $plan;
}

$activo         = 'planes';
$scriptsPagina  = [BASE_URL . '/assets/js/segmentado.js', BASE_URL . '/assets/js/mantenimiento-planes.js'];
$tituloPagina   = 'Planes de mantenimiento';
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/tabs.php';
?>

<h1><?= $registroEditar ? 'Editar plan de mantenimiento' : 'Nuevo plan de mantenimiento' ?></h1>

<?php foreach ($errores as $error): ?>
  <p class="login-error"><?= htmlspecialchars($error) ?></p>
<?php endforeach; ?>

<?php if (!$camiones || !$tipos): ?>
  <p class="nota">Hace falta al menos un camión activo y un tipo de service cargado (<a href="tipos.php">Tipos</a>).</p>
<?php else: ?>

<form method="post">
  <input type="hidden" name="accion" value="guardar">
  <input type="hidden" name="id" value="<?= $registroEditar['id'] ?? '' ?>">

  <?php if ($registroEditar): ?>
    <?php
    $patenteEditar = '';
    $tipoEditar    = '';
    foreach ($camiones as $c) {
        if ((int) $c['id'] === (int) $registroEditar['camion_id']) {
            $patenteEditar = $c['patente'];
        }
    }
    foreach ($tipos as $t) {
        if ((int) $t['id'] === (int) $registroEditar['tipo_service_id']) {
            $tipoEditar = $t['nombre'];
        }
    }
    ?>
    <p><b><?= htmlspecialchars($patenteEditar) ?></b> · <?= htmlspecialchars($tipoEditar) ?></p>
    <input type="hidden" name="camion_id" value="<?= $registroEditar['camion_id'] ?>">
    <input type="hidden" name="tipo_service_id" value="<?= $registroEditar['tipo_service_id'] ?>">
  <?php else: ?>
    <label>Camión</label>
    <div class="seg" data-input="camion_id">
      <?php foreach ($camiones as $camion): ?>
        <button type="button" data-value="<?= $camion['id'] ?>"><?= htmlspecialchars($camion['patente']) ?></button>
      <?php endforeach; ?>
    </div>
    <input type="hidden" name="camion_id" id="camion_id" value="">

    <label>Tipo de service</label>
    <div class="seg" data-input="tipo_service_id">
      <?php foreach ($tipos as $tipo): ?>
        <button type="button" data-value="<?= $tipo['id'] ?>"><?= htmlspecialchars($tipo['nombre']) ?></button>
      <?php endforeach; ?>
    </div>
    <input type="hidden" name="tipo_service_id" id="tipo_service_id" value="">
  <?php endif; ?>

  <div class="fila3">
    <div>
      <label for="intervalo_km">Intervalo (km)</label>
      <input class="campo-input" type="number" id="intervalo_km" name="intervalo_km" min="0" step="1" inputmode="numeric"
        value="<?= htmlspecialchars((string) ($registroEditar['intervalo_km'] ?? '')) ?>">
    </div>
    <div>
      <label for="intervalo_meses">Intervalo (meses)</label>
      <input class="campo-input" type="number" id="intervalo_meses" name="intervalo_meses" min="0" step="1" inputmode="numeric"
        value="<?= htmlspecialchars((string) ($registroEditar['intervalo_meses'] ?? '')) ?>">
    </div>
  </div>

  <?php if (!$registroEditar): ?>
    <div class="auto oculto" id="autoHistorial">
      <div>
        <small>Último service registrado</small>
        <strong id="autoHistorialTexto"></strong>
      </div>
      <span class="chip cartera">auto</span>
    </div>

    <div id="puntoPartidaWrap" class="oculto">
      <p class="nota">Este camión todavía no tiene service registrado para este tipo: indicá el punto de partida para poder calcular el próximo vencimiento.</p>
      <div class="fila3">
        <div>
          <label for="km_partida">Km del último service conocido</label>
          <input class="campo-input" type="number" id="km_partida" name="km_partida" min="0" step="1" inputmode="numeric" value="">
        </div>
        <div>
          <label for="fecha_partida">Fecha aproximada</label>
          <input class="campo-input" type="date" id="fecha_partida" name="fecha_partida" value="">
        </div>
      </div>
    </div>
  <?php endif; ?>

  <button type="submit" class="btn">Guardar plan</button>
  <?php if ($registroEditar): ?>
    <a href="planes.php" class="btn sec">Cancelar edición</a>
  <?php endif; ?>
</form>

<h1 class="seccion">Planes por camión</h1>

<?php if (!$planes): ?>
  <p class="nota">Todavía no hay planes de mantenimiento cargados.</p>
<?php endif; ?>

<?php foreach ($planesPorCamion as $patente => $planesCamion): ?>
  <div class="totalbar">
    <span><?= htmlspecialchars($patente) ?></span>
  </div>
  <?php foreach ($planesCamion as $plan): ?>
    <?php
    $ultimo = $ultimoServicio[$plan['camion_id'] . '_' . $plan['tipo_service_id']] ?? null;
    $partes = [];
    if ($plan['intervalo_km']) {
        $partes[] = number_format((float) $plan['intervalo_km'], 0, ',', '.') . ' km';
    }
    if ($plan['intervalo_meses']) {
        $partes[] = $plan['intervalo_meses'] . ' meses';
    }
    ?>
    <div class="item">
      <div class="l1"><span class="num"><?= htmlspecialchars($plan['tipo_nombre']) ?></span></div>
      <div class="l2">
        Cada <?= implode(' o ', $partes) ?: 'sin intervalo' ?>
        <?php if ($ultimo): ?>
          · último: <?= formatearFecha($ultimo['fecha']) ?><?= $ultimo['km'] ? ' · km ' . number_format((float) $ultimo['km'], 0, ',', '.') : '' ?>
        <?php endif; ?>
      </div>
      <div class="acciones">
        <a href="planes.php?id=<?= $plan['id'] ?>">Editar</a>
        <form method="post" onsubmit="return confirm('¿Eliminar este plan?');">
          <input type="hidden" name="accion" value="eliminar">
          <input type="hidden" name="id" value="<?= $plan['id'] ?>">
          <button type="submit">Eliminar</button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
<?php endforeach; ?>

<script>
  window.ULTIMO_SERVICIO = <?= json_encode($ultimoServicio, JSON_NUMERIC_CHECK) ?>;
</script>

<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
