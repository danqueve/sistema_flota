# Planificación — Sistema de Gestión de Flota de Camiones

**Cliente:** Transportista (3 camiones), referido por Diego
**Fecha:** Julio 2026
**Situación actual:** registros en cuadernos y planillas de Excel, remitos de pallets hechos a mano
**Volumen:** entre 4 y 15 viajes por camión por mes (12–45 fletes mensuales en total) — volumen bajo: prioriza simplicidad de carga sobre performance

---

## 1. Resumen del pedido

Una única plataforma web (accesible desde PC y celular) con cuatro módulos:

| # | Módulo | Objetivo principal |
|---|--------|-------------------|
| 1 | Fletes y liquidación de choferes | Registrar viajes, calcular comisión del chofer, controlar combustible y gastos, cierre mensual por camión |
| 2 | Stock de repuestos y cubiertas | Saber qué hay en el taller desde cualquier lado, con baja automática al usar |
| 3 | Pallets (portal para la empresa de Entre Ríos) | Remitos digitales y visibilidad en tiempo real del stock de pallets para el cliente externo |
| 4 | Mantenimiento de vehículos | Historial de services y alertas por kilometraje/fecha |
| 5 | Cheques y tesorería | Trazabilidad de cheques recibidos y emitidos, estado bancario y destino del dinero |

**Usuarios previstos:**
- **Dueño (admin):** acceso total, único que modifica pallets.
- **Jefe de taller:** consulta stock, registra uso de repuestos (con autorización).
- **Empresa externa (Entre Ríos):** solo lectura del módulo de pallets.
- **Choferes:** sin acceso — confirmado por el cliente: todo pasa por el dueño.

---

## 2. Módulo 1 — Fletes y liquidación de choferes

### Funcionalidades
- ABM de camiones (móviles), choferes y clientes/destinos.
- Carga de fletes: fecha, camión, chofer, origen/destino, importe. Cada flete tiene precio propio.
- **Comisión del chofer (definido):** porcentaje único para todos los choferes, calculado **sobre el bruto del flete**. Arranca en 15%, pero se implementa como parámetro global editable (histórico incluido: si mañana pasa a 16%, los viajes viejos conservan el % vigente al momento del viaje).
- **Viáticos (definido):** se le adelanta un monto al chofer por viaje; los gastos reales se cargan aparte. En la liquidación mensual el sistema compara viáticos adelantados vs. gastos reales y calcula el ajuste a favor del chofer si no alcanzaron.
- Otros gastos por viaje: playa, peajes, extras (categorías configurables).
- **Cargas de combustible (definido):** el cliente tiene **cuenta corriente en 2–3 estaciones de servicio** y además carga eventualmente en otras. Cada carga registra: fecha, camión, estación, litros, importe, kilometraje y modalidad (cuenta corriente / pago directo). Las cargas en cta. cte. no mueven caja al momento — se acumulan por estación para cruzar contra el resumen mensual de la estación (mini conciliación).
  - Cálculo automático de consumo promedio (litros/100 km o km/litro) por camión y por período.
  - **Gráfica de evolución del consumo** por camión: si la curva se desvía del promedio histórico, es la señal de alerta que el cliente pide para detectar robos de combustible.
- **Cierre mensual por camión:** facturado, comisiones de choferes, combustible, gastos de viaje, resultado neto.
- **Consulta al día:** "¿cuánto va ganando el chofer X al día de la fecha?" con impresión/PDF de la liquidación para entregarle al chofer.
- Alta de un camión nuevo = un registro más, sin crear "planillas" nuevas (el cliente piensa en Excel; hay que explicarle que el sistema lo resuelve solo).

### Tablas principales (borrador)
`camiones`, `choferes` (con % comisión), `clientes`, `fletes`, `gastos_viaje`, `cargas_combustible`, `liquidaciones`.

---

## 3. Módulo 2 — Stock de repuestos y cubiertas

