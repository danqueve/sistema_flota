# PROMPT MAESTRO — pegar en Claude Code

> **Antes de pegar:** creá la carpeta `C:\wamp64\www\sistema_flota`, adentro una subcarpeta `docs\` y copiá ahí los 3 archivos: `planificacion-sistema-flota.md`, `esquema-flota.sql` y `wireframes-flota.html`. Abrí la carpeta en VS Code, iniciá Claude Code y pegá todo lo que sigue.

---

Sos el desarrollador principal de un nuevo proyecto: **Sistema de Gestión de Flota** para un transportista de Tucumán, Argentina (3 camiones). Yo soy Alejandro, desarrollador PHP/MySQL, trabajo en Windows con WAMP64 local, deploy a un VPS AlmaLinux con cPanel vía `git pull origin main`. Todo el código, comentarios, nombres de variables y textos de la interfaz van **en español**.

## PASO 0 — Leer la documentación del proyecto

Antes de escribir una sola línea de código, leé completos estos tres archivos en `docs/`:

1. `docs/planificacion-sistema-flota.md` → la planificación completa: 5 módulos, definiciones confirmadas por el cliente, fases y decisiones de diseño. **Es la fuente de verdad del proyecto.**
2. `docs/esquema-flota.sql` → el modelo de datos ya diseñado (24 tablas + 2 vistas). NO lo modifiques ni lo regeneres: es el esquema aprobado. Si detectás un problema real, avisame antes de tocar nada.
3. `docs/wireframes-flota.html` → el diseño aprobado de las pantallas de carga. Abrilo y respetá: paleta de colores (asfalto #23282E, amarillo vial #F2B705, fondo #E9EBE8), botones segmentados en lugar de desplegables para camión/chofer/estación, chips de estado, y la regla de oro: **cada carga se completa en menos de 30 segundos desde un celular**.

## PASO 1 — Configuración del proyecto (hacelo primero, en este orden)

1. **Creá `CLAUDE.md`** en la raíz con: descripción del proyecto, stack, convenciones (abajo), estructura de carpetas, y un resumen de las definiciones del cliente extraídas de la planificación (comisión 15% sobre bruto copiada al flete, viáticos adelanto+ajuste, cta. cte. en estaciones, 2 bancos, financieras variables, choferes sin acceso). Así cualquier sesión futura arranca con contexto.

2. **Creá los skills del proyecto** en `.claude/skills/` (cada uno es una carpeta con su `SKILL.md` con frontmatter `name` y `description`):
   - `.claude/skills/diseno-flota/SKILL.md` → los tokens de diseño extraídos de `docs/wireframes-flota.html` (paleta, tipografía, radios, componentes: botón segmentado, chip de estado, tarjeta de cálculo automático, barra total) y las reglas de UX (mobile-first 390px, un desplegable máximo por pantalla, cálculos visibles antes de guardar). Descripción: "usar siempre que se cree o modifique una pantalla".
   - `.claude/skills/convenciones-php/SKILL.md` → las convenciones de código del punto siguiente. Descripción: "usar siempre que se escriba código PHP o SQL".
   - `.claude/skills/logica-negocio/SKILL.md` → las reglas de negocio críticas: el `pct_comision` se lee de `parametros` SOLO al guardar el flete y se graba en la fila (nunca calcular en vivo en reportes); el ajuste de viáticos = MAX(gastos reales − viáticos adelantados, 0); las cargas en cta. cte. no mueven caja hasta pagar el resumen; todo cambio de estado de cheque genera fila en `cheques_movimientos`; el rechazo de cheque registra gastos y genera egreso en tesorería; stock se descuenta vía `movimientos_stock` actualizando `repuestos.stock_actual` en la misma transacción. Descripción: "consultar antes de implementar cualquier cálculo de negocio".

3. **Inicializá git**: `git init`, rama `main`, `.gitignore` con `config/config.php`, `/vendor/`, `*.log`, `node_modules/`. Primer commit tras el scaffold.

## PASO 2 — Convenciones de código (van también al skill)

- PHP 8+ sin framework, estructura procedural prolija con funciones por módulo (mismo estilo que un sistema de gestión clásico). Sin Composer salvo dompdf cuando lleguemos a PDFs.
- **PDO exclusivamente, siempre con prepared statements.** Nada de concatenar SQL.
- `password_hash()` / `password_verify()` para claves. Sesiones PHP con verificación de rol en cada página (`admin`, `taller`, `portal_pallets`).
- Transacciones (`beginTransaction/commit/rollBack`) en toda operación que toque más de una tabla (flete+gastos, cheque+movimiento+tesorería, stock).
- Frontend: HTML + CSS propio siguiendo los wireframes + JavaScript vanilla. **Sin frameworks JS.** Chart.js (desde cdnjs) solo para las gráficas de consumo y facturación.
- Todas las páginas responsive mobile-first: el cliente usa el sistema desde el celular.
- Importes: `DECIMAL` en BD, `number_format($x, 2, ',', '.')` con prefijo `$` para mostrar. Fechas en pantalla: `d/m/Y`.
- Estructura de carpetas:

```
config/config.php (ignorado) + config/config.ejemplo.php (versionado)
includes/db.php, auth.php, header.php, footer.php, funciones.php
modulos/fletes/ combustible/ stock/ pallets/ mantenimiento/ cheques/ liquidaciones/
portal/          ← solo lectura para la empresa de pallets
assets/css/ assets/js/
index.php        ← login
```

## PASO 3 — Base de datos

Base local: `sistema_flota` en MySQL de WAMP (usuario `root`, sin clave, host `localhost`). Verificá si existe; si no, creala con `utf8mb4_unicode_ci` y ejecutá `docs/esquema-flota.sql`. Después insertá datos semilla realistas para desarrollo: 3 camiones, 3 choferes, 4 clientes (uno con `es_portal_pallets=1`, de Entre Ríos), 3 estaciones (2 con cta. cte.), 2 cuentas banco + 1 caja, categorías de gasto típicas (viáticos, playa, peaje, sueldos, repuestos, gastos bancarios), tipos de service, y un usuario admin (usuario: `alejandro`) pidiéndome a mí la clave por consola antes de hashearla.

## PASO 4 — Construcción (Fase 1 de la planificación)

Construí en este orden, mostrándome cada pantalla terminada antes de seguir:

1. **Login + layout base** (header con menú por rol, footer, CSS con los tokens del skill de diseño).
2. **ABM de camiones, choferes, clientes y estaciones** (pantallas simples, prueban toda la base).
3. **Nuevo flete** — replicar exactamente el wireframe 1: botones segmentados para camión y chofer, comisión calculada en vivo con JS (leyendo el % vigente) y guardada en el POST releyéndola de `parametros`, campo de viático adelantado, botón secundario "Guardar y cargar gastos del viaje".
4. **Gastos del viaje** (alta rápida por categoría sobre un flete).
5. **Carga de combustible** — wireframe 2: modalidad cta.cte./contado, cálculo del consumo del tramo vs. promedio histórico del camión con alerta visual inmediata, acumulado mensual de la cta. cte. de la estación al pie.
6. **Listado de fletes del mes** con filtros por camión/chofer y totales.
7. **Liquidación mensual del chofer**: comisiones del período + ajuste de viáticos, cierre que marca los fletes con `liquidacion_id`, y versión imprimible (CSS print) para entregarle al chofer.
8. **Dashboard inicial**: fletes del mes por camión, gráfica de consumo (Chart.js), cheques próximos a vencer (placeholder por ahora).

Las Fases 2–4 (cheques/tesorería, stock, pallets con portal, mantenimiento) vienen después: NO las empieces hasta que yo apruebe la Fase 1, pero dejá el menú y las carpetas preparadas.

## Reglas de trabajo conmigo

- Antes de cada bloque grande, contame en 2–3 líneas qué vas a hacer y esperá mi ok si implica una decisión de diseño no cubierta por la documentación.
- Si la planificación y algo que yo diga se contradicen, preguntame.
- Commits chicos y frecuentes con mensajes en español ("feat: alta de fletes con cálculo de comisión").
- Al terminar cada pantalla, decime la URL local para probarla (ej. `http://localhost/sistema_flota/modulos/fletes/nuevo.php`).

Empezá por el PASO 0: leé los tres documentos y confirmame en un párrafo qué entendiste del proyecto antes de crear nada.
