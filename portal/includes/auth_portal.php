<?php

require_once __DIR__ . '/../../includes/db.php';

session_name('flota_portal');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('PORTAL_MAX_INTENTOS', 5);
define('PORTAL_MINUTOS_BLOQUEO', 15);

function aplicarCabecerasSeguridadPortal(): void
{
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
}

function estaLogueadoPortal(): bool
{
    return isset($_SESSION['portal_usuario_id']);
}

function usuarioPortalActual(): ?array
{
    if (!estaLogueadoPortal()) {
        return null;
    }

    return [
        'id'           => $_SESSION['portal_usuario_id'],
        'nombre'       => $_SESSION['portal_nombre'],
        'usuario'      => $_SESSION['portal_usuario'],
        'cliente_id'   => $_SESSION['portal_cliente_id'],
        'razon_social' => $_SESSION['portal_razon_social'],
    ];
}

function requerirLoginPortal(): void
{
    if (!estaLogueadoPortal()) {
        header('Location: ' . BASE_URL . '/portal/index.php');
        exit;
    }
}

/**
 * @return array{ok: bool, mensaje: ?string}
 */
function intentarLoginPortal(string $usuario, string $clave): array
{
    $pdo = obtenerConexion();
    $stmt = $pdo->prepare(
        'SELECT up.*, cl.razon_social
         FROM usuarios_portal up
         JOIN clientes cl ON cl.id = up.cliente_id
         WHERE up.usuario = ? AND up.activo = 1'
    );
    $stmt->execute([$usuario]);
    $fila = $stmt->fetch();

    if (!$fila) {
        return ['ok' => false, 'mensaje' => 'Usuario o clave incorrectos.'];
    }

    if ($fila['bloqueado_hasta'] !== null && strtotime($fila['bloqueado_hasta']) > time()) {
        return ['ok' => false, 'mensaje' => 'Cuenta bloqueada temporalmente por intentos fallidos. Probá de nuevo más tarde.'];
    }

    if (!password_verify($clave, $fila['clave_hash'])) {
        $intentos = (int) $fila['intentos_fallidos'] + 1;

        if ($intentos >= PORTAL_MAX_INTENTOS) {
            $stmt = $pdo->prepare(
                'UPDATE usuarios_portal SET intentos_fallidos = ?, bloqueado_hasta = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id = ?'
            );
            $stmt->execute([$intentos, PORTAL_MINUTOS_BLOQUEO, $fila['id']]);
        } else {
            $pdo->prepare('UPDATE usuarios_portal SET intentos_fallidos = ? WHERE id = ?')->execute([$intentos, $fila['id']]);
        }

        return ['ok' => false, 'mensaje' => 'Usuario o clave incorrectos.'];
    }

    $pdo->prepare('UPDATE usuarios_portal SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = ?')->execute([$fila['id']]);

    session_regenerate_id(true);
    $_SESSION['portal_usuario_id']   = $fila['id'];
    $_SESSION['portal_nombre']       = $fila['nombre'];
    $_SESSION['portal_usuario']      = $fila['usuario'];
    $_SESSION['portal_cliente_id']   = $fila['cliente_id'];
    $_SESSION['portal_razon_social'] = $fila['razon_social'];

    return ['ok' => true, 'mensaje' => null];
}

function cerrarSesionPortal(): void
{
    $_SESSION = [];
    session_destroy();
}
