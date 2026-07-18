# CLAUDE.md

Contexto de proyecto para Claude Code. Leer completo antes de escribir código.

## Descripción del proyecto

**Sistema de Gestión de Flota** para un transportista de Tucumán, Argentina (3 camiones), hoy llevado en cuadernos y planillas de Excel. Plataforma web única, responsive, usada desde PC y sobre todo desde celular. Cliente: Alejandro (dueño), desarrollador PHP/MySQL.

5 módulos (ver `docs/planificacion-sistema-flota.md`, fuente de verdad del proyecto):

1. **Fletes y liquidación de choferes** — Fase 1, construido.
2. **Stock de repuestos y cubiertas** — Fase 2, construido.
3. **Pallets** (portal de solo lectura para una empresa de Entre Ríos) — Fase 3, construido.
4. **Mantenimiento de vehículos** — Fase 4, en construcción.
5. **Cheques y tesorería** — Fase 2, construido.

**Roles:** `admin` (Alejandro, acceso total), `taller` (consulta stock, registra uso con autorización), `portal_pallets` (solo lectura, empresa externa). Los choferes **no tienen acceso** al sistema — todo pasa por el dueño.

## Stack

- **Backend:** PHP 8+ puro, sin framework, procedural, funciones organizadas por módulo. Única dependencia de Composer: `dompdf/dompdf` (PDFs de remitos, Fase 3).
- **Base de datos:** MySQL/MariaDB (WAMP local en desarrollo; el mismo motor en el VPS de producción).
- **Frontend:** HTML + CSS propio siguiendo los wireframes + JavaScript vanilla. Sin frameworks JS. Chart.js (desde cdnjs) solo para gráficas de consumo y facturación.
- **Entorno local:** Windows + WAMP64, base `sistema_flota`, MySQL usuario `root` sin clave, host `localhost`.
- **Zona horaria:** `America/Argentina/Buenos_Aires`, fijada en `config/config.php` con `date_default_timezone_set()`. Sin esto PHP cae en UTC por defecto y se desincroniza con el "hoy" de MySQL (zona del sistema) entre las 21:00 y las 00:00 hora Argentina — no la borres ni la cambies.
- **Deploy:** VPS AlmaLinux con cPanel, vía `git pull origin main`. Desde la Fase 3, después de cada `git pull` hay que correr **`composer install`** (`/vendor/` no se versiona) y, si hay migraciones nuevas en `docs/migraciones/`, aplicarlas a mano contra la base de producción.
- **Idioma:** todo el código, comentarios, nombres de variables y textos de interfaz van en **español**.

## Definiciones confirmadas por el cliente

- **Comisión del chofer:** porcentaje único para todos los choferes (no varía por chofer), calculado **sobre el bruto del flete**. Arranca en 15%, parámetro global editable en `parametros`, con histórico: cada flete copia el % vigente al crearse.
- **Viáticos:** se adelanta un monto por viaje; los gastos reales se cargan aparte. En la liquidación mensual se compara adelanto vs. gastos reales y se calcula el ajuste a favor del chofer.
- **Combustible:** cuenta corriente en 2–3 estaciones (no mueve caja al momento, se concilia contra el resumen mensual de la estación) + cargas eventuales de contado en otras estaciones.
- **Cheques:** físicos y ECHEQ, dos bancos propios (ABM de cuentas), financieras variables (ABM simple, se cargan a medida que aparecen), los rechazos registran gastos/comisiones asociadas.
- **Pallets:** módulo diferencial con portal externo de solo lectura para la empresa de Entre Ríos.
- **Volumen:** bajo (12–45 fletes/mes en total) — prioridad a la simplicidad de carga por sobre la performance.

## Estructura de carpetas

```
config/config.php (ignorado) + config/config.ejemplo.php (versionado)
includes/db.php, auth.php, header.php, footer.php, funciones.php
modulos/fletes/ combustible/ stock/ pallets/ mantenimiento/ cheques/ tesoreria/ liquidaciones/
portal/          ← solo lectura para la empresa de pallets
assets/css/ assets/js/
database/        ← datos_semilla.sql (Fase 1) y datos_semilla_fase2.sql, reproducibles
docs/            ← planificación, esquema SQL, wireframes, prompts de fase (no modificar)
index.php        ← login
```

## Convenciones de código

- PHP 8+ sin framework, estructura procedural prolija con funciones por módulo.
- **PDO exclusivamente, siempre con prepared statements.** Nada de concatenar SQL.
- `password_hash()` / `password_verify()` para claves. Sesión PHP con verificación de rol en cada página (`admin`, `taller`, `portal_pallets`).
- Transacciones (`beginTransaction/commit/rollBack`) en toda operación que toque más de una tabla (flete + gastos, cheque + movimiento + tesorería, stock).
- Todas las páginas responsive mobile-first: el cliente usa el sistema desde el celular.
- Importes: `DECIMAL` en BD, `number_format($x, 2, ',', '.')` con prefijo `$` para mostrar. Fechas en pantalla: `d/m/Y`.

## Reglas de negocio críticas

