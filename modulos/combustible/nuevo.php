<?php

require_once __DIR__ . '/../../includes/auth.php';
requerirRol(['admin']);
require_once __DIR__ . '/../../includes/funciones.php';

$pdo = obtenerConexion();
$errores = [];
$guardado = null;

$camiones   = $pdo->query('SELECT id, patente, km_actual FROM camiones WHERE activo=1 ORDER BY patente')->fetchAll();
$estaciones = $pdo->query('SELECT id, nombre, tiene_cta_cte FROM estaciones WHERE activo=1 ORDER BY nombre')->fetchAll();

// Promedio histórico de consumo (L/100km) y último km registrado, por camión.
// El último km parte de cargas_combustible, pero nunca puede ser menor al
// km_actual del camión (que puede venir de un service o de una carga previa).
$statsCamion = [];
$stmt = $pdo->query(
    "SELECT camion_id, AVG(consumo_l100) AS promedio, MAX(km) AS ultimo_km
     FROM (
         SELECT camion_id, km,
                litros / (km - LAG(km) OVER (PARTITION BY camion_id ORDER BY km)) * 100 AS consumo_l100
         FROM cargas_combustible
         WHERE km IS NOT NULL
     ) t
     GROUP BY camion_id"
);
foreach ($stmt->fetchAll() as $fila) {
    $statsCamion[(int) $fila['camion_id']] = [
        'promedio'  => $fila['promedio'] !== null ? round((float) $fila['promedio'], 1) : null,
        'ultimo_km' => $fila['ultimo_km'] !== null ? (int) $fila['ultimo_km'] : null,
    ];
}
foreach ($camiones as $camion) {
    $id = (int) $camion['id'];
    if (!isset($statsCamion[$id])) {
        $statsCamion[$id] = ['promedio' => null, 'ultimo_km' => null];
    }
    if ($camion['km_actual'] !== null) {
        $statsCamion[$id]['ultimo_km'] = max($statsCamion[$id]['ultimo_km'] ?? 0, (int) $camion['km_actual']);
    }
}

