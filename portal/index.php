<?php

require_once __DIR__ . '/../includes/auth.php';
requerirRol(['portal_pallets']);

$tituloPagina = 'Portal de pallets';
require __DIR__ . '/../includes/header.php';
?>
<div class="placeholder-fase">
  <span class="chip cartera">Fase 3</span>
  <h1>Portal de pallets</h1>
  <p>Acá vas a poder ver el stock de pallets en Tucumán por estado, las devoluciones en tránsito y el historial de remitos. Se construye en la Fase 3.</p>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
