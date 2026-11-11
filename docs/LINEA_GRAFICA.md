# Identidad gráfica de TuPlanner (kit común Tukán, modelo ERP) — 10 nov 2026

Todos los programas Tukán se ven y se usan igual: barra de arriba del color del programa, menú izquierdo azul noche con
todas las opciones (emoji + nombre) en grupos, dashboard con primeros pasos y cifras, tarjetas de sección, tablas con
cabecera azul noche. **Cada programa solo cambia su color.**

## El kit (no se edita aquí)
- `public/assets/tukan-ui.css` y `public/assets/tukan-ui.js`: **idénticos byte a byte en todos los programas**
  (`md5sum` debe dar lo mismo que en los demás). Si falta algo, va en el CSS propio del programa, nunca en el kit.
- Se cargan en `app/vistas/_diseno.php`, **después** de `app.css` y los `mod_*.css`, y solo actúan con `<body class="tk">`.
  El portal público del cliente (`_publico.php`), los PDF y los correos no llevan el kit: no cambian.

## Color de TuPlanner (la única línea propia, en `app/vistas/_diseno.php`)
```html
<style>:root{ --tk-color:#3678B4; --tk-color-claro:#629BD0; --tk-color-texto:#ffffff; }</style>
```
**Decisión de Dimitry, 10 nov 2026: el color de la web.** Cada programa usa por dentro el mismo color que tiene en
tukansoftware.com (y en el Panel). TuPlanner es **azul**: en la web `#3B82C4`; aquí `#3678B4`, el «tono de pantalla» (un poco más
oscuro para que el texto blanco llegue a 4,5:1: da 4,7:1). Antes rosa antiguo `#8A3F5C`. También `<meta name="theme-color">` y
`theme_color` de `public/manifest.json` (los íconos y la app de Google Play no cambian). `#629BD0` sobre azul noche: más de 6:1.
- Como letra sobre el gris del kit el `#3678B4` no llega (4,3:1). En el puente (`app.css`): `--tp-color-letra` = el mismo azul más
  oscuro (`color-mix(… 80%, #000)`, 6:1). Lo usan los enlaces sueltos y **`--verde`** (las piezas de antes que lo usaban como letra:
  pestañas, «Ver…», montos, años de las metas). `--verde-claro` y `--verde-oscuro` también son tonos del azul.
- Las piezas de antes con `--verde` de **fondo** (chips activos, contadores, pestañas del día, embudo, paso actual, casillas hechas
  del plan, barras de gráficos…) están listadas al final del puente («Piezas de antes con fondo del color del programa») con el
  azul del programa y su texto. Si aparece una pieza nueva con fondo del color, agrégala ahí.
- El portal público del cliente, los PDF, los correos y los colores que elige cada negocio no cambian.

## Menú (`app/menu.php`, función `tk_menu()`): una sola definición para el menú y la pantalla «Más»
| Grupo | Opciones (las de módulos apagados o sin permiso no salen) |
|---|---|
| Principal | 📊 Inicio · 📅 Calendario · 🎉 Eventos · 🔔 Avisos (con globito de sin leer) |
| Ventas | 👥 Clientes · 📞 Seguimiento · 💬 Mensajes por WhatsApp · 🏷️ Catálogo y precios · 📦 Paquetes · 🎁 Extras del portal · 🎯 Metas de ventas |
| Dinero | 💳 Cobros · 🧾 Por pagar · 🤝 Comisiones · 📈 Reportes |
| Operación | 🏛️ Lugares · 🗺️ Planos del salón · 🧑‍🍳 Personal · 🚚 Proveedores · ⚠️ Incidencias · 🪑 Decoración y menaje |
| Configuración | ⚙️ Configuración · ✍️ Plantillas de contrato · 📋 Formularios del evento · 🧑‍💼 Usuarios · 🧾 Factura electrónica · ⭐ Mi plan · 👤 Mi cuenta · ❓ Ayuda |

