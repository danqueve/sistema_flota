<?php

require_once __DIR__ . '/includes/auth.php';

if (estaLogueado()) {
    header('Location: ' . urlInicioSegunRol($_SESSION['usuario_rol']));
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $clave   = (string) ($_POST['clave'] ?? '');

    if ($usuario !== '' && $clave !== '') {
        $resultado = intentarLogin($usuario, $clave);

        if ($resultado['ok']) {
            header('Location: ' . urlInicioSegunRol($_SESSION['usuario_rol']));
            exit;
        }

        $error = $resultado['mensaje'];
    } else {
        $error = 'Usuario o clave incorrectos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ingresar — Sistema de Flota</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🚚</text></svg>">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/estilo.css">
</head>
<body class="login-body">
  <div class="login-tarjeta">
    <div class="login-barra">
      <span class="login-barra__logo">🚚</span>
      <h1>Sistema de Flota</h1>
    </div>
    <form method="post" class="login-form" novalidate>
      <?php if ($error): ?>
        <p class="login-error"><?= htmlspecialchars($error) ?></p>
      <?php endif; ?>

      <label for="usuario">Usuario</label>
      <input class="campo-input" type="text" id="usuario" name="usuario" autocomplete="username" required autofocus>

      <label for="clave">Clave</label>
      <input class="campo-input" type="password" id="clave" name="clave" autocomplete="current-password" required>

      <button type="submit" class="btn">Ingresar</button>
    </form>
  </div>
</body>
</html>
