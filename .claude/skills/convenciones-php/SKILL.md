---
name: convenciones-php
description: usar siempre que se escriba código PHP o SQL
---

# Convenciones de código — Sistema de Flota

## Backend

- PHP 8+ **sin framework**. Estructura procedural prolija, funciones organizadas por módulo (mismo estilo que un sistema de gestión clásico) — no introducir clases, autoloaders ni patrones MVC salvo que se pida explícitamente.
- Sin Composer, salvo `dompdf` cuando se lleguen a construir PDFs (remitos, liquidaciones).
- **PDO exclusivamente, siempre con prepared statements.** Nunca concatenar valores de usuario en una consulta SQL, ni siquiera para nombres de columnas fijos que vienen de un `switch`/whitelist.
- `password_hash()` / `password_verify()` para claves. Nunca guardar ni loguear una clave en texto plano.
- Sesión PHP (`session_start()` en un bootstrap común) con verificación de rol en **cada** página, no solo en el menú: `admin`, `taller`, `portal_pallets`. Redirigir a login si no hay sesión válida o el rol no tiene permiso sobre esa página.
- Transacciones (`beginTransaction()` / `commit()` / `rollBack()`) en toda operación que toque más de una tabla: alta de flete + sus gastos, cambio de estado de cheque + `cheques_movimientos` + `movimientos_tesoreria`, egreso/ingreso de stock + `movimientos_stock` + `repuestos.stock_actual`. Envolver en try/catch y hacer `rollBack()` ante cualquier excepción.
- Archivo de configuración real (`config/config.php`, con credenciales) **nunca se versiona**; el placeholder versionado es `config/config.ejemplo.php`.

## Frontend

- HTML + CSS propio siguiendo los tokens de [[diseno-flota]] + JavaScript vanilla. **Sin frameworks JS** (nada de React/Vue/jQuery).
- Chart.js, cargado desde cdnjs, únicamente para las gráficas de consumo y facturación — no para nada más.
- Todas las páginas responsive **mobile-first**: diseñar primero para 390px y expandir, no al revés.

## Datos y formato

- Importes: `DECIMAL` en la base de datos (nunca `FLOAT`/`DOUBLE`). Al mostrar: `number_format($x, 2, ',', '.')` con prefijo `$` (ej. `$ 1.450.000,00`).
- Fechas en pantalla: `d/m/Y`. En la base de datos, `DATE`/`DATETIME` estándar de MySQL.
- Los nombres de tablas y columnas del esquema (`docs/esquema-flota.sql`) son fijos: no renombrar columnas ni tablas al escribir consultas.

## Estructura de carpetas

```
config/config.php (ignorado) + config/config.ejemplo.php (versionado)
includes/db.php, auth.php, header.php, footer.php, funciones.php
modulos/fletes/ combustible/ stock/ pallets/ mantenimiento/ cheques/ liquidaciones/
portal/          ← solo lectura para la empresa de pallets
assets/css/ assets/js/
index.php        ← login
```

- `includes/db.php`: conexión PDO única, reutilizada por `require`/`include` en cada página.
- `includes/auth.php`: funciones de verificación de sesión y rol.
- `includes/funciones.php`: helpers compartidos (formato de importes, fechas, cálculos reutilizables).
- Cada módulo en `modulos/<nombre>/` con sus propias páginas (`listado.php`, `nuevo.php`, `editar.php`, etc.), sin mezclar lógica de un módulo dentro de otro.

## Idioma

Todo el código, comentarios, nombres de variables/funciones y textos de interfaz van **en español** (ej. `$importeBruto`, `function calcularComision()`, botón "Guardar flete").
