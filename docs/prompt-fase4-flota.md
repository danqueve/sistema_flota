# PROMPT FASE 4 — pegar en Claude Code (proyecto sistema_flota)

> **Antes de pegar:** Fase 3 commiteada y aprobada. Esta es la fase final: mantenimiento de vehículos + pulido general para dejar el sistema listo para presentar al cliente.

---

La **Fase 3 está terminada**. Arrancamos la **Fase 4: mantenimiento de vehículos + pulido de presentación**. Dato confirmado: **TrailingSat NO tiene API** (sistema cerrado), así que la integración GPS queda **descartada definitivamente** — la fuente de kilometraje del sistema es el km cargado en cada carga de combustible, que ya existe desde la Fase 1. No dejes código, menús ni comentarios "preparados para GPS".

## PASO 0 — Recuperar contexto

1. Leé `CLAUDE.md` completo.
2. Releé en `docs/planificacion-sistema-flota.md` la sección **"Módulo 4 — Mantenimiento de vehículos"**.
3. Revisá en `docs/esquema-flota.sql`: `tipos_service`, `planes_mantenimiento`, `services`, la FK `movimientos_stock.service_id`, y `camiones.km_actual`. El esquema NO se modifica en esta fase.
4. Confirmame en un párrafo el alcance antes de codear.

## Reglas de negocio de esta fase

- **`camiones.km_actual` es la fuente de verdad del odómetro** y se actualiza en dos lugares: al guardar una carga de combustible con km (ya existe — verificá que efectivamente lo actualice; si no, corregilo) y al registrar un service con km. Nunca aceptar un km menor al actual sin una confirmación explícita ("¿el odómetro fue reemplazado o es un error de tipeo?").
- **Cálculo del próximo vencimiento** por camión + tipo de service: último service registrado + `intervalo_km` y/o + `intervalo_meses` — vence lo que ocurra PRIMERO. Si no hay service previo registrado, el plan pide un "punto de partida" (km y fecha del último service conocido) al crearlo.
- **Semáforo**: verde = falta más del 20% del intervalo; amarillo = quedan 20% o menos (km o días); rojo = vencido. El umbral del 20% va como parámetro en `parametros` (`pct_alerta_service`, default 20).
- **Repuestos del service**: al registrar un service se pueden asociar egresos de stock (reutilizá el flujo de `movimientos_stock` de la Fase 2 con `service_id`), y el costo del service puede cargarse manual o sugerirse desde el costo de los repuestos usados + mano de obra.
- El **costo del service** genera (opcionalmente, con un check) un egreso en tesorería, categoría "mantenimiento".

## Construcción (en orden, cada pantalla con su URL antes de seguir)

1. **ABM de tipos de service** (`modulos/mantenimiento/tipos.php`) con datos semilla: cambio de aceite y filtros, frenos, embrague, tren delantero, cubiertas, revisión general.
2. **Planes de mantenimiento** (`planes.php`): por camión, tipo + intervalo km y/o meses, con el "punto de partida" si no hay historial. Vista de todos los planes de los 3 camiones en una sola pantalla.
3. **Registro de service** (`nuevo.php`): camión, tipo, fecha, km (validado contra `km_actual`), taller, costo, observaciones, repuestos usados (buscador sobre el stock), y el check de egreso en tesorería. Mobile-first como todo lo demás.
4. **Historial por camión** (`historial.php`): línea de tiempo de services con costos, filtrable por tipo, con total gastado en mantenimiento por período.
5. **Panel de vencimientos** (`vencimientos.php`): los 3 camiones × sus planes con el semáforo, ordenado por urgencia. Es la pantalla estrella del módulo.
6. **Dashboard**: reemplazá el último placeholder por el semáforo de services (solo amarillos y rojos, con link al panel).
7. **Pulido de presentación** (el sistema se le muestra al cliente por primera vez después de esta fase):
   - Recorré TODAS las pantallas de las 4 fases y verificá consistencia: mismos tokens de diseño, mismos formatos de importe (`$ 1.234.567,89`) y fecha (`d/m/Y`), mismos textos de botones, ningún texto en inglés, ningún `var_dump`/`console.log` olvidado.
   - Pantalla de error amigable (404/500) con el estilo del sistema.
   - Datos semilla finales coherentes entre TODOS los módulos (los fletes cobran con los cheques cargados, los services usan repuestos del stock, las tarimas cuadran con sus remitos) — es lo que voy a usar para la demo con el cliente, tiene que contar una historia creíble de un mes de operación.
   - Un `docs/DEMO.md` con el guion de demostración: en qué orden mostrar las pantallas para la presentación (arrancando por "nuevo flete" que es lo que más le duele hoy), con los datos semilla como ejemplo.
8. **Checklist de deploy** en `docs/DEPLOY.md`: pasos exactos para subir al VPS (clonar, `composer install`, config.php, importar esquema + migraciones de `docs/migraciones/`, permisos de `archivos/remitos/`, HTTPS, crear el usuario admin real y el del portal).

## Refinamientos reservados (NO los hagas ahora)

El cliente todavía no vio el sistema. Cuando lo use van a surgir ajustes; esta lista queda documentada en el CLAUDE.md como "Backlog post-presentación" para encararlos después:
- Puente peajes del remito → gastos del viaje (Módulo 1).
- Conciliación bancaria simple (tildar movimientos contra el resumen del banco).
- Lo que surja de la presentación.

## Reglas de trabajo (las de siempre)

- Transacciones PDO en service+stock+tesorería; commits conventional en español; frená ante decisiones no documentadas; URL local por pantalla.

Empezá por el PASO 0.
