<?php
/** Requiere $activo definido antes de incluir (cuentas|financieras|nuevo|cartera|emitidos). */
?>
<nav class="seg tabs">
  <a href="cartera.php" class="<?= $activo === 'cartera' ? 'on' : '' ?>">Cartera</a>
  <a href="nuevo.php" class="<?= $activo === 'nuevo' ? 'on' : '' ?>">Nuevo cheque</a>
  <a href="emitidos.php" class="<?= $activo === 'emitidos' ? 'on' : '' ?>">Emitidos</a>
  <a href="cuentas.php" class="<?= $activo === 'cuentas' ? 'on' : '' ?>">Cuentas</a>
  <a href="financieras.php" class="<?= $activo === 'financieras' ? 'on' : '' ?>">Financieras</a>
</nav>