// Acumulado del mes en curso por estación con cuenta corriente.
$acumuladoEstacion = [];
$stmt = $pdo->query(
    "SELECT estacion_id, SUM(importe) AS total
     FROM cargas_combustible
     WHERE modalidad = 'cta_cte' AND estacion_id IS NOT NULL
       AND DATE_FORMAT(fecha, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
     GROUP BY estacion_id"
);
foreach ($stmt->fetchAll() as $fila) {
    $acumuladoEstacion[(int) $fila['estacion_id']] = (float) $fila['total'];
}

$valores = [
    'camion_id'     => $camiones[0]['id'] ?? '',
    'estacion_id'   => $estaciones[0]['id'] ?? '',
    'estacion_otro' => '',
    'modalidad'     => !empty($estaciones[0]['tiene_cta_cte']) ? 'cta_cte' : 'contado',
    'litros'        => '',
    'importe'       => '',
    'km'            => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $camionId     = (int) ($_POST['camion_id'] ?? 0);
    $estacionId   = ($_POST['estacion_id'] ?? '') !== '' ? (int) $_POST['estacion_id'] : null;
    $estacionOtro = trim($_POST['estacion_otro'] ?? '');
    $modalidad    = ($_POST['modalidad'] ?? '') === 'contado' ? 'contado' : 'cta_cte';
    $litros       = $_POST['litros'] !== '' ? (float) str_replace(',', '.', $_POST['litros']) : null;
    $importe      = $_POST['importe'] !== '' ? (float) str_replace(',', '.', $_POST['importe']) : null;
    $km           = $_POST['km'] !== '' ? (int) $_POST['km'] : null;

    $valores = [
        'camion_id'     => $camionId ?: '',
        'estacion_id'   => $estacionId ?? '',
        'estacion_otro' => $estacionOtro,
        'modalidad'     => $modalidad,
        'litros'        => $_POST['litros'] ?? '',
        'importe'       => $_POST['importe'] ?? '',
        'km'            => $_POST['km'] ?? '',
    ];

    if (!$camionId) {
        $errores[] = 'Elegí un camión.';
    }
    if (!$estacionId && $estacionOtro === '') {
        $errores[] = 'Elegí una estación o escribí el nombre si es una eventual.';
    }
    if ($litros === null || $litros <= 0) {
        $errores[] = 'Los litros tienen que ser mayores a 0.';
    }
    if ($importe === null || $importe <= 0) {
        $errores[] = 'El importe tiene que ser mayor a 0.';
    }
    if ($km === null || $km <= 0) {
        $errores[] = 'El km actual es obligatorio.';
    }

    $ultimoKm = $statsCamion[$camionId]['ultimo_km'] ?? null;
    if (!$errores && $ultimoKm !== null && $km <= $ultimoKm) {
        $errores[] = 'El km ingresado (' . number_format($km, 0, ',', '.') . ') tiene que ser mayor al último registrado para ese camión (' . number_format($ultimoKm, 0, ',', '.') . ').';
    }

    if (!$errores) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'INSERT INTO cargas_combustible (fecha, camion_id, estacion_id, estacion_otro, litros, importe, km, modalidad)
                 VALUES (CURDATE(), ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $camionId,
                $estacionId,
                $estacionId ? null : ($estacionOtro ?: null),
                $litros,
                $importe,
                $km,
                $modalidad,
            ]);
            $cargaId = (int) $pdo->lastInsertId();

            $stmt = $pdo->prepare('UPDATE camiones SET km_actual = ? WHERE id = ? AND (km_actual IS NULL OR km_actual < ?)');
            $stmt->execute([$km, $camionId, $km]);

            $pdo->commit();

            header('Location: nuevo.php?guardado=' . $cargaId);
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errores[] = 'No se pudo guardar la carga.';
        }
    }
}

if (isset($_GET['guardado'])) {
    $stmt = $pdo->prepare(
        'SELECT cc.*, c.patente, e.nombre AS estacion_nombre
         FROM cargas_combustible cc
         JOIN camiones c ON c.id = cc.camion_id
         LEFT JOIN estaciones e ON e.id = cc.estacion_id
         WHERE cc.id = ?'
    );
    $stmt->execute([(int) $_GET['guardado']]);
    $guardado = $stmt->fetch() ?: null;
}

$scriptsPagina = [BASE_URL . '/assets/js/segmentado.js', BASE_URL . '/assets/js/combustible-nuevo.js'];
$tituloPagina = 'Carga de combustible';
require __DIR__ . '/../../includes/header.php';
?>

<h1>Carga de combustible</h1>

<?php if ($guardado): ?>
  <p class="exito">
    Carga guardada: <?= htmlspecialchars($guardado['patente']) ?> ·
    <?= htmlspecialchars($guardado['estacion_nombre'] ?? $guardado['estacion_otro'] ?? 'estación eventual') ?> ·
    <?= number_format((float) $guardado['litros'], 2, ',', '.') ?> L · <?= formatearImporte((float) $guardado['importe']) ?> ·
    km <?= number_format((float) $guardado['km'], 0, ',', '.') ?>
  </p>
<?php endif; ?>

<?php foreach ($errores as $error): ?>
  <p class="login-error"><?= htmlspecialchars($error) ?></p>
<?php endforeach; ?>

<?php if (!$camiones || !$estaciones): ?>
  <p class="nota">Hace falta al menos un camión y una estación activos. Cargalos en <a href="<?= BASE_URL ?>/modulos/maestros/camiones.php">Maestros</a>.</p>
<?php else: ?>

