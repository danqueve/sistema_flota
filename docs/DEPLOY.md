# Deploy al VPS (AlmaLinux + cPanel)

Checklist para subir el sistema por primera vez. Para actualizaciones posteriores, ver la sección final.

## 1. Clonar el repositorio

Vía Terminal de cPanel (o SSH):

```
git clone https://github.com/danqueve/sistema_flota.git
```

Apuntar el dominio/subdominio a la carpeta clonada (o a una subcarpeta si el sistema no vive en la raíz — ver `BASE_URL` más abajo).

## 2. Instalar dependencias de Composer

```
cd sistema_flota
composer install --no-dev
```

`/vendor/` no está versionado — este paso es obligatorio antes de que el sistema funcione (lo usa `dompdf/dompdf` para los PDF de remitos).

## 3. Configurar `config/config.php`

No está versionado (`.gitignore`). Copiar la plantilla y completar con los datos reales del VPS:

```
cp config/config.ejemplo.php config/config.php
```

Editar:

- `ENTORNO` → `'produccion'` (nunca dejar `'local'` en el VPS: con `'local'` se muestran errores de PHP en pantalla, y el portal externo los vería).
- `DB_HOST` / `DB_NAME` / `DB_USER` / `DB_PASS` → los de la base MySQL creada en cPanel.
- `BASE_URL` → `''` si el sistema vive en la raíz del dominio, o `'/subcarpeta'` si vive en una subcarpeta.
- `ARCHIVOS_DIR` → una carpeta **fuera de `public_html`** (hermana, no descendiente), para que los PDF de remitos no queden accesibles por URL directa. Crear la carpeta y darle permiso de escritura al usuario con el que corre PHP en cPanel (`chmod 750` suele alcanzar; evitar `777`).
- `date_default_timezone_set(...)` ya viene fijado a `America/Argentina/Buenos_Aires` — no tocar.

## 4. Importar el esquema y las migraciones

En este orden, contra la base ya creada en cPanel:

```
mysql -u USUARIO -p NOMBRE_BASE < docs/esquema-flota.sql
mysql -u USUARIO -p NOMBRE_BASE < docs/migraciones/fase3-remitos.sql
mysql -u USUARIO -p NOMBRE_BASE < docs/migraciones/fase3-usuarios-portal.sql
```

`docs/esquema-flota.sql` es el esquema aprobado completo (incluye las tablas de mantenimiento de la Fase 4: no hizo falta ninguna migración nueva en esa fase). Los archivos de `docs/migraciones/` son solo los cambios *incrementales* posteriores al esquema base — si en el futuro se agrega uno nuevo, aplicarlo acá antes de dar el deploy por terminado.

Después, cargar los maestros base (camiones, choferes, clientes, estaciones, cuentas, repuestos, tipos de service, categorías, parámetros):

```
mysql -u USUARIO -p NOMBRE_BASE < database/datos_semilla.sql
mysql -u USUARIO -p NOMBRE_BASE < database/datos_semilla_fase2.sql
```

Ajustar antes los datos reales de la empresa (camiones, choferes, clientes, cuentas) si difieren de los de ejemplo — estos dos archivos fueron pensados originalmente como semilla de desarrollo, así que conviene revisarlos línea por línea antes de correrlos en producción.

**No correr `database/generar_datos_demo.php` en producción**: genera un mes de operación *ficticio* (fletes, cheques, services de ejemplo) pensado solo para demos locales o de staging. Si se corre por error contra la base real, hay que limpiar las tablas transaccionales a mano antes de cargar datos reales.

## 5. Crear el usuario admin real

No hay pantalla para el alta del primer usuario interno (`admin`/`taller`) — es a propósito, para no exponer esa alta en el sistema. Generar el hash y cargarlo a mano:

```
php -r "echo password_hash('LA-CLAVE-REAL', PASSWORD_DEFAULT) . PHP_EOL;"
```

```sql
INSERT INTO usuarios (usuario, clave_hash, nombre, rol)
VALUES ('alejandro', 'EL-HASH-GENERADO-ARRIBA', 'Alejandro', 'admin');
```

Repetir para cualquier usuario `taller` que haga falta.

## 6. Crear el usuario del portal de pallets

A diferencia del admin, esto **sí** tiene pantalla propia: una vez logueado como admin, ir a `modulos/pallets/usuarios_portal.php` y dar de alta el usuario real de la empresa de Entre Ríos (no reutilizar `portal_demo`, que es solo para pruebas locales).

## 7. HTTPS

Activar el certificado SSL del dominio (AutoSSL de cPanel o Let's Encrypt) **antes** de que alguien inicie sesión: tanto `includes/auth.php` como `portal/includes/auth_portal.php` marcan la cookie de sesión como `secure` automáticamente cuando detectan `$_SERVER['HTTPS']`, así que sin HTTPS activo la sesión funciona igual pero sin ese refuerzo — no rompe nada, pero conviene tenerlo activo desde el primer día, sobre todo por el portal externo.

## 8. Verificar rutas de error 404/500

El `.htaccess` de la raíz tiene:

```
ErrorDocument 404 /sistema_flota/error.php?codigo=404
ErrorDocument 500 /sistema_flota/error.php?codigo=500
```

Si en el VPS el sistema vive en la raíz del dominio (no en `/sistema_flota`), cambiar esas dos líneas a `/error.php?codigo=404` y `/error.php?codigo=500`.

## 9. Probar antes de avisarle al cliente

- Login con el usuario admin real.
- Cargar un flete de prueba y borrarlo (o dejarlo, si es real).
- Entrar al portal de pallets con el usuario real de la empresa externa.
- Forzar un 404 (URL inexistente) y confirmar que se ve la pantalla de error con el estilo del sistema, no el error crudo de Apache/PHP.

---

## Deploys posteriores (ya instalado)

```
git pull origin main
composer install --no-dev   # solo si composer.json cambió
```

Si hay migraciones nuevas en `docs/migraciones/`, aplicarlas a mano contra la base de producción — no se aplican solas con el `git pull`.
