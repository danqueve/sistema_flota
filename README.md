# Sistema de Flota

Plataforma web de gestión integral para una empresa de transporte de carga en Tucumán, Argentina (3 camiones). Reemplaza cuadernos y planillas de Excel por un sistema único, responsive, pensado para cargarse en menos de 30 segundos desde el celular.

PHP 8 puro (sin framework), MySQL/MariaDB, JavaScript vanilla. Proyecto privado — ver [Licencia](#licencia).

## Módulos

| # | Módulo | Qué resuelve |
|---|--------|--------------|
| 1 | **Fletes y liquidación de choferes** | Alta de fletes con comisión automática (% histórico por flete, no recalculado nunca), gastos de viaje vs. viático adelantado, combustible con control antirrobo por consumo, liquidación mensual imprimible. |
| 2 | **Stock de repuestos y cubiertas** | Movimientos de stock (ingreso/egreso/ajuste) nunca negativos, alerta de bajo mínimo, trazabilidad completa hacia el service que consumió cada repuesto. |
| 3 | **Pallets** | Recepción/devolución con remito en PDF y numeración correlativa, más un **portal externo de solo lectura** para que el cliente destinatario vea su propio stock en tiempo real. |
| 4 | **Mantenimiento de vehículos** | Planes de service por camión (intervalo por km y/o por fecha, vence lo que ocurra primero), semáforo de vencimientos, historial con costos y repuestos usados. |
| 5 | **Cheques y tesorería** | Cheques recibidos y emitidos con máquina de estados validada en el backend, movimientos de tesorería automáticos, posición financiera siempre recalculada (nunca una caché desactualizada). |

**Roles:** `admin` (dueño, acceso total), `taller` (consulta stock, registra uso), `portal_pallets` (solo lectura, empresa externa, sesión y autenticación completamente separadas del resto del sistema). Los choferes no tienen acceso al sistema.

## Stack

- **Backend:** PHP 8+ procedural, sin framework, organizado por módulo. PDO exclusivamente, siempre con prepared statements.
- **Base de datos:** MySQL/MariaDB.
- **Frontend:** HTML + CSS propio (sistema de diseño documentado en `.claude/skills/diseno-flota/`) + JavaScript vanilla. [Chart.js](https://www.chartjs.org/) (vía cdnjs) solo para el gráfico de consumo.
- **Dependencia de Composer:** [`dompdf/dompdf`](https://github.com/dompdf/dompdf), para los PDF de remitos.
- **Sesión:** dos sistemas de autenticación completamente independientes — uno para el sistema interno (`includes/auth.php`) y otro para el portal externo (`portal/includes/auth_portal.php`, cookie de sesión propia) — con bloqueo por intentos fallidos en ambos.

## Estructura del proyecto

```
config/         config.php (no versionado) + config.ejemplo.php (plantilla)
includes/       conexión a BD, autenticación, layout compartido, helpers de negocio
modulos/        fletes/ combustible/ stock/ pallets/ mantenimiento/ cheques/
                tesoreria/ liquidaciones/ maestros/
portal/         portal externo de solo lectura (sesión propia)
assets/         css/ y js/ del sistema
database/       seeds de maestros + generar_datos_demo.php (datos de demo reproducibles)
docs/           esquema SQL aprobado, migraciones, guion de demo, checklist de deploy
error.php       pantalla de error 403/404/500 con el estilo del sistema
index.php       login
```

## Puesta en marcha (desarrollo local)

Requisitos: PHP 8+, MySQL/MariaDB, Composer, Apache (WAMP/XAMPP en Windows funciona bien).

```bash
git clone https://github.com/danqueve/sistema_flota.git
cd sistema_flota
composer install

cp config/config.ejemplo.php config/config.php
# completar DB_HOST/DB_NAME/DB_USER/DB_PASS, BASE_URL y ARCHIVOS_DIR

mysql -u root sistema_flota < docs/esquema-flota.sql
mysql -u root sistema_flota < docs/migraciones/fase3-remitos.sql
mysql -u root sistema_flota < docs/migraciones/fase3-usuarios-portal.sql
mysql -u root sistema_flota < docs/migraciones/fase4-usuarios-rate-limit.sql
mysql -u root sistema_flota < database/datos_semilla.sql
mysql -u root sistema_flota < database/datos_semilla_fase2.sql

# usuario admin (no hay pantalla de alta, es a propósito):
php -r "echo password_hash('tu-clave', PASSWORD_DEFAULT) . PHP_EOL;"
# INSERT INTO usuarios (usuario, clave_hash, nombre, rol) VALUES (...)
```

### Datos de demo

`php database/generar_datos_demo.php` genera un mes completo de operación coherente entre los 5 módulos — fletes que se cobran con cheques, resumen de combustible pagado, repuestos consumidos en services, tarimas cuyo stock cuadra con sus remitos. Reproducible en cualquier momento (las fechas son relativas a "hoy"); **no correr contra una base con datos reales**. Guion de demostración completo en [`docs/DEMO.md`](docs/DEMO.md).

## Deploy a producción

Checklist completo (VPS AlmaLinux + cPanel, pero aplicable a cualquier hosting con Apache+MySQL) en [`docs/DEPLOY.md`](docs/DEPLOY.md): Composer, configuración de entorno, esquema + migraciones, permisos de archivos, HTTPS, alta de usuarios.

## Seguridad

- Contraseñas con `password_hash()`/`password_verify()`; nunca en texto plano en logs, seeds ni datos de demo.
- Bloqueo de 5 intentos fallidos / 15 minutos en ambos logins (interno y portal), con mitigación de timing attack para no delatar si un usuario existe.
- Cookies de sesión `HttpOnly` + `SameSite=Lax` + `secure` dinámico según HTTPS; regeneración de ID de sesión en cada login.
- El portal externo vive en una sesión y tabla de usuarios completamente separadas del sistema interno — ningún dato de un cliente es visible para otro, ni el portal tiene acceso a fletes, cheques o tesorería.
- Ningún error interno (excepciones, fallos de base de datos) se muestra crudo al usuario: manejador global + `error.php` con el estilo del sistema para 403/404/500.
- CSRF: decisión consciente de no implementar tokens (perfil de un solo admin de confianza, sin exposición pública, mutaciones solo por POST protegidas por `SameSite=Lax`) — documentado en `CLAUDE.md`, a revisar si el perfil de uso cambia.

## Licencia

Software propietario, desarrollado a medida para un cliente específico. Todos los derechos reservados — no está disponible para uso, copia o redistribución por terceros.
