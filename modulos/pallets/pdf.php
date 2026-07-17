<?php

require_once __DIR__ . '/../../includes/auth.php';
requerirRol(['admin']);
require_once __DIR__ . '/../../includes/funciones.php';
require_once __DIR__ . '/../../includes/pdf_remito.php';

$pdo = obtenerConexion();

$remitoId = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT id, numero, pdf_generado FROM remitos WHERE id = ?');
$stmt->execute([$remitoId]);
$remito = $stmt->fetch();

if (!$remito) {
    http_response_code(404);
    echo 'No encontré ese remito.';
    exit;
}

$ruta = rutaPdfRemito((int) $remito['numero']);

if (!$remito['pdf_generado'] || !is_file($ruta)) {
    try {
        $ruta = generarYGuardarPdfRemito($pdo, $remitoId);
    } catch (Throwable $e) {
        http_response_code(500);
        echo 'No pude generar el PDF de este remito.';
        exit;
    }
}

$numeroFormateado = str_pad((string) $remito['numero'], 6, '0', STR_PAD_LEFT);

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="remito_' . $numeroFormateado . '.pdf"');
header('Content-Length: ' . filesize($ruta));
header('X-Content-Type-Options: nosniff');
readfile($ruta);
