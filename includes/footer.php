</main>
<footer class="app-pie">
  <span>Sistema de Flota &middot; <?= date('Y') ?></span>
</footer>
<?php foreach ($scriptsPagina ?? [] as $src): ?>
<script src="<?= htmlspecialchars($src) ?>"></script>
<?php endforeach; ?>
</body>
</html>
