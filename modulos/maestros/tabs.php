<?php
/** Requiere $activo definido (camiones|choferes|clientes|estaciones) antes de incluir. */
?>
<nav class="seg tabs">
  <a href="camiones.php" class="<?= $activo === 'camiones' ? 'on' : '' ?>">Camiones</a>
  <a href="choferes.php" class="<?= $activo === 'choferes' ? 'on' : '' ?>">Choferes</a>
  <a href="clientes.php" class="<?= $activo === 'clientes' ? 'on' : '' ?>">Clientes</a>
  <a href="estaciones.php" class="<?= $activo === 'estaciones' ? 'on' : '' ?>">Estaciones</a>
</nav>
