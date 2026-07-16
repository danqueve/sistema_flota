<?php

require_once __DIR__ . '/../../includes/auth.php';
requerirRol(['admin', 'taller']);

$tituloPagina = 'Stock';
require __DIR__ . '/../../includes/header.php';
?>
<div class="placeholder-fase">
  <span class="chip cartera">Fase 2</span>
  <h1>Stock de repuestos y cubiertas</h1>
  <p>Este módulo se construye en la Fase 2, junto con cheques y tesorería.</p>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