- El `pct_comision` se lee de `parametros` **solo al guardar el flete** y se graba en la fila. Nunca se recalcula en vivo en reportes — los reportes siempre usan el `pct_comision` guardado en cada flete, no el parámetro actual.
- El ajuste de viáticos = `MAX(gastos reales − viáticos adelantados, 0)`.
- Las cargas de combustible en cuenta corriente **no mueven caja** hasta que se paga el resumen mensual de la estación (tabla `resumenes_estacion`, pantalla `modulos/combustible/resumenes.php`).
- **Cheques recibidos**, transiciones válidas (ver `transicionChequeRecibidoValida()` en `includes/funciones.php`): `en_cartera → {depositado, vendido, endosado}`, `depositado → {acreditado, rechazado}`, `rechazado → {recuperado}`. **Cheques emitidos** (`transicionChequeEmitidoValida()`): `emitido → {debitado, rechazado}`. Cualquier transición fuera de estos grafos se rechaza en el backend.
- Todo cambio de estado de un cheque genera una fila en `cheques_movimientos` (trazabilidad completa, sin excepciones), vía `registrarMovimientoCheque()`.
- Movimientos de tesorería automáticos (misma transacción que el cambio de estado): `acreditado` → ingreso por el importe en la cuenta de depósito; `vendido` → ingreso por `monto_neto_venta` (no el importe completo); `rechazado` → egreso por `gastos_asociados` si los hubo, categoría "Gastos bancarios"; `debitado` (emitido) → egreso por el importe en la cuenta emisora. `endosado` y `recuperado` no generan movimiento de tesorería.
- Cuando un cheque recibido llega a `acreditado`, `vendido` o `endosado` y tiene `flete_id`, ese flete pasa a `estado_cobro = 'cobrado'`.
- Pagar una liquidación o un resumen de estación genera su egreso en `movimientos_tesoreria` (categoría "Sueldos" y "Combustible" respectivamente) y guarda `movimiento_id` — no hay que cargarlo de nuevo a mano.
- El stock se descuenta vía `movimientos_stock`, actualizando `repuestos.stock_actual` **en la misma transacción**. Nunca puede quedar negativo (se valida antes de guardar). El tipo `ajuste` no es un delta: pide el conteo físico real y el sistema calcula la diferencia contra `stock_actual`.
- El rol `taller` puede ver y registrar movimientos de stock, pero no entra a cheques, tesorería, ni ve montos de fletes/comisiones.

## Diseño

Tokens extraídos de `docs/wireframes-flota.html` (diseño aprobado, no reinventar):

- **Paleta:** asfalto `#23282E`, amarillo vial `#F2B705`, fondo `#E9EBE8`, panel `#FFFFFF`, tinta `#1B1F24`, gris `#69707A`, línea `#D8DBD6`, ok `#2E7D46`, alerta `#C63C3C`, info `#2B5FA3`.
- **Radio de borde:** `10px` en general.
- **Tipografía:** `"Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif`.
- **Botones segmentados** (`.seg`) en lugar de desplegables para elegir camión/chofer/estación/modalidad — máximo **un desplegable por pantalla**.
- **Chips de estado** tipo cartel vial (cartera/depositado/rechazado, etc.), color por estado.
- **Bloque de cálculo automático** (`.auto`, fondo crema `#F6F3E6`, borde punteado amarillo) siempre visible **antes** de guardar — nunca ocultar un cálculo hasta después de confirmar.
- **Mobile-first, ancho de referencia 390px.**
- **Regla de oro:** cada carga (flete, combustible, cheque) se completa en **menos de 30 segundos** desde el celular.

## Fases

- **Fase 1 (construida):** ABM de camiones/choferes/clientes/estaciones, fletes + comisión, gastos de viaje, combustible, listado mensual, liquidación de chofer imprimible (con "marcar pagada"), dashboard con datos reales.
- **Fase 2 (construida):** cuentas y financieras, cheques recibidos (alta, cartera con cambio de estado de un toque, ficha con historial) y emitidos (alta, compromisos futuros por semana), tesorería (posición, listado con filtros, alta manual), resúmenes de estación (con "marcar pagada"), stock de repuestos (vista rápida con buscador + movimientos, ABM separado).
- **Fase 3 (construida):** pallets con portal externo de solo lectura para la empresa de Entre Ríos (recepción/devolución con remito en PDF, numeración correlativa, stock por vista `v_pallets_stock`, usuarios de portal con sesión propia).
- **Fase 4 (en construcción):** mantenimiento de vehículos — tipos de service, planes por camión con intervalo km/meses, registro de service con repuestos y egreso en tesorería, historial, panel de vencimientos con semáforo. La integración GPS TrailingSat quedó descartada (el cliente confirmó que no hay API disponible): no dejar código, menús ni comentarios "preparados para GPS".

## Skills del proyecto

- `.claude/skills/diseno-flota/` → tokens de diseño y reglas de UX. Consultar al crear o modificar cualquier pantalla.
- `.claude/skills/convenciones-php/` → convenciones de código de esta sección. Consultar al escribir PHP o SQL.
- `.claude/skills/logica-negocio/` → las reglas de negocio críticas de arriba, en detalle. Consultar antes de implementar cualquier cálculo de negocio (comisiones, viáticos, cheques, stock).
- Skills externos instalados vía `npx skills add` (php-pro, mysql, frontend-design, owasp-security, sql-code-review, conventional-commit): buenas prácticas generales de PHP, MySQL, frontend, seguridad OWASP y commits. Los tokens y reglas de este `CLAUDE.md` y de los skills locales mandan por sobre cualquier sugerencia genérica de estos skills.

## Reglas de trabajo con Alejandro

- Antes de cada bloque grande de trabajo, avisar en 2–3 líneas qué se va a hacer y esperar el ok si implica una decisión de diseño no cubierta por la documentación.
- Si la planificación y algo que diga Alejandro se contradicen, preguntar antes de asumir.
- Commits chicos y frecuentes, mensajes en español, estilo conventional commits (ej. `feat: alta de fletes con cálculo de comisión`).
- Al terminar cada pantalla, indicar la URL local para probarla (ej. `http://localhost/sistema_flota/modulos/fletes/nuevo.php`).
- `docs/esquema-flota.sql` es el esquema aprobado: no modificarlo ni regenerarlo. Si se detecta un problema real, avisar antes de tocar nada.