<form method="post">
  <label>Camión</label>
  <div class="seg" data-input="camion_id">
    <?php foreach ($camiones as $camion): ?>
      <button type="button" data-value="<?= $camion['id'] ?>" class="<?= (string) $valores['camion_id'] === (string) $camion['id'] ? 'on' : '' ?>"><?= htmlspecialchars($camion['patente']) ?></button>
    <?php endforeach; ?>
  </div>
  <input type="hidden" name="camion_id" id="camion_id" value="<?= htmlspecialchars((string) $valores['camion_id']) ?>">

  <label>Estación</label>
  <div class="seg" data-input="estacion_id">
    <?php foreach ($estaciones as $estacion): ?>
      <button type="button" data-value="<?= $estacion['id'] ?>" class="<?= (string) $valores['estacion_id'] === (string) $estacion['id'] ? 'on' : '' ?>"><?= htmlspecialchars($estacion['nombre']) ?></button>
    <?php endforeach; ?>
    <button type="button" data-value="" class="<?= $valores['estacion_id'] === '' ? 'on' : '' ?>">Otra…</button>
  </div>
  <input type="hidden" name="estacion_id" id="estacion_id" value="<?= htmlspecialchars((string) $valores['estacion_id']) ?>">

  <div id="estacionOtraWrap" class="<?= $valores['estacion_id'] === '' ? '' : 'oculto' ?>">
    <label for="estacion_otro">Nombre de la estación</label>
    <input class="campo-input" type="text" id="estacion_otro" name="estacion_otro" maxlength="80"
      value="<?= htmlspecialchars($valores['estacion_otro']) ?>">
  </div>

  <label>Modalidad</label>
  <div class="seg" data-input="modalidad">
    <button type="button" data-value="cta_cte" class="<?= $valores['modalidad'] === 'cta_cte' ? 'on' : '' ?>">Cta. corriente</button>
    <button type="button" data-value="contado" class="<?= $valores['modalidad'] === 'contado' ? 'on' : '' ?>">Pago directo</button>
  </div>
  <input type="hidden" name="modalidad" id="modalidad" value="<?= htmlspecialchars($valores['modalidad']) ?>">

  <div class="fila3">
    <div>
      <label for="litros">Litros</label>
      <input class="campo-input" type="number" id="litros" name="litros" min="0" step="0.01" inputmode="decimal" required
        value="<?= htmlspecialchars((string) $valores['litros']) ?>">
    </div>
    <div>
      <label for="importe">Importe</label>
      <input class="campo-input" type="number" id="importe" name="importe" min="0" step="0.01" inputmode="decimal" required
        value="<?= htmlspecialchars((string) $valores['importe']) ?>">
    </div>
    <div>
      <label for="km">Km actual</label>
      <input class="campo-input" type="number" id="km" name="km" min="0" step="1" inputmode="numeric" required
        value="<?= htmlspecialchars((string) $valores['km']) ?>">
    </div>
  </div>

  <div class="consumo oculto" id="consumoCaja">
    <div><small>Este tramo</small><b id="consumoTramo"></b></div>
    <div id="consumoPromedioCaja"><small>Promedio camión</small><b id="consumoPromedio"></b></div>
  </div>

  <button type="submit" class="btn">Guardar carga</button>
</form>

<div class="totalbar oculto" id="totalBarCtaCte">
  <span id="totalBarTexto"></span>
  <b id="totalBarMonto"></b>
</div>

<p class="nota"><b>El control antirrobo pasa acá</b>: al guardar el km, el sistema compara el consumo del tramo contra el promedio histórico del camión y avisa en el momento si se desvía.</p>

<a href="resumenes.php" class="btn sec">Resúmenes de estación</a>

<script>
  window.STATS_CAMION = <?= json_encode($statsCamion, JSON_NUMERIC_CHECK) ?>;
  window.ACUMULADO_ESTACION = <?= json_encode($acumuladoEstacion, JSON_NUMERIC_CHECK) ?>;
  window.ESTACIONES = <?= json_encode($estaciones) ?>;
  window.NOMBRE_MES_ACTUAL = <?= json_encode(nombreMes((int) date('n'))) ?>;
</script>

<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
