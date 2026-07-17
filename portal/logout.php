<?php

require_once __DIR__ . '/includes/auth_portal.php';

cerrarSesionPortal();
header('Location: ' . BASE_URL . '/portal/index.php');
exit;
