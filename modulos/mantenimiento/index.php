<?php

require_once __DIR__ . '/../../includes/auth.php';
requerirRol(['admin']);

$tituloPagina = 'Mantenimiento';
require __DIR__ . '/../../includes/header.php';
?>
<div class="placeholder-fase">
  <span class="chip cartera">Fase 4</span>
  <h1>Mantenimiento de vehículos</h1>
  <p>Planes de service, alertas por km/fecha e integración GPS — se construyen en la Fase 4.</p>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
