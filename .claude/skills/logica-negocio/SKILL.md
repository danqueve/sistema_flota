---
name: logica-negocio
description: consultar antes de implementar cualquier cálculo de negocio
---

# Reglas de negocio críticas — Sistema de Flota

Estas reglas vienen confirmadas por el cliente (`docs/planificacion-sistema-flota.md`) y ya están materializadas en `docs/esquema-flota.sql`. No son negociables sin que Alejandro lo apruebe explícitamente.

## Comisión del chofer

- El porcentaje vigente vive en `parametros` (clave `pct_comision_chofer`), es único y global para todos los choferes, y se calcula **sobre el importe bruto del flete**.
- Al guardar un flete nuevo, se **lee el % vigente de `parametros` en ese momento y se copia** en `fletes.pct_comision`. `fletes.comision_chofer` se calcula y graba también en ese momento (`importe_bruto * pct_comision / 100`).
- **Nunca recalcular la comisión en vivo en reportes o listados** usando el parámetro actual — siempre se usa el `pct_comision` y `comision_chofer` ya guardados en cada fila de `fletes`. Si el parámetro cambia mañana (ej. 15% → 16%), los fletes viejos conservan su porcentaje histórico intacto.

## Viáticos

- `fletes.viatico_adelanto` es lo que se le da al chofer al salir de viaje.
- Los gastos reales del viaje se cargan aparte en `gastos_viaje` (no confundir con el adelanto).
- En la liquidación mensual (`liquidaciones`): `ajuste_viaticos = MAX(gastos_reales − viaticos_adelantados, 0)`. Es decir, solo se ajusta a favor del chofer si los gastos reales superaron el adelanto; si el chofer gastó menos de lo adelantado, el ajuste es 0 (no se le reclama la diferencia).

## Combustible y cuenta corriente de estaciones

- `cargas_combustible.modalidad` distingue `cta_cte` de `contado`.
- Las cargas en `cta_cte` **no generan movimiento de caja/tesorería en el momento** — se acumulan por estación (`estacion_id`) y se cruzan contra `resumenes_estacion` (una fila por estación y período `'YYYY-MM'`).
- Solo al marcar un `resumenes_estacion.pagado = 1` se genera el egreso correspondiente en `movimientos_tesoreria` (y se guarda su id en `resumenes_estacion.movimiento_id`).
- El control antirrobo: al guardar una carga con `km`, calcular el consumo del tramo (litros / (km_actual − km_carga_anterior_del_mismo_camión) * 100) y compararlo contra el promedio histórico de ese camión. Si se desvía notoriamente, mostrar alerta visual inmediata (ver [[diseno-flota]], bloque `.consumo.malo`) — es el pedido explícito del cliente para detectar robos de combustible.

## Cheques

- Todo cheque (`recibido` o `emitido`) tiene un ciclo de vida por `estado`, validado en el backend (`transicionChequeRecibidoValida()` / `transicionChequeEmitidoValida()` en `includes/funciones.php`) — cualquier transición fuera del grafo se rechaza:
  - **Recibidos:** `en_cartera → {depositado, vendido, endosado}`; `depositado → {acreditado, rechazado}`; `rechazado → {recuperado}`.
  - **Emitidos:** `emitido → {debitado, rechazado}`.
- **Todo cambio de estado, sin excepción, genera una fila nueva en `cheques_movimientos`** (vía `registrarMovimientoCheque()`) con `estado_anterior`, `estado_nuevo`, `usuario_id` y fecha — es trazabilidad pedida explícitamente por el cliente, no un detalle opcional.
- Movimientos de tesorería automáticos, misma transacción que el cambio de estado:
  - `acreditado` → ingreso por el importe en `cuenta_deposito_id`.
  - `vendido` → ingreso por `monto_neto_venta` (no el importe completo del cheque — la diferencia es el costo del descuento de la financiera, se muestra informativamente al cargar la venta).
  - `rechazado` → egreso por `gastos_asociados` si los hubo, categoría "Gastos bancarios", en la cuenta donde estaba depositado (o emisora si es un cheque propio).
  - `debitado` (cheque propio) → egreso por el importe en la cuenta emisora.
  - `endosado` y `recuperado` **no** generan movimiento de tesorería.
- Si un cheque recibido con `flete_id` llega a `acreditado`, `vendido` o `endosado`, ese flete pasa a `fletes.estado_cobro = 'cobrado'` — el pago del cliente quedó resuelto, más allá del costo del descuento que haya asumido la empresa.
- Cambiar de estado es una acción de un clic desde la lista (ver [[diseno-flota]]), no un formulario nuevo — como mucho abre un mini-diálogo con el dato puntual que ese cambio necesita (cuenta al depositar, financiera + neto + cuenta destino al vender, destinatario al endosar, gastos al rechazar).

