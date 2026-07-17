<?php

// La página que incluye este archivo debe haber hecho require_once
// includes/auth.php y llamado requerirLogin() / requerirRol() antes.

$usuario = usuarioActual();
$rol     = $usuario['rol'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($tituloPagina) ? htmlspecialchars($tituloPagina) . ' — ' : '' ?>Sistema de Flota</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/estilo.css">
</head>
<body>
<header class="app-barra">
  <div class="app-barra__marca">
    <span class="app-barra__logo">🚚</span>
    <span>Sistema de Flota</span>
  </div>
  <?php if ($usuario): ?>
  <nav class="app-menu">
    <?php if ($rol === 'admin'): ?>
      <a href="<?= BASE_URL ?>/dashboard.php">Inicio</a>
      <a href="<?= BASE_URL ?>/modulos/fletes/listado.php">Fletes</a>
      <a href="<?= BASE_URL ?>/modulos/combustible/nuevo.php">Combustible</a>
      <a href="<?= BASE_URL ?>/modulos/maestros/camiones.php">Maestros</a>
      <a href="<?= BASE_URL ?>/modulos/stock/index.php">Stock</a>
      <a href="<?= BASE_URL ?>/modulos/cheques/index.php">Cheques</a>
      <a href="<?= BASE_URL ?>/modulos/pallets/index.php">Pallets</a>
      <a href="<?= BASE_URL ?>/modulos/mantenimiento/index.php">Mantenimiento</a>
    <?php elseif ($rol === 'taller'): ?>
      <a href="<?= BASE_URL ?>/modulos/stock/index.php">Stock</a>
    <?php elseif ($rol === 'portal_pallets'): ?>
      <a href="<?= BASE_URL ?>/portal/index.php">Pallets</a>
    <?php endif; ?>
  </nav>
  <div class="app-usuario">
    <span><?= htmlspecialchars($usuario['nombre']) ?></span>
    <a href="<?= BASE_URL ?>/logout.php" class="app-usuario__salir">Salir</a>
  </div>
  <?php endif; ?>
</header>
<main class="app-contenido">
