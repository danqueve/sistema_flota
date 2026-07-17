<?php

require_once __DIR__ . '/../../includes/auth.php';
requerirRol(['admin']);
require_once __DIR__ . '/../../includes/funciones.php';

$pdo = obtenerConexion();
$errores = [];

$choferes = $pdo->query('SELECT id, nombre FROM choferes ORDER BY nombre')->fetchAll();

$choferId = (int) ($_GET['chofer_id'] ?? $_POST['chofer_id'] ?? ($choferes[0]['id'] ?? 0));
$periodo  = $_GET['periodo'] ?? $_POST['periodo'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $periodo)) {
    $periodo = date('Y-m');
}

function calcularResumenPendiente(PDO $pdo, int $choferId, string $periodo): array
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS cantidad, COALESCE(SUM(comision_chofer),0) AS total_comisiones, COALESCE(SUM(viatico_adelanto),0) AS viaticos_adelantados
         FROM fletes
         WHERE chofer_id = ? AND DATE_FORMAT(fecha, "%Y-%m") = ? AND liquidacion_id IS NULL'
    );
    $stmt->execute([$choferId, $periodo]);
    $resumenFletes = $stmt->fetch();

    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(gv.importe),0) AS gastos_reales
         FROM gastos_viaje gv
         JOIN fletes f ON f.id = gv.flete_id
         WHERE f.chofer_id = ? AND DATE_FORMAT(f.fecha, "%Y-%m") = ? AND f.liquidacion_id IS NULL'
    );
    $stmt->execute([$choferId, $periodo]);
    $gastosReales = (float) $stmt->fetchColumn();

    $totalComisiones     = (float) $resumenFletes['total_comisiones'];
    $viaticosAdelantados = (float) $resumenFletes['viaticos_adelantados'];
    $ajusteViaticos      = max($gastosReales - $viaticosAdelantados, 0);

    return [
        'cantidad'             => (int) $resumenFletes['cantidad'],
        'total_comisiones'     => $totalComisiones,
        'viaticos_adelantados' => $viaticosAdelantados,
        'gastos_reales'        => $gastosReales,
        'ajuste_viaticos'      => $ajusteViaticos,
        'total_pagar'          => $totalComisiones + $ajusteViaticos,
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cerrar') {
    $resumen = calcularResumenPendiente($pdo, $choferId, $periodo);

    if ($resumen['cantidad'] === 0) {
        $errores[] = 'No hay fletes pendientes de liquidar para ese chofer en ese período.';
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'INSERT INTO liquidaciones (chofer_id, periodo, total_comisiones, viaticos_adelantados, gastos_reales, ajuste_viaticos, total_pagar, estado, fecha_cierre)
                 VALUES (?, ?, ?, ?, ?, ?, ?, "cerrada", CURDATE())'
            );
            $stmt->execute([
                $choferId,
                $periodo,
                $resumen['total_comisiones'],
                $resumen['viaticos_adelantados'],
                $resumen['gastos_reales'],
                $resumen['ajuste_viaticos'],
                $resumen['total_pagar'],
            ]);
            $liquidacionId = (int) $pdo->lastInsertId();

            $stmt = $pdo->prepare(
                'UPDATE fletes SET liquidacion_id = ? WHERE chofer_id = ? AND DATE_FORMAT(fecha, "%Y-%m") = ? AND liquidacion_id IS NULL'
            );
            $stmt->execute([$liquidacionId, $choferId, $periodo]);

            $pdo->commit();

            header('Location: nueva.php?chofer_id=' . $choferId . '&periodo=' . $periodo);
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errores[] = $e->getCode() === '23000'
                ? 'Ya existe una liquidación cerrada para ese chofer en ese período.'
                : 'No se pudo cerrar la liquidación.';
        }
    }
}

$stmt = $pdo->prepare('SELECT * FROM liquidaciones WHERE chofer_id = ? AND periodo = ?');
$stmt->execute([$choferId, $periodo]);
$liquidacion = $stmt->fetch() ?: null;

