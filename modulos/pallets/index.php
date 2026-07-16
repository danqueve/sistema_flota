<?php

require_once __DIR__ . '/../../includes/auth.php';
requerirRol(['admin']);

$tituloPagina = 'Pallets';
require __DIR__ . '/../../includes/header.php';
?>
<div class="placeholder-fase">
  <span class="chip cartera">Fase 3</span>
  <h1>Pallets</h1>
  <p>Remitos digitales y portal de solo lectura para la empresa de Entre Ríos — se construyen en la Fase 3.</p>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
