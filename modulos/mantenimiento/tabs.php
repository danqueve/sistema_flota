<?php
/** Requiere $activo definido antes de incluir (vencimientos|nuevo|historial|planes|tipos). */
?>
<nav class="seg tabs">
  <a href="vencimientos.php" class="<?= $activo === 'vencimientos' ? 'on' : '' ?>">Vencimientos</a>
  <a href="nuevo.php" class="<?= $activo === 'nuevo' ? 'on' : '' ?>">Nuevo service</a>
  <a href="historial.php" class="<?= $activo === 'historial' ? 'on' : '' ?>">Historial</a>
  <a href="planes.php" class="<?= $activo === 'planes' ? 'on' : '' ?>">Planes</a>
  <a href="tipos.php" class="<?= $activo === 'tipos' ? 'on' : '' ?>">Tipos</a>
</nav>
