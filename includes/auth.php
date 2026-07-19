<?php

require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    $httpsActivo = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $httpsActivo,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
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
        $_GET['codigo'] = '403';
        require __DIR__ . '/../error.php';
        exit;
    }
}

define('LOGIN_MAX_INTENTOS', 5);
define('LOGIN_MINUTOS_BLOQUEO', 15);

/**
 * @return array{ok: bool, mensaje: ?string}
 */
function intentarLogin(string $usuario, string $clave): array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE usuario = ? AND activo = 1');
    $stmt->execute([$usuario]);
    $fila = $stmt->fetch();

    if (!$fila) {
        // Hash dummy: mismo costo de bcrypt que el camino real, para que el
        // tiempo de respuesta no delate si el usuario existe o no.
        password_verify($clave, '$2y$10$C7g8vC1e8s5m5x1sT0f8UOo3iM6RZ8h1a1vQd9y0kM1p8c2b7a6de');

        return ['ok' => false, 'mensaje' => 'Usuario o clave incorrectos.'];
    }

    if ($fila['bloqueado_hasta'] !== null && strtotime($fila['bloqueado_hasta']) > time()) {
        return ['ok' => false, 'mensaje' => 'Cuenta bloqueada temporalmente por intentos fallidos. Probá de nuevo más tarde.'];
    }

    if (!password_verify($clave, $fila['clave_hash'])) {
        // Incremento atómico en la propia base (no leer-calcular-escribir en
        // PHP): evita que solicitudes concurrentes pisen el contador y
        // permitan más de LOGIN_MAX_INTENTOS intentos reales antes de que
        // el bloqueo se aplique. Van en dos pasos separados porque MySQL
        // evalúa las expresiones del SET en orden — si se compara el umbral
        // en el mismo UPDATE que incrementa, ve el valor ya incrementado y
        // bloquea un intento antes de lo debido.
        $pdo->prepare('UPDATE usuarios SET intentos_fallidos = intentos_fallidos + 1 WHERE id = ?')
            ->execute([$fila['id']]);

        $stmt = $pdo->prepare('SELECT intentos_fallidos FROM usuarios WHERE id = ?');
        $stmt->execute([$fila['id']]);
        $intentosActuales = (int) $stmt->fetchColumn();

        if ($intentosActuales >= LOGIN_MAX_INTENTOS) {
            $pdo->prepare('UPDATE usuarios SET bloqueado_hasta = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id = ?')
                ->execute([LOGIN_MINUTOS_BLOQUEO, $fila['id']]);
        }

        return ['ok' => false, 'mensaje' => 'Usuario o clave incorrectos.'];
    }

    $pdo->prepare('UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = ?')->execute([$fila['id']]);

    session_regenerate_id(true);
    $_SESSION['usuario_id']      = $fila['id'];
    $_SESSION['usuario_nombre']  = $fila['nombre'];
    $_SESSION['usuario_usuario'] = $fila['usuario'];
    $_SESSION['usuario_rol']     = $fila['rol'];

    return ['ok' => true, 'mensaje' => null];
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
