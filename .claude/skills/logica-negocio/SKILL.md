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

- Todo cheque (`recibido` o `emitido`) tiene un ciclo de vida por `estado`. Recibidos: `en_cartera` → `depositado` → `acreditado`/`rechazado` (o `vendido`/`endosado` directo desde cartera); `rechazado` puede pasar a `recuperado`. Emitidos: `emitido` → `debitado`/`rechazado`.
- **Todo cambio de estado, sin excepción, genera una fila nueva en `cheques_movimientos`** con `estado_anterior`, `estado_nuevo`, `usuario_id` y fecha — es trazabilidad pedida explícitamente por el cliente, no un detalle opcional.
- Al marcar un cheque como **rechazado**: registrar los gastos/comisiones asociados en `cheques_movimientos.gastos_asociados` y generar automáticamente un **egreso** en `movimientos_tesoreria` (categoría gastos bancarios) por ese importe.
- Al **acreditar** o **vender** un cheque recibido: generar automáticamente un **ingreso** en `movimientos_tesoreria` (en el banco de depósito, o el neto recibido si se vendió a una financiera).
- Cambiar de estado es una acción de un clic desde la lista (ver [[diseno-flota]]), no un formulario nuevo — como mucho pide el dato puntual que ese cambio necesita (banco al depositar, financiera + neto al vender, gastos al rechazar).

## Stock de repuestos

- El stock nunca se edita directamente en `repuestos.stock_actual` a mano: **todo movimiento pasa por `movimientos_stock`** (`tipo`: ingreso/egreso/ajuste), y `repuestos.stock_actual` se actualiza **en la misma transacción** que inserta el movimiento. Si la actualización del stock falla, se hace `rollBack()` del movimiento también.
- Un egreso de stock puede asociarse a un `camion_id` y opcionalmente a un `service_id` — eso alimenta el historial de mantenimiento (Módulo 4).

## Pallets (Fase 3, no implementar aún)

- Solo el dueño (admin) genera movimientos (`pallets_movimientos` vía `remitos`); la empresa externa de Entre Ríos únicamente lee (rol `portal_pallets`).
- El stock por estado (sano/roto/reacondicionado/separador) es una vista calculada (`v_pallets_stock`) a partir de recepciones menos devoluciones — no una tabla que se actualiza a mano.

## Posición financiera (Fase 2, no implementar aún)

- `v_posicion` calcula saldo actual + cheques por entrar (recibidos en cartera/depositados) + cheques por salir (emitidos pendientes) a partir de `cuentas`, `movimientos_tesoreria` y `cheques` — no se mantiene como tabla, se recalcula siempre desde el estado real.
