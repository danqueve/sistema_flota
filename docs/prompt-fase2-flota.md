# PROMPT FASE 2 — pegar en Claude Code (proyecto sistema_flota)

> **Antes de pegar:** verificá que la Fase 1 esté commiteada y funcionando, y que el `CLAUDE.md` y la carpeta `docs/` sigan en su lugar.

---

La **Fase 1 está terminada y aprobada por el cliente**. Arrancamos la **Fase 2: módulo de cheques y tesorería + stock de repuestos**.

## PASO 0 — Recuperar contexto

1. Leé `CLAUDE.md` completo (convenciones, reglas de negocio críticas, tokens de diseño).
2. Releé en `docs/planificacion-sistema-flota.md` las secciones **"Módulo 5 — Cheques y tesorería"** y **"Módulo 2 — Stock de repuestos y cubiertas"**: son el alcance exacto de esta fase.
3. Abrí `docs/wireframes-flota.html` y estudiá las pantallas 3 (**Nuevo cheque recibido**) y 4 (**Cheques en cartera**): son el diseño aprobado a replicar.
4. Revisá en `docs/esquema-flota.sql` las tablas que vas a usar: `cuentas`, `financieras`, `cheques`, `cheques_movimientos`, `movimientos_tesoreria`, `repuestos`, `movimientos_stock`, la vista `v_posicion`, y las FK de `liquidaciones.movimiento_id` y `resumenes_estacion.movimiento_id`. El esquema NO se modifica; si encontrás un problema real, avisame antes.
5. Confirmame en un párrafo qué vas a construir antes de escribir código.

## Reglas de negocio de esta fase (además de las del CLAUDE.md)

- **Trazabilidad obligatoria**: TODO cambio de estado de un cheque inserta una fila en `cheques_movimientos` (estado anterior, nuevo, usuario, fecha), dentro de la misma transacción.
- **Estados válidos y sus transiciones** (cheques recibidos): `en_cartera` → `depositado` | `vendido` | `endosado`; `depositado` → `acreditado` | `rechazado`; `rechazado` → `recuperado`. Cheques emitidos: `emitido` → `debitado` | `rechazado`. Rechazá en backend cualquier transición fuera de este grafo.
- **Movimientos de tesorería automáticos** (misma transacción que el cambio de estado):
  - `acreditado` → ingreso por el importe en la cuenta del depósito.
  - `vendido` → ingreso por `monto_neto_venta` (pedirlo en el diálogo de venta junto con la financiera; la diferencia con el importe es el costo del descuento, mostrarla informativamente).
  - `rechazado` → egreso por `gastos_asociados` (comisiones bancarias) si se informaron, categoría "gastos bancarios".
  - `debitado` (cheque propio) → egreso por el importe en la cuenta emisora.
- **Nada de doble carga**: pagar una liquidación de chofer (Fase 1) o un resumen de estación ahora genera su egreso en `movimientos_tesoreria` y guarda el `movimiento_id` — agregá esos botones "Marcar como pagada/o" a las pantallas existentes de liquidaciones y resúmenes sin rediseñarlas.
- **Stock**: los egresos siempre indican camión (y opcionalmente service); `repuestos.stock_actual` se actualiza en la misma transacción que el insert en `movimientos_stock`. Nunca permitir stock negativo (validar y avisar).
- **Rol `taller`**: puede ver stock y registrar movimientos; NO ve cheques, tesorería ni montos de fletes. Verificalo en cada página nueva.

## Construcción (en este orden, mostrándome cada pantalla con su URL antes de seguir)

1. **ABM de cuentas y financieras** (`modulos/cheques/cuentas.php`, `financieras.php`): las dos cuentas banco + caja, y el ABM simple de financieras (se cargan a medida que aparecen).
2. **Nuevo cheque recibido** — replicar el wireframe 3 exacto: toggle Físico/ECHEQ, 6 campos, vínculo opcional a flete, estado inicial `en_cartera` puesto por el sistema, días al cobro calculados en vivo con JS.
3. **Cheques en cartera** — wireframe 4: lista con acciones de un toque. "Depositar" abre un mini-diálogo que solo pregunta la cuenta; "Vender" pide financiera + neto recibido; "Endosar" pide destinatario. Vencimientos ≤ 7 días resaltados en rojo automáticamente. Nunca un formulario de página completa para cambiar estado.
4. **Cheques emitidos**: alta (cuenta emisora, destinatario, importe, fecha de débito) + vista de **compromisos futuros** ordenada por fecha de débito con total acumulado por semana ("cuánto me va a debitar el banco y cuándo").
5. **Ficha del cheque**: historial completo desde `cheques_movimientos` (línea de tiempo simple), para responder "¿qué pasó con este cheque?".
6. **Rechazo y recuperación**: acción "Rechazado" desde depositado (pide gastos/comisiones → egreso automático) y "Recuperado" desde rechazado.
7. **Tesorería** (`modulos/tesoreria/`): listado de movimientos con filtros (cuenta, tipo, categoría, período), alta manual de ingresos/egresos con categoría, y cabecera con la **posición** desde `v_posicion`: saldo actual + por entrar (cheques en cartera/depositados) + por salir (emitidos pendientes).
8. **Stock de repuestos** (`modulos/stock/`): ABM de repuestos/cubiertas, ingreso de compras, egreso con camión destino, listado con buscador rápido pensado para celular (el dueño consulta desde la ruta), alerta visual cuando `stock_actual <= stock_minimo`.
9. **Integraciones con Fase 1**: botón "Marcar pagada" en liquidaciones y en resúmenes de estación (egreso + `movimiento_id`), y reemplazo del placeholder del dashboard por los datos reales: cheques que vencen en 7 días, posición de tesorería, y repuestos bajo mínimo.
10. **Datos semilla** para probar: 6-8 cheques recibidos en distintos estados (incluí un rechazado con gastos y uno vendido), 3 emitidos, 10 repuestos con algunos bajo mínimo, movimientos de tesorería coherentes con esos cheques.

## Reglas de trabajo (las mismas de la Fase 1)

- Cada operación multi-tabla dentro de una transacción PDO con rollback.
- Commits chicos con conventional commits en español.
- Antes de cada bloque, 2-3 líneas de qué vas a hacer; frená y preguntame ante cualquier decisión no cubierta por la documentación.
- Al terminar cada pantalla, pasame la URL local para probarla.
- NO empieces la Fase 3 (pallets + portal) ni la Fase 4 (mantenimiento): solo dejá sus entradas de menú deshabilitadas como hasta ahora.

Empezá por el PASO 0.
