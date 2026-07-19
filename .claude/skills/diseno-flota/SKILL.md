---
name: diseno-flota
description: usar siempre que se cree o modifique una pantalla
---

# Diseño — Sistema de Flota

Tokens y componentes extraídos de `docs/wireframes-flota.html` (diseño aprobado por el cliente). Replicar exactamente, no reinventar ni "mejorar" sin aprobación.

## Regla de oro

Cada carga (flete, combustible, cheque) se completa en **menos de 30 segundos, con una mano, desde el celular**. Máximo **un desplegable por pantalla** — todo lo demás son botones de un toque. Lo que el sistema puede calcular, lo calcula y lo muestra **antes** de guardar, nunca después.

## Paleta (CSS custom properties)

```css
:root{
  --asfalto:#23282E; --fondo:#E9EBE8; --panel:#FFFFFF;
  --vial:#F2B705; --tinta:#1B1F24; --gris:#646A74; --linea:#D8DBD6;
  --ok:#2C7743; --alerta:#BC3939; --info:#2B5FA3;
  --radio:10px;
}
```

- Fondo de página: `--fondo`. Tarjetas/paneles: `--panel` blanco.
- Cabeceras de pantalla ("barra"): fondo `--asfalto`, texto blanco, franja superior de 5px `--vial`.
- Botón primario: fondo `--vial`, texto `--tinta`, negrita.
- Estados: `--ok` verde (acreditado/sano), `--alerta` rojo (rechazado/vencido), `--info` azul (depositado).
- **Contraste verificado (WCAG AA, texto normal ≥4.5:1)**, fórmula de luminancia relativa aplicada a los pares realmente en uso: `--tinta` sobre `--panel`/`--fondo` ≈16.6:1/13.8:1 (sobra margen); `--gris` sobre `--panel` 5.45:1 y sobre `--fondo` 4.54:1; `--alerta` sobre `--panel` 5.55:1 y sobre `--fondo` 4.63:1 (uso en `.venc`); pares de chip — `.chip.cartera` (`#836800`/`#FBF0CB`) 4.67:1, `.chip.rech`/`.login-error` (`--alerta`/`#F8E4E4`) 4.55:1, `.chip.ok`/`.exito` (`--ok`/`#E2F0E6`) 4.66:1, `.chip.dep` (`--info`/`#E3EBF6`) 5.35:1. `--gris`, `--alerta` y `--ok` y el texto de `.chip.cartera` se oscurecieron levemente (~5%) respecto a la paleta original del wireframe para cruzar el umbral de 4.5:1 sin cambiar el tono ni los fondos — si se agrega un color de estado nuevo, verificar su contraste con la misma fórmula antes de darlo por bueno.

## Tipografía

`"Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif`. Números de importes y kilometraje con `font-variant-numeric: tabular-nums`.

## Componentes

### Botón segmentado (`.seg`) — reemplaza los `<select>`

Para elegir camión, chofer, estación, modalidad. Un toque, sin abrir nada. `min-width:90px` evita que los botones queden demasiado angostos cuando hay 4-5 opciones en una fila a 390px; envuelve a la línea siguiente (`flex-wrap`) si no entran. `button` y `a` comparten estilo (`a` para navegación tipo pestañas); `.seg.tabs` es la variante usada como sub-navegación de un módulo (ver `modulos/*/tabs.php`), con más margen inferior para separarla del contenido.

```css
.seg{display:flex; gap:6px; flex-wrap:wrap;}
.seg button, .seg a{
  flex:1; padding:10px 4px; border-radius:var(--radio); font-size:.86rem; font-weight:600;
  border:1.5px solid var(--linea); background:#fff; color:var(--gris); cursor:pointer;
  min-width:90px; text-decoration:none; text-align:center;
}
.seg button.on, .seg a.on{background:var(--asfalto); border-color:var(--asfalto); color:var(--vial);}
.seg.tabs{margin-bottom:18px;}
```

Si una lista puede crecer mucho (ej. clientes), no uses `.seg` — ahí sí va el único desplegable permitido de la pantalla.

### Chip de estado (`.chip`)

```css
.chip{display:inline-block; padding:3px 9px; border-radius:6px; font-size:.7rem;
  font-weight:800; text-transform:uppercase; letter-spacing:.05em;}
.chip.cartera{background:#FBF0CB; color:#836800; border:1px solid var(--vial);}
.chip.dep{background:#E3EBF6; color:var(--info); border:1px solid #B9CCE6;}
.chip.rech{background:#F8E4E4; color:var(--alerta); border:1px solid #E3B6B6;}
.chip.ok{background:#E2F0E6; color:var(--ok); border:1px solid #B7D8C1;}
```