### Funcionalidades
- ABM de repuestos con categoría (repuesto / cubierta), marca, compatibilidad (a qué camión aplica), cantidad, ubicación, costo.
- Ingreso de compras (suma stock) y **consumo** (resta stock automáticamente), indicando en qué camión se usó — esto alimenta el historial de mantenimiento del Módulo 4.
- Vista rápida mobile-friendly: el dueño consulta desde el celular si el repuesto está y le dice al jefe de taller "usalo".
- *(Opcional)* flujo de autorización: el jefe de taller solicita, el dueño aprueba desde el celular.
- Alerta de stock mínimo por ítem.

### Tablas principales
`repuestos`, `movimientos_stock` (tipo: ingreso/egreso, camión asociado, usuario, fecha).

---

## 4. Módulo 3 — Pallets (portal externo)

Es el módulo diferencial del proyecto: involucra a un tercero.

### Flujo
1. Llega un camión con pallets vacíos a Tucumán → el dueño registra la **recepción**: cantidad, estado (sano / roto / reacondicionado / separador), fecha, remito digital autogenerado (numerado, imprimible en PDF, reemplaza el remito a mano).
2. El stock por estado se actualiza al instante.
3. Cuando devuelve pallets a Entre Ríos, registra la **devolución** (resta del stock, genera remito de salida).
4. La empresa de Entre Ríos entra con **usuario y contraseña de solo lectura** y ve en tiempo real: pallets en Tucumán por estado, devoluciones en tránsito, historial de remitos.

### Reglas
- Solo el dueño (admin) modifica; la empresa externa solo consulta.
- Trazabilidad completa: cada movimiento queda registrado con fecha, usuario y remito asociado.
- "Tiempo real" aquí significa simplemente que la web muestra el dato actualizado al recargar; no requiere websockets ni infraestructura especial.

### Tablas principales
`pallets_movimientos` (recepción/devolución, cantidades por estado), `pallets_stock` (o vista calculada), `remitos`.

---

## 5. Módulo 4 — Mantenimiento de vehículos

### Funcionalidades
- Historial de services por camión: tipo (cambio de aceite, filtros, frenos, cubiertas...), fecha, kilometraje, taller/mecánico, costo, repuestos usados (link con Módulo 2).
- **Planes de mantenimiento:** ej. "cambio de aceite cada 30.000 km o cada 6 meses". El sistema calcula el próximo vencimiento y muestra alertas en el dashboard (verde/amarillo/rojo).
- Carga de kilometraje: en la fase 1, manual (se puede aprovechar el km que ya se carga con cada combustible). El GPS queda como integración posterior.

### Sobre la integración con el GPS (definido: usa TrailingSat)
La plataforma es **TrailingSat** (trailingsat.com.ar), empresa argentina de monitoreo satelital con plataforma web y app propia. No publica documentación de API abierta en su sitio, así que el camino es: **contactar a su soporte comercial y preguntar si ofrecen API o reportes exportables** (muchas plataformas de este tipo lo dan a pedido, o corren sobre motores como Wialon/Traccar que sí tienen API). Plan B si no hay API: importación manual/periódica de un export de km, o seguir con la carga de km en cada carga de combustible, que para 3 camiones alcanza. Cotizar como **adicional de Fase 4**, condicionado a la respuesta de TrailingSat.

---

## 6. Módulo 5 — Cheques y tesorería

El cliente cobra y paga con cheques; hoy no tiene trazabilidad de qué pasó con cada uno. La consigna es: **detallado pero muy fácil de usar**.

**Definiciones confirmadas:** maneja cheques **físicos y digitales (ECHEQ)** — el formulario lleva un campo tipo; opera con **dos bancos** (ABM de cuentas bancarias, cada cheque propio y cada depósito se asocia a un banco); las **financieras varían** (ABM simple de financieras, se cargan a medida que aparecen); no hay volumen fijo mensual; y **sí necesita registrar rechazos con sus gastos y comisiones asociadas**.

