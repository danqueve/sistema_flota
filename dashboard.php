<?php

require_once __DIR__ . '/includes/auth.php';
requerirRol(['admin']);

$tituloPagina = 'Inicio';
require __DIR__ . '/includes/header.php';
?>
<h1>Hola, <?= htmlspecialchars(usuarioActual()['nombre']) ?></h1>
<p class="nota">El dashboard con fletes del mes por camión, gráfica de consumo y cheques próximos a vencer se arma en el Paso 4.8, al final de la Fase 1.</p>
<?php require __DIR__ . '/includes/footer.php'; ?>
