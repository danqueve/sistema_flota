<?php

require_once __DIR__ . '/config/config.php';

$codigo = in_array($_GET['codigo'] ?? '', ['403', '404', '500'], true) ? $_GET['codigo'] : '500';
http_response_code((int) $codigo);

$textos = [
    '403' => ['titulo' => 'No tenés permiso', 'detalle' => 'Esta pantalla no está disponible para tu usuario. Si te parece un error, avisale a Alejandro.'],
    '404' => ['titulo' => 'No encontramos esta página', 'detalle' => 'Revisá la dirección o volvé al inicio.'],
    '500' => ['titulo' => 'Ocurrió un error', 'detalle' => 'Probá de nuevo en un momento. Si el problema sigue, avisale al desarrollador.'],
];
$texto = $textos[$codigo];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($texto['titulo']) ?> — Sistema de Flota</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🚚</text></svg>">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/estilo.css">
</head>
<body class="login-body">
  <div class="login-tarjeta">
    <div class="login-barra">
      <span class="login-barra__logo">🚚</span>
      <h1>Sistema de Flota</h1>
    </div>
    <div class="login-form error-cuerpo">
      <p class="nota">
        <strong><?= htmlspecialchars($texto['titulo']) ?></strong><br>
        <?= htmlspecialchars($texto['detalle']) ?>
      </p>
      <a href="<?= BASE_URL ?>/index.php" class="btn">Volver al inicio</a>
    </div>
  </div>
</body>
</html>