Agregar variantes del mismo patrón (fondo pastel + texto + borde del color del estado) para nuevos estados en vez de inventar una paleta distinta.

### Lista de ítems (`.item`) — cheques, fletes, planes, services, remitos

El patrón de lista más usado en todo el sistema: una tarjeta por fila, con hasta dos líneas de detalle y acciones al pie.

```css
.item{border:1.5px solid var(--linea); border-radius:var(--radio); padding:11px 12px; margin-bottom:10px; background:var(--panel);}
.item .l1{display:flex; justify-content:space-between; align-items:center; margin-bottom:3px;}
.item .num{font-weight:700; font-size:.92rem;}       /* identificador principal (nº, patente, nombre) */
.item .imp{font-weight:800; font-size:1.02rem; font-variant-numeric:tabular-nums;}  /* importe, alineado a la derecha en .l1 */
.item .l2{display:flex; justify-content:space-between; align-items:center; color:var(--gris); font-size:.8rem;}  /* detalle secundario, hasta dos por item */
.item .venc{color:var(--alerta); font-weight:700;}    /* texto de vencimiento/urgencia dentro de .l2 */
.item.inactivo{opacity:.55;}                          /* fila dada de baja, sin ocultarla del todo */
```

`.l1` es la línea de identificación + importe; una o dos `.l2` debajo para contexto (fecha, cliente, chip de estado). Si `.num` es un link a un detalle (`ficha.php`, `detalle.php`), envolver el texto en `<a>` — el link ya hereda color y gana algo de padding vertical como área de toque.

### Acciones de fila (`.acciones`)

Botones/forms de una sola acción al pie de un `.item`, con touch target de **44px mínimo** (guía mobile, no es negociable en pantallas de una mano):

```css
.acciones{display:flex; gap:6px; flex-wrap:wrap; margin-top:9px;}
.acciones form{flex:1; display:flex;}
.acciones button, .acciones a{
  flex:1; min-height:44px; padding:8px 6px; font-size:.78rem; font-weight:700; cursor:pointer;
  border-radius:8px; border:1.5px solid var(--linea); background:#fff; color:var(--tinta);
  text-decoration:none; text-align:center; box-sizing:border-box;
  display:flex; align-items:center; justify-content:center;
}
.acciones button.p, .acciones a.p{border-color:var(--asfalto); background:var(--asfalto); color:var(--vial);}  /* acción primaria de la fila */
```

Con 3 botones por fila (el máximo visto hasta ahora, ej. cartera de cheques en_cartera) entran cómodos a 390px; para más de 3, agrupar en un diálogo en vez de seguir agregando botones a la fila.

### Tarjetas grandes (`.tarjeta-grande`, `.tarjeta-total`) — portal de pallets

Para que un número clave se entienda "en 3 segundos" sin leer texto alrededor (stock del portal externo). No usar para pantallas internas con más densidad de datos — ahí va `.item`/`.consumo`.

```css
.tarjetas-grandes{display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-top:16px;}
.tarjeta-grande{
  background:var(--panel); border:1.5px solid var(--linea); border-radius:var(--radio);
  padding:18px 14px; text-align:center;
}
.tarjeta-grande strong{display:block; font-size:2.4rem; font-variant-numeric:tabular-nums; line-height:1;}
.tarjeta-grande.ok strong{color:var(--ok);}
.tarjeta-grande.alerta strong{color:var(--alerta);}
.tarjeta-grande.info strong{color:var(--info);}
.tarjeta-total{
  margin-top:14px; background:var(--asfalto); color:#fff; border-radius:var(--radio);
  padding:16px; text-align:center;
}
.tarjeta-total strong{font-size:2.2rem; font-variant-numeric:tabular-nums;}
```

### Formularios: `.campo`, `.campo-input`, `.fila`, `.fila3`

```css
.campo, .campo-input{
  width:100%; border:1.5px solid var(--linea); border-radius:var(--radio);
  padding:11px 12px; font-size:1rem; background:#fff; color:var(--tinta);
}
.campo{display:flex; align-items:center; justify-content:space-between;}  /* fila tipo "select" simulado o checkbox con texto */
.fila{display:grid; grid-template-columns:1fr 1fr; gap:10px;}    /* dos campos por fila (ej. litros/importe) */
.fila3{display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px;} /* tres campos por fila (ej. litros/importe/km) */
```

