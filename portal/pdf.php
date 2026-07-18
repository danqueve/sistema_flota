<?php

require_once __DIR__ . '/includes/auth_portal.php';
requerirLoginPortal();
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/pdf_remito.php';

$usuarioPortal = usuarioPortalActual();
$pdo = obtenerConexion();

$remitoId = (int) ($_GET['id'] ?? 0);

// El remito tiene que existir Y pertenecer al cliente de esta sesión —
// nunca confiar en que el id de la URL sea de este cliente.
$stmt = $pdo->prepare('SELECT id, numero, cliente_id, pdf_generado FROM remitos WHERE id = ? AND cliente_id = ?');
$stmt->execute([$remitoId, $usuarioPortal['cliente_id']]);
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

aplicarCabecerasSeguridadPortal();
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="remito_' . $numeroFormateado . '.pdf"');
header('Content-Length: ' . filesize($ruta));
readfile($ruta);