### Cheques recibidos (de clientes)
- Carga rápida del cheque al recibirlo: número, banco, librador (cliente), importe, fecha de emisión y de cobro (diferido o al día), a qué flete/cliente corresponde.
- **Ciclo de vida con estados:** `En cartera` → y de ahí a uno de estos destinos:
  - `Depositado` (en cuál de los dos bancos) → `Acreditado` o `Rechazado`
  - `Vendido` (descontado a una financiera — esto explica el fragmento "una financiera" del audio): registrar a quién se vendió, tasa/quita y monto neto recibido
  - `Endosado` (entregado a un proveedor como pago)
- Cada cambio de estado queda registrado con fecha y usuario → **trazabilidad completa**: en cualquier momento se puede ver dónde está cada cheque y qué pasó con él.
- **Rechazos (definido):** al marcar un cheque como rechazado se registran los gastos y comisiones bancarias asociadas (egreso automático en tesorería) y el cheque queda en estado `Rechazado` con su historia, pudiendo pasar luego a `Recuperado` si el cliente lo repone.
- Alertas de vencimiento: cheques diferidos próximos a fecha de cobro.

### Cheques emitidos (propios)
- Registro de cheques propios entregados: a quién, importe, fecha de débito.
- Estados: `Emitido` → `Debitado` / `Rechazado`.
- Vista de **compromisos futuros**: cuánto va a debitar el banco y cuándo (evita rebotes propios).

### Registro del dinero (tesorería simple)
- Cuando un cheque se acredita o se vende, el sistema genera automáticamente un **ingreso de dinero** (en banco o en caja según corresponda).
- Sobre ese dinero, registrar **en qué se gastó**: egresos con categoría (combustible, repuestos, sueldos, gastos personales, etc.), muchas ya existentes en los otros módulos.
- **Estado bancario/de caja al día:** saldo actual, cheques en cartera (plata "por entrar"), cheques emitidos pendientes (plata "por salir") → posición financiera real de un vistazo.
- Vínculo con el resto del sistema: un egreso puede asociarse a una compra de repuestos (Módulo 2) o a una liquidación de chofer (Módulo 1), sin doble carga.

### Claves de UX
- Cargar un cheque debe llevar menos de 30 segundos (un solo formulario, campos mínimos obligatorios).
- Cambiar el estado debe ser un clic desde la lista ("Depositar", "Vender", "Endosar"), no un formulario nuevo.
- Semáforo visual por estado y por vencimiento.

### Tablas principales
`cheques` (recibidos y emitidos, con tipo), `cheques_movimientos` (historial de estados), `movimientos_tesoreria` (ingresos/egresos con categoría y referencia), `cuentas` (banco/caja).

---

Aprovechando tu stack actual (PHP + MySQL, mismo esquema que SAS Imperio):

- **Backend:** PHP (puro con tu estructura habitual, o Laravel si querés algo más mantenible a largo plazo — este proyecto tiene entidad suficiente para justificarlo).
- **Base de datos:** MySQL/MariaDB.
- **Frontend:** responsive obligatorio (el cliente insistió con el celular). Bootstrap 5 o Tailwind + algún template de admin moderno (el cliente pidió explícitamente "algo moderno, cómodo, fácil").
- **Gráficas:** Chart.js (consumo de combustible, facturación mensual).
- **PDF:** dompdf o mPDF para remitos y liquidaciones de choferes.
- **Autenticación con roles:** admin / taller / cliente-pallets / (chofer a futuro).
- **Hosting:** tu hosting actual sirve; el portal externo solo necesita HTTPS y un subdominio o ruta propia (ej. `pallets.dominio.com` o `/portal`).

Alternativa a evaluar: en lugar de desarrollar todo a medida, mirar cómo resuelven esto sistemas de gestión de transporte existentes (el propio cliente sugirió "averiguar cómo se manejan otros transportes") — no para comprarlos, sino para copiar buenas ideas de UX en fletes y liquidaciones.

