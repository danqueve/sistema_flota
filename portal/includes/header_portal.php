<?php

// La página que incluye este archivo debe haber hecho require_once
// portal/includes/auth_portal.php y llamado requerirLoginPortal() antes.

require_once __DIR__ . '/../../includes/datos_empresa.php';

aplicarCabecerasSeguridadPortal();

$usuarioPortal = usuarioPortalActual();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($tituloPagina) ? htmlspecialchars($tituloPagina) . ' — ' : '' ?><?= htmlspecialchars(EMPRESA_MARCA) ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/estilo.css">
</head>
<body>
<header class="app-barra">
  <div class="app-barra__marca">
    <span class="app-barra__logo">📦</span>
    <span><?= htmlspecialchars(EMPRESA_MARCA) ?></span>
  </div>
  <?php if ($usuarioPortal): ?>
  <nav class="app-menu">
    <a href="<?= BASE_URL ?>/portal/inicio.php">Inicio</a>
    <a href="<?= BASE_URL ?>/portal/remitos.php">Remitos</a>
  </nav>
  <div class="app-usuario">
    <span><?= htmlspecialchars($usuarioPortal['razon_social']) ?></span>
    <a href="<?= BASE_URL ?>/portal/logout.php" class="app-usuario__salir">Salir</a>
  </div>
  <?php endif; ?>
</header>
<main class="app-contenido">