$resumen = $liquidacion ? null : calcularResumenPendiente($pdo, $choferId, $periodo);
$datos   = $liquidacion ?: $resumen;

$choferNombre = '';
foreach ($choferes as $c) {
    if ((int) $c['id'] === $choferId) {
        $choferNombre = $c['nombre'];
        break;
    }
}

[$anioPeriodo, $mesPeriodo] = array_map('intval', explode('-', $periodo));
$periodoTexto = ucfirst(nombreMes($mesPeriodo)) . ' ' . $anioPeriodo;

$scriptsPagina = [BASE_URL . '/assets/js/segmentado.js', BASE_URL . '/assets/js/filtros-auto.js'];
$tituloPagina  = 'Liquidación de chofer';
require __DIR__ . '/../../includes/header.php';
?>

<h1>Liquidación de chofer</h1>

<form method="get" id="formFiltros" class="no-print">
  <label>Chofer</label>
  <div class="seg" data-input="chofer_id">
    <?php foreach ($choferes as $chofer): ?>
      <button type="button" data-value="<?= $chofer['id'] ?>" class="<?= $choferId === (int) $chofer['id'] ? 'on' : '' ?>"><?= htmlspecialchars($chofer['nombre']) ?></button>
    <?php endforeach; ?>
  </div>
  <input type="hidden" name="chofer_id" id="chofer_id" class="filtro-auto" value="<?= $choferId ?>">

  <label for="periodo">Período</label>
  <input class="campo-input filtro-auto" type="month" id="periodo" name="periodo" value="<?= htmlspecialchars($periodo) ?>">
</form>

<?php foreach ($errores as $error): ?>
  <p class="login-error no-print"><?= htmlspecialchars($error) ?></p>
<?php endforeach; ?>

<h1 class="seccion"><?= htmlspecialchars($choferNombre) ?> · <?= htmlspecialchars($periodoTexto) ?></h1>

<?php if ($liquidacion): ?>
  <p><span class="chip ok">cerrada</span> el <?= formatearFecha($liquidacion['fecha_cierre']) ?></p>
<?php else: ?>
  <p class="nota no-print">Vista previa — todavía no se cerró. Fletes pendientes de liquidar en el período: <?= $resumen['cantidad'] ?>.</p>
<?php endif; ?>

<?php if ($datos): ?>
  <div class="item">
    <div class="l1"><span>Total comisiones</span><span class="imp"><?= formatearImporte((float) $datos['total_comisiones']) ?></span></div>
  </div>
  <div class="item">
    <div class="l1"><span>Viáticos adelantados</span><span class="imp"><?= formatearImporte((float) $datos['viaticos_adelantados']) ?></span></div>
  </div>
  <div class="item">
    <div class="l1"><span>Gastos reales</span><span class="imp"><?= formatearImporte((float) $datos['gastos_reales']) ?></span></div>
  </div>
  <div class="item">
    <div class="l1"><span>Ajuste de viáticos</span><span class="imp"><?= formatearImporte((float) $datos['ajuste_viaticos']) ?></span></div>
  </div>

  <div class="totalbar">
    <span>Total a pagar</span>
    <b><?= formatearImporte((float) $datos['total_pagar']) ?></b>
  </div>
<?php endif; ?>

<?php if ($liquidacion): ?>
  <button type="button" class="btn no-print" onclick="window.print()">Imprimir</button>
<?php elseif ($resumen['cantidad'] > 0): ?>
  <form method="post" class="no-print"
    onsubmit="return confirm('¿Cerrar la liquidación de <?= htmlspecialchars(addslashes($choferNombre)) ?> para <?= htmlspecialchars(addslashes($periodoTexto)) ?>? Esto marca los fletes del período como liquidados y no se puede deshacer desde acá.');">
    <input type="hidden" name="accion" value="cerrar">
    <input type="hidden" name="chofer_id" value="<?= $choferId ?>">
    <input type="hidden" name="periodo" value="<?= htmlspecialchars($periodo) ?>">
    <button type="submit" class="btn">Cerrar liquidación</button>
  </form>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
