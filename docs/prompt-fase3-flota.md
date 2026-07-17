# PROMPT FASE 3 — pegar en Claude Code (proyecto sistema_flota)

> **Antes de pegar:** Fase 2 commiteada y aprobada. Copiá la foto del remito real del cliente a `docs/remito-actual.jpg` — el PDF debe replicarlo.

---

La **Fase 2 está terminada y aprobada**. Arrancamos la **Fase 3: módulo de pallets + portal externo de solo lectura** para la empresa de Entre Ríos. Es la fase más delicada del proyecto: por primera vez una pantalla del sistema la va a ver alguien que NO es nuestro cliente — la imagen del transportista frente a SU cliente depende de que el portal sea impecable y seguro.

## PASO 0 — Recuperar contexto

1. Leé `CLAUDE.md` completo.
2. Releé en `docs/planificacion-sistema-flota.md` la sección **"Módulo 3 — Pallets (portal externo)"**: flujo de recepción/devolución, estados (sanos, rotos, reacondicionados, separadores) y reglas (solo el dueño modifica, la empresa solo consulta).
3. Revisá en `docs/esquema-flota.sql`: `remitos`, `pallets_movimientos`, la vista `v_pallets_stock`, y `clientes.es_portal_pallets`. El esquema NO se modifica.
4. Abrí `docs/remito-actual.jpg`: es el talonario que usan hoy y el PDF debe replicarlo. Especificación extraída del remito real (respetala al pie de la letra):
   - **Membrete**: marca del transportista + razón social, domicilio (predio, localidad), teléfonos/WhatsApp, condición de IVA y CUIT. Los datos concretos pedímelos a mí antes de armar la plantilla — no inventes ninguno.
   - **Leyendas obligatorias**: "DOCUMENTO NO VÁLIDO COMO FACTURA" arriba y "COMPROBANTE INTERNO — REMITO" junto al número.
   - **Número correlativo de 6 dígitos** con ceros a la izquierda (ej. 000592) + fecha.
   - **Datos de la recepción**: "Recibí del Transporte: ___ CUIT: ___", "Chofer: ___ DNI: ___", "Hoja de ruta: ___" (el que entrega puede ser un transporte tercero, no solo la empresa de Entre Ríos).
   - **Cuerpo en tabla de dos columnas** (concepto / cantidad-detalle) con estos renglones fijos: Documentación, Tarimas, Separadores, Peajes, Devoluciones — más 2-3 renglones libres para observaciones.
   - **Pie**: razón social + CUIT repetidos, y dos firmas: "Firma del Recepcionista" y "Firma del Chofer".
   - **Terminología**: el cliente les dice **"tarimas"** a los pallets. En el PDF usá "Tarimas"; en las pantallas internas y el portal usá "Tarimas (pallets)" en títulos y "tarimas" en el texto corriente.
5. Confirmame en un párrafo el alcance antes de codear.

## Ajuste autorizado del esquema (único de esta fase)

El remito real registra datos que el esquema original no contemplaba. Ejecutá este ALTER (y guardalo como `docs/migraciones/fase3-remitos.sql` para replicarlo en el VPS):

```sql
ALTER TABLE remitos
  ADD COLUMN transporte_origen VARCHAR(120) NULL AFTER cliente_id,   -- quién entrega (puede ser un tercero)
  ADD COLUMN transporte_cuit   VARCHAR(15)  NULL AFTER transporte_origen,
  ADD COLUMN chofer_nombre     VARCHAR(80)  NULL AFTER transporte_cuit,
  ADD COLUMN chofer_dni        VARCHAR(15)  NULL AFTER chofer_nombre,
  ADD COLUMN hoja_ruta         VARCHAR(40)  NULL AFTER chofer_dni,
  ADD COLUMN documentacion     VARCHAR(150) NULL AFTER hoja_ruta,    -- renglón "Documentación" del talonario
  ADD COLUMN peajes            VARCHAR(100) NULL AFTER documentacion; -- renglón "Peajes" del talonario
```

Las cantidades por estado siguen viviendo en `pallets_movimientos` (sanos/rotos/reacondicionados/separadores) — el renglón "Tarimas" del PDF es la suma de sanos+rotos+reacondicionados con el detalle entre paréntesis, y "Separadores" y "Devoluciones" salen de sus campos. Fuera de este ALTER, el esquema NO se toca.

## Reglas de negocio de esta fase