---

## 8. Fases propuestas

### Fase 0 — Relevamiento y diseño (1–2 semanas)
- Reunión para cerrar definiciones (ver preguntas abajo).
- Modelo de datos completo y wireframes de las pantallas clave.
- Presupuesto cerrado por fase.

### Fase 1 — Núcleo: fletes + choferes (3–4 semanas)
- ABM de camiones, choferes, clientes.
- Carga de fletes con cálculo de comisión.
- Gastos de viaje y cargas de combustible.
- Cierre mensual y liquidación imprimible de chofer.
- Dashboard básico con gráfica de consumo por camión.

**Es la fase que más dolor le saca al cliente (los cuadernos) y la que genera confianza para seguir.**

### Fase 2 — Cheques y tesorería + Stock (3–4 semanas)
- Módulo de cheques recibidos/emitidos con trazabilidad de estados y alertas de vencimiento.
- Tesorería: ingresos automáticos al cobrar/vender cheques, egresos por categoría, saldo al día.
- Módulo de repuestos y cubiertas con movimientos.

**Va segundo porque toca la plata: junto con la Fase 1 le da control total del circuito facturar → cobrar → gastar.**

### Fase 3 — Pallets (2–3 semanas)
- Módulo de pallets completo con remitos PDF.
- Portal de solo lectura para la empresa de Entre Ríos (usuario/contraseña).

### Fase 4 — Mantenimiento + refinamientos (2–3 semanas)
- Planes de service y alertas por km/fecha.
- Vínculo repuestos ↔ services.
- Evaluación e integración con la API del GPS (si existe) — cotizar aparte.
- Conciliación bancaria simple (tildar movimientos contra el resumen del banco), si el uso lo pide.
- Ajustes de UX según el uso real de las fases anteriores.

**Total estimado: 3 a 4 meses** trabajando por fases con entregas usables al final de cada una.

---

## 9. Preguntas — estado

**Respondidas e incorporadas a este documento:** comisión (única, 15% inicial, sobre bruto), viáticos (adelanto + ajuste mensual), combustible (cta. cte. en 2–3 estaciones + cargas eventuales), GPS (TrailingSat), acceso de choferes (no, todo pasa por el dueño), flota (3 camiones, 4–15 viajes c/u por mes), cheques (físicos y ECHEQ, dos bancos, financieras variables, rechazos con gastos: sí).

**Siguen abiertas para la próxima reunión:**
1. Pallets: ¿qué datos exactos lleva el remito actual a mano? (pedir una foto de uno)
2. ¿Cuántas personas de la empresa de Entre Ríos necesitan acceso al portal?
3. ¿Facturación/IVA entra en el alcance o queda afuera? (recomendado: afuera en v1)
4. Combustible: ¿las estaciones con cta. cte. le mandan resumen mensual detallado? (define si la conciliación se hace contra papel o contra un archivo)
5. TrailingSat: pedirle que consulte a su ejecutivo de cuenta si ofrecen API o export de kilometraje.

## 10. Riesgos y recomendaciones

- **Alcance elástico:** el cliente va sumando ideas a medida que habla (típico). Cerrar alcance por fase y por escrito; todo lo nuevo va a la fase siguiente o se cotiza aparte.
- **El GPS es una promesa condicional:** no comprometer la integración hasta confirmar que el proveedor tiene API.
- **Adopción:** viene de cuadernos; la carga de datos tiene que ser rapidísima (pocas pantallas, pocos clics, funcionar bien en el celular). Si cargar un flete lleva más de un minuto, vuelve al cuaderno.
- **Datos históricos:** definir si se migra algo del Excel actual o se arranca de cero (recomendado: arrancar de cero con fecha de corte).
- **Modelo de cobro sugerido:** desarrollo por fase + abono mensual de hosting/soporte, como manejás en tus otros proyectos.