## Tesorería

- `v_posicion` calcula saldo actual + cheques por entrar (recibidos en cartera/depositados) + cheques por salir (emitidos pendientes) a partir de `cuentas`, `movimientos_tesoreria` y `cheques` — no se mantiene como tabla, se recalcula siempre desde el estado real.
- Pagar una **liquidación** (`estado: cerrada → pagada`) o un **resumen de estación** (`pagado: 0 → 1`) genera su egreso en `movimientos_tesoreria` (categoría "Sueldos" y "Combustible" respectivamente) y guarda `movimiento_id` en la fila de origen — nunca se carga ese egreso a mano por separado.
- Los movimientos manuales (alta desde `modulos/tesoreria/nuevo.php`) usan `referencia_tipo = 'otro'`; los automáticos usan `'cheque'`, `'liquidacion'`, `'resumen_estacion'` o `'stock'` con su `referencia_id`.

## Stock de repuestos

- El stock nunca se edita directamente en `repuestos.stock_actual` a mano: **todo movimiento pasa por `movimientos_stock`** (`tipo`: ingreso/egreso/ajuste), y `repuestos.stock_actual` se actualiza **en la misma transacción** que inserta el movimiento. Si la actualización del stock falla, se hace `rollBack()` del movimiento también.
- **El stock nunca puede quedar negativo**: un egreso que superaría el stock disponible se rechaza antes de guardar, mostrando cuánto hay realmente.
- El tipo `ajuste` **no es un delta**: se pide el conteo físico real (no una cantidad a sumar/restar) y el sistema calcula la diferencia contra `stock_actual` para decidir el signo y la magnitud del movimiento guardado.
- Un egreso de stock puede asociarse a un `camion_id` y opcionalmente a un `service_id` — eso alimenta el historial de mantenimiento (Módulo 4).
- El rol `taller` puede ver stock y registrar movimientos, pero no entra a cheques, tesorería, ni ve montos de fletes/comisiones — verificar con `requerirRol()` en cada página nueva de estos módulos.

## Pallets

- Solo el dueño (admin) genera movimientos (`pallets_movimientos` vía `remitos`); la empresa externa de Entre Ríos únicamente lee (rol `portal_pallets`), en una sesión y autenticación completamente separadas del sistema interno (`portal/includes/auth_portal.php`).
- El stock por estado (sano/roto/reacondicionado/separador) es una vista calculada (`v_pallets_stock`) a partir de recepciones menos devoluciones — no una tabla que se actualiza a mano.
- Cada remito genera su PDF (Dompdf) replicando el talonario físico, servido siempre por un script con sesión (nunca por URL directa a un archivo).

## Mantenimiento (Fase 4)

- Cada plan (`planes_mantenimiento`, único por `camion_id` + `tipo_service_id`) define `intervalo_km` y/o `intervalo_meses`. **Vence lo que ocurra primero**: se calculan ambos porcentajes de intervalo restante (si el plan tiene ambos datos) y se toma el mínimo — ver `calcularVencimientosMantenimiento()` en `includes/funciones.php`, compartida entre `vencimientos.php` y el dashboard para no duplicar la lógica.
- El **semáforo** usa el umbral `parametros.pct_alerta_service` (default 20): verde si falta más del umbral del intervalo, amarillo si queda ese % o menos, rojo si ya venció (porcentaje ≤ 0).
- El "último service" de cada camión+tipo se determina por la fecha más reciente en `services` (no hay columna de estado ni de "vigente": se recalcula siempre desde el historial completo).
- Si un plan se crea para un camión+tipo **sin historial previo**, hace falta un "punto de partida" (km y fecha del último service conocido): se graba como una fila real en `services` con `taller`/`costo` en `NULL` — no se agrega ninguna columna nueva al esquema para esto.
- Un service puede asociar egresos de stock (`movimientos_stock.service_id`) que descuentan `repuestos.stock_actual` en la misma transacción, y opcionalmente un egreso en `movimientos_tesoreria` (categoría "Mantenimiento", `referencia_tipo = 'otro'` porque el enum de esquema no tiene un valor dedicado para "service").
- `camiones.km_actual` es la fuente de verdad del odómetro: la actualiza tanto una carga de combustible con km como un service con km. Un km menor al actual nunca se acepta en silencio — requiere confirmación explícita (odómetro reemplazado o corrección de un error previo).
- No hay integración GPS (TrailingSat no tiene API, descartado definitivamente): el kilometraje siempre sale de una carga cargada a mano.
