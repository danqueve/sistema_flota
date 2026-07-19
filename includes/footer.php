</main>
<footer class="app-pie">
  <span>Sistema de Flota &middot; <?= date('Y') ?></span>
</footer>
<script src="<?= BASE_URL ?>/assets/js/menu-movil.js"></script>
<?php foreach ($scriptsPagina ?? [] as $src): ?>
<script src="<?= htmlspecialchars($src) ?>"></script>
<?php endforeach; ?>
</body>
</html>
