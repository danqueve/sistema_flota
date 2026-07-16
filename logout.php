<?php

require_once __DIR__ . '/includes/auth.php';

cerrarSesion();
header('Location: ' . BASE_URL . '/index.php');
exit;
