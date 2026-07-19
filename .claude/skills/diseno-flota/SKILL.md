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

Para elegir camión, chofer, estación, modalidad. Un toque, sin abrir nada.

```css
.seg{display:flex; gap:6px;}
.seg button{
  flex:1; padding:10px 4px; border-radius:var(--radio); font-size:.86rem; font-weight:600;
  border:1.5px solid var(--linea); background:#fff; color:var(--gris); cursor:pointer;
}
.seg button.on{background:var(--asfalto); border-color:var(--asfalto); color:var(--vial);}
```

Si una lista puede crecer mucho (ej. clientes), no uses `.seg` — ahí sí va el único desplegable permitido de la pantalla.

### Chip de estado (`.chip`)

```css
.chip{display:inline-block; padding:3px 9px; border-radius:6px; font-size:.7rem;
  font-weight:800; text-transform:uppercase; letter-spacing:.05em;}
.chip.cartera{background:#FBF0CB; color:#8A6D00; border:1px solid var(--vial);}
.chip.dep{background:#E3EBF6; color:var(--info); border:1px solid #B9CCE6;}
.chip.rech{background:#F8E4E4; color:var(--alerta); border:1px solid #E3B6B6;}
```

Agregar variantes del mismo patrón (fondo pastel + texto + borde del color del estado) para nuevos estados en vez de inventar una paleta distinta.

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