`.campo-input` es el `<input>`/`<select>` real; `.campo` es un contenedor tipo fila para agrupar un label con un control (por ejemplo, un checkbox con su texto al lado, ver `modulos/maestros/clientes.php` o `modulos/mantenimiento/nuevo.php`).

### Nota secundaria (`.nota`)

Texto de ayuda o aclaración bajo un bloque, nunca el único lugar donde vive información obligatoria:

```css
.nota{margin-top:12px; font-size:.83rem; color:var(--gris); line-height:1.5;}
.nota b{color:var(--tinta);}
```

### Mini-diálogos (`dialog`, `.btn-cerrar`)

Cambiar el estado de algo es un clic desde una lista, no un formulario nuevo — como mucho abre uno de estos diálogos nativos (`<dialog>` + `showModal()`) con el campo puntual que ese cambio necesita:

```css
dialog{
  border:none; border-radius:var(--radio); padding:18px;
  max-width:340px; width:90vw;
  box-shadow:0 12px 32px rgba(27,31,36,.24);
}
dialog::backdrop{background:rgba(27,31,36,.5);}
dialog .btn-cerrar{
  width:100%; margin-top:8px; padding:12px; border-radius:var(--radio);
  border:1.5px solid var(--linea); background:#fff; color:var(--gris); font-weight:600; cursor:pointer;
}
```

### Pantalla de login (`.login-body`, `.login-tarjeta`, `.login-barra`)

Reusada también por `error.php` (404/403/500) para que la pantalla de error comparta la misma identidad que el login, no un estilo aparte:

```css
.login-body{min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px 16px;}
.login-tarjeta{
  width:100%; max-width:360px; background:var(--panel); border-radius:18px;
  overflow:hidden; border:1px solid var(--linea); box-shadow:0 8px 28px rgba(27,31,36,.10);
}
.login-barra{background:var(--asfalto); color:#fff; padding:22px 20px 18px; border-top:5px solid var(--vial); text-align:center;}
.login-form{padding:20px;}  /* también se reusa como contenedor con padding en error.php (.error-cuerpo) */
```

### Tarjeta de cálculo automático (`.auto`)

Para comisión, consumo, ajuste de viáticos, días al cobro: todo cálculo que el sistema hace solo va acá, visible antes de guardar.

```css
.auto{
  margin-top:14px; background:#F6F3E6; border:1.5px dashed var(--vial);
  border-radius:var(--radio); padding:11px 13px; display:flex;
  justify-content:space-between; align-items:center;
}
.auto small{display:block; color:var(--gris); font-size:.7rem; text-transform:uppercase; letter-spacing:.08em; font-weight:700;}
.auto strong{font-size:1.18rem; font-variant-numeric:tabular-nums;}
```

### Alerta de desvío (consumo, stock mínimo, vencimientos)

```css
.consumo{display:flex; gap:8px; margin-top:14px;}
.consumo div{flex:1; border-radius:var(--radio); padding:9px 10px; border:1.5px solid var(--linea);}
.consumo .malo{border-color:#E3B6B6; background:#FBF3F3;}
.consumo .malo b{color:var(--alerta);}
```

### Barra total (`.totalbar`)

Pie de pantalla con un acumulado relevante (cta. cte. del mes, total en cartera, etc.):

```css
.totalbar{display:flex; justify-content:space-between; background:var(--asfalto); color:#fff;
  padding:11px 16px; font-size:.85rem;}
.totalbar b{color:var(--vial); font-variant-numeric:tabular-nums;}
```

### Botón secundario

```css
.btn.sec{background:#fff; border:1.5px solid var(--linea); color:var(--gris); font-weight:600; margin-top:8px;}
```

Se usa para acciones opcionales que no cierran el flujo (ej. "Guardar y cargar gastos del viaje").

## Reglas de UX no negociables

- Mobile-first, ancho de referencia **390px**. Probar siempre en ese ancho antes de dar por terminada una pantalla.
- Un solo formulario por tarea, campos mínimos obligatorios.
- Cambiar el estado de algo (cheque, resumen de estación) es **un clic desde una lista**, nunca un formulario nuevo — el clic abre a lo sumo un campo puntual (ej. banco de destino al depositar).
- Semáforo visual (verde/amarillo/rojo) para todo lo que tiene fecha límite o umbral: vencimientos de cheques, stock mínimo, consumo desviado, próximo service.
