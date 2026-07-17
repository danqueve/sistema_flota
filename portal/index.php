<?php

require_once __DIR__ . '/includes/auth_portal.php';
require_once __DIR__ . '/../includes/datos_empresa.php';

aplicarCabecerasSeguridadPortal();

if (estaLogueadoPortal()) {
    header('Location: ' . BASE_URL . '/portal/inicio.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $clave   = (string) ($_POST['clave'] ?? '');

    if ($usuario !== '' && $clave !== '') {
        $resultado = intentarLoginPortal($usuario, $clave);
        if ($resultado['ok']) {
            header('Location: ' . BASE_URL . '/portal/inicio.php');
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
<title>Ingresar — <?= htmlspecialchars(EMPRESA_MARCA) ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/estilo.css">
</head>
<body class="login-body">
  <div class="login-tarjeta">
    <div class="login-barra">
      <span class="login-barra__logo">📦</span>
      <h1><?= htmlspecialchars(EMPRESA_MARCA) ?></h1>
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
  <footer class="app-pie">
    <span>Sistema provisto por [MARCA DEL DESARROLLADOR — pendiente]</span>
  </footer>
</body>
</html>
