<?php
/** Requiere $activo definido antes de incluir (nuevo|listado|stock|usuarios). */
?>
<nav class="seg tabs">
  <a href="nuevo.php" class="<?= $activo === 'nuevo' ? 'on' : '' ?>">Nuevo remito</a>
  <a href="listado.php" class="<?= $activo === 'listado' ? 'on' : '' ?>">Remitos</a>
  <a href="stock.php" class="<?= $activo === 'stock' ? 'on' : '' ?>">Stock</a>
  <a href="usuarios_portal.php" class="<?= $activo === 'usuarios' ? 'on' : '' ?>">Usuarios portal</a>
</nav>