- El coordinador no ve Ventas ni Dinero; solo el dueño y los administradores ven Configuración, usuarios, factura y Mi plan.
- Arriba del menú queda «Buscar evento o cliente…». En la barra de arriba: logo del negocio (o «TP»), nombre del negocio,
  «TuPlanner · Plan …», nombre del usuario (lleva a Mi cuenta), su cargo y «Salir».
- En el celular el menú se abre con ☰ (lo hace `tukan-ui.js`). Ya no hay barra de botones abajo.

## Inicio (dashboard), en este orden (`app/vistas/inicio.php`)
1. **Saludo** según la hora de Ecuador.
2. **Avisos importantes** (`.tk-tira`): demo con los días que quedan y «Ver Mi plan», plan por vencer, términos por aceptar,
   contraseña temporal, comprobantes por revisar, y la tarjeta de la demo con el manual y los primeros pasos.
3. **Primeros pasos** (`.tk-pasos`), los que ya calculaba `Configuracion::primerosPasos()`: completar los datos del negocio ·
   subir el logo · revisar lugares y precios · crear el primer evento · conectar Telegram. Con barra de avance, «Hacerlo» y
   «Ya sé usarlo, ocultar» (se guarda por negocio). Desaparece al completarlos.
4. **Cifras** (`.tk-cifras`, las que el Inicio ya calculaba): eventos este mes · eventos en los próximos 30 días ·
   vencido por cobrar · consultas nuevas sin atender · pagos a proveedores (si hay y el módulo está encendido).
5. **Tarjetas del día** (`.tk-columnas`): próximos 7 días · cuotas vencidas o por vencer · seguimientos de hoy ·
   tareas por vencer · para atender · avisos sin leer. Cada una con título, descripción, botón «Ver…» y tabla o vacío que guía.
6. **Accesos rápidos**: Nuevo evento · Calendario · Cobros · Eventos · Clientes.

## Las demás pantallas
- Sus piezas llevan las clases del kit junto a las de antes: `boton tk-boton` (`boton-claro` → `secundario`, `boton-chico` →
  `chico`, `boton-peligro` → `peligro`, `boton-wa` → `ok`), `bloque tk-tarjeta`, `cabecera tk-titulo-pagina`, `campo tk-campo`,
  `tabla tk-tabla`, `vacio tk-vacio`, `acciones tk-acciones`. En una pantalla nueva usa directamente las clases `tk-…`.
- **Puente** (al final de `public/assets/app.css`, todo con `body.tk`): lo propio de TuPlanner que el kit no trae —campos
  sueltos, avisos (`aviso aviso-ok|error|alerta`), estados del evento como chips, buscador del menú, pantalla «Más»— y los
  **emojis de los botones**: el botón lleva `data-ico="guardar|mas|editar|basura|descargar|ojo|tarjeta|check|flecha…"` y el
  puente le pone el emoji delante, sin cambiar el texto del botón.
- **Tablas en el celular**: `app.js` les pone a las tablas `tk-tabla` el título de cada columna (`data-titulo`) y la clase
  `tarjetas`; así se ven como tarjetas. No toca las que tienen campos para escribir ni las marcadas `data-tabla-fija`.
- **Tres nombres del kit que ya existían en TuPlanner** (ojo al copiar esto a otro programa de la misma familia):
  `aviso` (aquí es la clase de los mensajes: para cifras y tarjetas ámbar se usa `te-ambar`), `tarjetas` (era una rejilla: el
  puente la anula en las tablas) y el botón «ocupado» de `tukan-ui.js` (los formularios que abren otra pestaña o que piden
  confirmación llevan `data-sin-ocupado`).
- **Contraste AA**: el puente oscurece dos colores del kit que no llegaban a 4,5:1 (texto gris sobre el fondo gris y blanco
  sobre el botón verde de WhatsApp). Cuando el kit lo corrija para todos, esas dos líneas del puente se quitan.
