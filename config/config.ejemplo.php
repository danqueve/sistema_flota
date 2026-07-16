<?php

// Copiar este archivo como config.php (ignorado por git) y completar
// según el entorno. En WAMP local: usuario root sin clave.

define('DB_HOST', 'localhost');
define('DB_NAME', 'sistema_flota');
define('DB_USER', 'root');
define('DB_PASS', '');

// Ruta base de la app, sin barra final. Local WAMP: /sistema_flota
// VPS: '' si vive en la raíz del dominio, o '/ruta' si vive en una subcarpeta.
define('BASE_URL', '/sistema_flota');
