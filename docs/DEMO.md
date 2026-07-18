# Guion de demostración — Sistema de Flota

Guía para mostrarle el sistema a Alejandro y, después, al cliente final. Sigue el orden en que más le duele hoy el papel y Excel: primero lo que carga todos los días (fletes, combustible), después lo que resuelve una vez por mes (liquidaciones, cheques, tesorería), y cierra con lo más nuevo (pallets y mantenimiento).

## Antes de arrancar

Los datos de ejemplo son un mes de operación completo y coherente entre los 4 módulos: fletes que se cobran con cheques, un resumen de combustible pagado, repuestos de stock consumidos en services, y tarimas cuyo stock cuadra con sus remitos.

Para regenerar estos datos en cualquier momento (por ejemplo, antes de otra demo, para que las fechas relativas — "el mes pasado", "hoy", "hace X días" — sigan siendo coherentes):

```
php database/generar_datos_demo.php
```

Esto **borra y vuelve a cargar solo las tablas transaccionales** (fletes, combustible, cheques, tesorería, remitos, mantenimiento). Los maestros (camiones, choferes, clientes, estaciones, cuentas, repuestos, tipos de service, usuarios) no se tocan.

Login: usuario `alejandro`, rol admin.

---

## 1. Nuevo flete (lo que más le duele hoy)

`modulos/fletes/nuevo.php`

- Mostrar la carga de un flete: camión y chofer como botones (no desplegables), comisión calculada sola apenas se completa el importe — el bloque amarillo con el cálculo está visible **antes** de guardar, nunca es una sorpresa después.
- Guardar y elegir "Guardar y cargar gastos del viaje" para mostrar `gastos.php`: ahí se compara el viático adelantado contra los gastos reales, en vivo.
- Volver a `listado.php` y filtrar por camión — mostrar los fletes del mes en curso (4 cargados, todavía sin liquidar) y los del mes pasado (7, ya liquidados).

## 2. Combustible

`modulos/combustible/nuevo.php`

- Cargar una carga de combustible: estación como botón, cuenta corriente vs. contado según la estación elegida.
- El control antirrobo: al cargar el km, el sistema compara el consumo del tramo contra el promedio histórico del camión y avisa si se desvía — esto es lo que hoy se hace a ojo con la planilla.
- `resumenes.php`: el resumen mensual de la estación con cuenta corriente, ya cargado y marcado como pagado (generó su egreso en tesorería solo).

## 3. Liquidación de chofer

`modulos/liquidaciones/nueva.php`

- Elegir un chofer y el período del mes pasado: la liquidación ya está cerrada, con el detalle de comisiones + ajuste de viáticos (gastos reales vs. adelantado) y el total a pagar.
- Uno de los tres choferes tiene su liquidación pagada (con el botón "Marcar pagada" ya usado), otro la tiene cerrada pero pendiente de pago — para mostrar los dos estados.
- Botón "Imprimir": la liquidación queda lista para entregarle al chofer en papel si hace falta.

## 4. Cheques y tesorería

`modulos/cheques/cartera.php`

- Cartera con cheques recibidos en distintos estados: dos todavía en cartera sin resolver, dos ya acreditados (con el flete correspondiente marcado como cobrado automáticamente), uno vendido a una financiera, uno endosado a un proveedor, y uno que fue rechazado y después recuperado — la ficha de ese cheque (`ficha.php`) muestra el historial completo de movimientos.
- `emitidos.php`: un cheque propio ya debitado y otro pendiente, en "Compromisos futuros".
- `modulos/tesoreria/listado.php`: todos estos movimientos (cobros, pagos de sueldos, pago del resumen de combustible, gastos de mantenimiento) aparecen acá, con el saldo actual y la posición (por entrar / por salir).

## 5. Stock de repuestos

`modulos/stock/index.php`

- Buscador rápido de repuestos. Mostrar un repuesto bajo mínimo (hay varios, en rojo) — esto reemplaza el "me quedé sin filtros y no lo sabía" de hoy.
- La trazabilidad completa de qué repuesto salió para qué service se ve desde Mantenimiento (punto 7): cada movimiento de stock queda linkeado al service que lo generó.

## 6. Pallets (portal para la empresa de Entre Ríos)

`modulos/pallets/nuevo.php` → `portal/index.php`

- Un remito de recepción y uno de devolución, con el PDF generado en el momento (mismo formato que el talonario de papel que usan hoy).
- Abrir el portal externo en otra pestaña con el usuario `portal_demo` (clave según lo que Alejandro haya definido) para mostrarle a la empresa de Entre Ríos cómo ve su propio stock de tarimas en tiempo real, sin poder tocar nada.

## 7. Mantenimiento (lo más nuevo)

`modulos/mantenimiento/vencimientos.php` — **la pantalla estrella del módulo**

- Los 3 camiones con sus planes de service y el semáforo: hay un vencido (rojo), dos por vencer (amarillo) y el resto al día (verde) — de un vistazo se sabe qué camión hay que llevar al taller esta semana, sin revisar cuadernos.
- Tocar "Registrar service" desde un ítem vencido: el camión y el tipo de service ya vienen precargados.
- `historial.php`: la línea de tiempo de un camión, con los repuestos que salieron de stock para cada service y el costo total del período.
- Mencionar que el semáforo también aparece resumido en el dashboard de inicio (solo lo urgente), y que la integración GPS quedó descartada — el kilometraje sale de las cargas de combustible, que ya se cargan todos los días.

---

## Cierre

Volver al dashboard (`dashboard.php`): en una sola pantalla — fletes del mes, consumo de combustible, posición de tesorería, cheques por vencer, stock bajo mínimo, tarimas y el semáforo de mantenimiento. Todo lo que hoy vive en cuadernos y planillas separadas, en un lugar, actualizado en el momento en que se carga.
