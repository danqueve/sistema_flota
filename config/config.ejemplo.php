<?php

// Copiar este archivo como config.php (ignorado por git) y completar
// según el entorno. En WAMP local: usuario root sin clave.

// El cliente opera en Tucumán; sin esto PHP usa UTC por defecto y puede
// desincronizarse con el "hoy" de MySQL (que usa la zona del sistema) en
// la franja 21:00-00:00 ART, rompiendo cálculos de vencimiento/período.
date_default_timezone_set('America/Argentina/Buenos_Aires');

// 'local' o 'produccion'. En producción nunca se muestran errores de PHP en
// pantalla (el portal lo ve un tercero externo). Cambiar a 'produccion' en el VPS.
define('ENTORNO', 'local');

if (ENTORNO === 'produccion') {
    ini_set('display_errors', '0');
    error_reporting(0);
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

// Red de seguridad: nunca se filtra un stack trace ni una ruta de servidor.
set_exception_handler(function (Throwable $e): void {
    error_log('[sistema_flota] ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo 'Ocurrió un error. Probá de nuevo en un momento.';
});

define('DB_HOST', 'localhost');
define('DB_NAME', 'sistema_flota');
define('DB_USER', 'root');
define('DB_PASS', '');

// Ruta base de la app, sin barra final. Local WAMP: /sistema_flota
// VPS: '' si vive en la raíz del dominio, o '/ruta' si vive en una subcarpeta.
define('BASE_URL', '/sistema_flota');

// Carpeta fuera del webroot público donde se guardan los PDF de remitos.
// Local WAMP: fuera de c:/wamp64/www. VPS/cPanel: fuera de public_html.
define('ARCHIVOS_DIR', 'C:/wamp64/archivos_flota');