- **Remito correlativo sin huecos**: `remitos.numero` se asigna al confirmar (MAX+1 dentro de la transacción, con lock para evitar duplicados si hay dos cargas simultáneas). Un remito confirmado NO se edita ni se borra: los errores se corrigen con un remito de ajuste (observación obligatoria).
- **Recepción y devolución son el mismo formulario** con el tipo como toggle; cantidades por estado (sanos/rotos/reacondicionados/separadores), todas ≥ 0, al menos una > 0.
- **Una devolución no puede dejar stock negativo en ningún estado**: validar contra `v_pallets_stock` antes de confirmar y mostrar el disponible por estado en el propio formulario.
- **El PDF se genera al confirmar** el remito (acá entra **dompdf vía Composer** — primera dependencia del proyecto: creá `composer.json`, agregá `/vendor/` al `.gitignore` si no está, y documentá en el CLAUDE.md que en el VPS hay que correr `composer install`). Formato A5 apaisado o A4, membrete simple con los datos del transportista, imprimible en blanco y negro.
- **Trazabilidad**: el stock NUNCA se edita a mano; siempre es la suma de movimientos (la vista ya lo resuelve).

## El portal externo (`portal/`) — seguridad primero

Es una zona pública con login propio. Reglas estrictas:

- **Sesión separada** de la del sistema interno (cookie con nombre propio, ej. `flota_portal`); un login de portal jamás abre nada de `modulos/`.
- El usuario de portal (rol `portal_pallets`) queda **vinculado a un `cliente_id`** y solo ve datos de ese cliente. Verificación en cada consulta, no solo en el login.
- **Solo lectura absoluta**: el portal no tiene ni un solo endpoint que escriba en la base (excepto el registro de sesión si lo implementás). Ningún formulario salvo el login.
- Login con **rate limiting** simple (bloqueo temporal tras 5 intentos fallidos), `password_hash`, y sin revelar si falló el usuario o la clave.
- Cabeceras de seguridad en todo el portal: `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`. Nada de listados de directorio.
- Usá el skill **owasp-security** para revisar el portal completo cuando esté terminado y pasame el resultado de esa revisión.

### Pantallas del portal (diseño sobrio, con los mismos tokens del sistema)

1. **Login** simple con el nombre del transportista como título.
2. **Inicio**: el stock actual en Tucumán como 4 tarjetas grandes (sanos / rotos / reacondicionados / separadores) + total, con fecha y hora de la última actualización. Es LA pantalla que van a mirar: tiene que entenderse en 3 segundos desde un celular.
3. **Historial de remitos**: tabla filtrable por tipo y rango de fechas, con descarga del PDF de cada remito.
4. Pie discreto en todas las páginas: "Sistema provisto por [tu marca]" — te hace publicidad gratis frente a otra empresa.

## Construcción (en orden, cada pantalla con su URL antes de seguir)

1. **Nuevo remito** (`modulos/pallets/nuevo.php`): formulario único recepción/devolución según las reglas de arriba, mobile-first, con los campos del talonario real: transporte que entrega (con CUIT), chofer y DNI, hoja de ruta, documentación y peajes — todos opcionales salvo las cantidades. Disponible por estado visible al elegir "devolución".
2. **Generación del PDF** con dompdf al confirmar, replicando el layout del talonario (`docs/remito-actual.jpg`): membrete, leyendas, número de 6 dígitos, tabla de conceptos y doble firma. Almacenamiento en `archivos/remitos/` (fuera del webroot público si el hosting lo permite; si no, con acceso vía script que valida sesión — nunca URL directa adivinable). Antes de codear la plantilla, pedime: nombre comercial, razón social, domicilio, teléfonos, condición de IVA y CUIT del transportista.
3. **Listado de remitos** interno con reimpresión de PDF y acceso al detalle.
4. **Stock de pallets** interno (misma info que verá el portal, más el enlace a cada remito).
5. **Gestión de usuarios de portal** (`modulos/pallets/usuarios_portal.php`): alta/baja de usuarios vinculados al cliente con `es_portal_pallets=1`, con reseteo de clave. Preparalo para más de un usuario por cliente (no sabemos aún cuántas personas de Entre Ríos van a entrar).
6. **El portal completo** según la sección anterior.
7. **Dashboard interno**: agregá una tarjeta con el stock total de pallets y el último remito.
8. **Revisión de seguridad** con owasp-security sobre `portal/` y los endpoints de PDF; correcciones que surjan.
9. **Datos semilla**: 8-10 remitos mezclados (recepciones y devoluciones) con cantidades coherentes, y un usuario de portal de prueba (`portal_demo`) pidiéndome la clave por consola.

## Reglas de trabajo (las de siempre)

- Transacciones PDO en remito+movimiento+PDF; commits conventional en español; frená ante decisiones no documentadas; URL local al terminar cada pantalla.
- NO empieces la Fase 4 (mantenimiento) ni la integración GPS.

Empezá por el PASO 0.
