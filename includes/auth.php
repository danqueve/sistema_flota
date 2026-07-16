<?php

require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function estaLogueado(): bool
{
    return isset($_SESSION['usuario_id']);
}

function usuarioActual(): ?array
{
    if (!estaLogueado()) {
        return null;
    }

    return [
        'id'      => $_SESSION['usuario_id'],
        'nombre'  => $_SESSION['usuario_nombre'],
        'usuario' => $_SESSION['usuario_usuario'],
        'rol'     => $_SESSION['usuario_rol'],
    ];
}

function requerirLogin(): void
{
    if (!estaLogueado()) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

function requerirRol(array $rolesPermitidos): void
{
    requerirLogin();

    if (!in_array($_SESSION['usuario_rol'], $rolesPermitidos, true)) {
        http_response_code(403);
        echo 'No tenés permiso para acceder a esta página.';
        exit;
    }
}

function intentarLogin(string $usuario, string $clave): bool
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare('SELECT id, nombre, usuario, clave_hash, rol FROM usuarios WHERE usuario = ? AND activo = 1');
    $stmt->execute([$usuario]);
    $fila = $stmt->fetch();

    if (!$fila || !password_verify($clave, $fila['clave_hash'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['usuario_id']      = $fila['id'];
    $_SESSION['usuario_nombre']  = $fila['nombre'];
    $_SESSION['usuario_usuario'] = $fila['usuario'];
    $_SESSION['usuario_rol']     = $fila['rol'];

    return true;
}

function cerrarSesion(): void
{
    $_SESSION = [];
    session_destroy();
}

function urlInicioSegunRol(string $rol): string
{
    return match ($rol) {
        'portal_pallets' => BASE_URL . '/portal/index.php',
        'taller'         => BASE_URL . '/modulos/stock/index.php',
        default          => BASE_URL . '/dashboard.php',
    };
}
