# Bloc de notas — TuPlanner (wedding planners y organizadores de eventos · marca Tukán)

Última actualización: 21 de octubre de 2026

## Qué es
Programa para **wedding planners y organizadores de eventos** (bodas, quince años y demás). Pedido de Dimi del 14 oct 2026:
"un programa para wedding planner y organizador de eventos; es diferente que la quinta, porque hace todo el evento".
Se hizo **copiando TuEvento** (qfcorp/tuevento, salones y quintas) y adaptándolo al organizador. TuEvento sigue igual.

## Decisiones tomadas (14 oct 2026)
- [x] **Un solo programa** para bodas, quince años y todos los eventos (no dos)
- [x] Nombre: **TuPlanner** · dirección **tuplanner.tukanec.com** · repositorio **qfcorp/tuplanner**
- [x] Base: **copia de TuEvento** (no un modo dentro de TuEvento, para no dañar uno al tocar el otro)
- [x] Formas de cobro del organizador: las **4** — tarifa fija, porcentaje del presupuesto, comisión de proveedores y reventa (todo pasa por su contrato)
- [x] Planes provisionales: **Planner $39** (3 usuarios) y **Agencia $69** (todo, usuarios sin límite). Se cambian en el panel Tukán → Planes
- [x] Colores: rosa antiguo #8A3F5C y dorado #C9A46A (TuEvento es vino #8E3B52)

## Hecho (14 oct 2026) — lo propio del organizador (migración 100)
- [x] Lugares (quintas, haciendas, iglesias) con dirección, mapa, contacto y tipo; evento con **lugar por definir**
- [x] Tipos nuevos: pedida de mano, baby shower, despedida; categorías nuevas (honorarios, flores, pastel, vestido, maquillaje, invitaciones, transporte)
- [x] Pestaña **Planificación**: tareas por meses (organizador / cliente), lista típica (boda 28, quince 17, general 8), presupuesto por proveedor, repartir el presupuesto con % típicos, pagos directos del cliente a proveedores, comisiones, honorarios fijos o por % a la cotización, resumen del dinero
- [x] Pantalla **Comisiones** (por cobrar, cobradas por periodo, recordatorio por WhatsApp)
- [x] Pestaña **Ideas**: tablero de fotos (organizador y cliente), me encanta / no va, temática y paleta de colores
- [x] **Portal del cliente**: "Tu planificación" (sus tareas, presupuesto sin costos de reventa ni comisiones, sus pagos a proveedores, próximas reuniones) e "Ideas" (sube fotos)
- [x] **Máximo de eventos por día** (aviso y página pública con cupos), honorarios y comisión por defecto en Configuración → Reglas; plantilla de tareas con "para quién"
- [x] Datos de ejemplo de organizador (servicios de organización, paquete "Organización integral de boda", lugares, tareas, decoración propia), textos, planes, íconos "TP"
- [x] Pruebas: **lógica 2271/2271** (nuevas: logica_100, 136) y **pantallas 1916/1916** (nuevas: web_100, 114), sin avisos de PHP; revisado en celular (390 px) y computadora sin errores de JavaScript ni nada que se salga a lo ancho

## Hecho (15 oct 2026) — Pausar y borrar clientes desde el Panel Tukán Central (Anexo A)
- [x] **Pausar** (con tipo: falta de pago, pedido del cliente, revisión, uso indebido u otro, y motivo obligatorio): el negocio queda suspendido; al entrar ve "Tu cuenta está pausada" con el motivo (en revisión y uso indebido, un texto genérico) y el botón de WhatsApp de Tukán. Su página pública, portales (también "Tu planificación" e "Ideas"), invitaciones, firmas y enlaces dicen "temporalmente no disponible". **Reactivar** lo quita
- [x] **Borrar** (escribiendo el nombre exacto y el motivo; solo el dueño del Panel Central): primero se guarda una copia completa del negocio (todas sus filas de todas sus tablas, también las del organizador, y la lista de sus archivos) en `respaldos/borrados/` (fuera de la web, solo la lee el programa), y queda en la **papelera**: no entra, no sale en listas ni cifras, sus páginas dan 404 y no recibe tareas ni avisos
- [x] **Restaurar** dentro de 30 días (desde el Panel Central o desde el Panel Tukán del programa, filtro "Papelera"): vuelve tal cual. A los 30 días la tarea de cada hora lo borra de verdad; la copia se guarda 1 año
- [x] Pruebas: logica_97 (127) y web_97 (56), migración 097. Totales: lógica 2398/2398 y pantallas 1973/1973

## Hecho (18 oct 2026) — Programa modular (Anexo B) y "todo se pide demo"
Regla de Dimi: "todos los programas deben ser modulares para encender o apagar las funciones que necesita el cliente, para cobrar más o menos" y "nada se da gratis, todo se pide demo".
- [x] **Base + módulos**: la base la tienen todos (calendario, eventos, clientes, espacios, catálogo, cotizaciones, cuotas y recibos, Mensajes de hoy, página pública, reportes básicos, usuarios). Módulos con su precio al mes: paquetes $3, contratos con firma $6, portal del cliente $6, cobros en línea Payphone $4, formulario/extras/encuesta $5, día del evento $8, invitados con QR $8, plano $6, mobiliario $5, boda en vivo $5, proveedores y compras $7, portal del proveedor $4, incidencias y puntaje $3, metas y reportes avanzados $4, Telegram $3, exportar Excel/Google Calendar $3. Más los propios del organizador: **planificación $6**, **presupuesto por proveedor $6** (necesita planificación), **comisiones $4** (necesita presupuesto) e **ideas $4**.
- [x] **Planes = precio base + módulos**: Esencial $19 (solo la base, nuevo), Planner $39 (lo mismo que tenía antes) y Agencia $69 (todo). Precios, descripciones y qué trae cada plan se cambian en Panel Tukán → Planes y precios → Catálogo de módulos
- [x] **Por cliente**: módulos extra (se cobran), módulos quitados (se descuentan) y precio acordado. Precio al mes = plan + extras − quitados (o el acordado); es el que se sugiere al registrar el pago
- [x] **Apagar nunca borra datos**: se esconden menús, pestañas, páginas ("Este módulo no está en tu plan" con su precio y el enlace a Mi plan), botones, portales públicos ("No disponible"), PDF, tareas automáticas, Telegram, Mensajes de hoy y tarjetas del Inicio
- [x] **Mi plan** (dueño): lo que paga, sus módulos encendidos y los que puede agregar con su precio; "Pedir este módulo" → pedido que Tukán aprueba o rechaza (Panel Tukán o Panel Central)
- [x] **Panel Tukán**: ficha del negocio → Módulos con interruptores (enciende solo las dependencias), total al mes, precio acordado y pedidos; catálogo de módulos y planes; pedidos de demo en el Resumen con "Crear demo"
- [x] **Nada gratis**: ya no hay registro con prueba gratis. "Solicitar demo" (nombre, negocio, ciudad, WhatsApp, correo, qué necesita y módulos que le interesan) llega como pedido de demo; Tukán crea la demo (todos los módulos o los que elija, por los días que decida). "Tu demo terminó — contáctanos para contratar"
- [x] **Panel Tukán Central** (revisión 3): acción `modulos`, `cliente.modulos`, `mensual_estimado` con módulos, `cambiar_modulos` y `guardar_modulos` (solo el rol dueño, idempotentes y auditados), solicitudes `cambio_modulos` y `demo_pedida`, `crear_demo` con plan, módulos y pedido de demo, `finanzas.por_modulo`
- [x] Pruebas: logica_98 y web_98 (migración 098). Totales: **lógica 2515/2515 y pantallas 2060/2060**, sin avisos de PHP; revisado en celular (390 px) y computadora

## Hecho (20 oct 2026) — Precios del software solo por cotización
- [x] Regla del dueño: el precio del software nunca se publica. En **Mi plan** los módulos que el cliente no tiene dicen «Precio por cotización» con **Pedir cotización**; los otros planes ya no muestran su precio y también tienen **Pedir cotización** (crea el mismo pedido `cambio_modulos` con los módulos de ese plan y la nota «Pide cotización del plan …»). La pantalla «Este módulo no está en tu plan» ya no dice «Agrégalo por $X al mes»: trae **Pedir cotización** y **Ver Mi plan**. Lo que el cliente paga por su propio plan (y su renovación) se sigue viendo. El Panel Tukán y el Panel Central siguen viendo los precios.
- [x] Sin «gratis» en textos visibles (solo demos; en ventas, «meses de regalo»). README sin tabla de precios: «Precios: por cotización (se definen en el Panel Tukán Central)». Manual actualizado y PDF regenerado.
- [x] Pruebas: logica_98 (texto visible sin «gratis», Mi plan y módulo apagado sin precio) y web_98 (sección nueva: sin precios de módulos ni planes que no tiene, Pedir cotización desde el módulo y desde un plan, sin «gratis» en pantallas del cliente y públicas).

## Hecho (21 oct 2026) — Administradores (pedido del dueño de Tukán)
- [x] Rol nuevo **Administrador** (`admin`, migración 099 repetible: cambia el CHECK `usuarios_rol_check`). En Usuarios: «Administrador (mismos permisos que el dueño)» y el contador «Administradores: 2 de 4».
- [x] **Mismo nivel que el dueño** en todo: menú, Usuarios, Configuración, Mi plan, metas, costos y ganancia, plantillas, formularios, espacios/lugares, puntaje de proveedores, avisos «para el dueño» (campana y Telegram), quién vende y metas. Helper `es_admin_total()` (dueño o administrador) en vez de `es_dueno()` en todas las pantallas; `es_comercial()` también incluye al administrador.
- [x] **Máximo 4 administradores activos** sin contar al dueño: un quinto (crear, activar o cambiar de rol) muestra «Puede tener hasta 4 administradores además del dueño». Se cuenta con la fila del negocio bloqueada (dos personas a la vez no pasan el límite).
- [x] **No cuentan en el plan**: `max_usuarios` cuenta a todos los activos menos los administradores (el dueño sigue contando, como antes). Si un administrador pasa a otro rol, empieza a contar (y se revisa el límite). Mi plan también cuenta sin administradores.
- [x] **Seguridad**: nadie se cambia el rol ni se desactiva a sí mismo; al dueño nadie lo cambia, desactiva ni le da clave nueva desde Usuarios (tampoco un administrador); los administradores sí manejan a otros administradores y usuarios. El rol del POST se valida contra la lista: si no vale, error «Ese tipo de usuario no existe…» y no se guarda nada (nunca un rol por defecto). Todos los formularios de Usuarios llevan token CSRF (probado: sin token, 400 y no se guarda).
- [x] **Exclusivo del dueño** (identidad de la cuenta ante Tukán): ser el contacto del negocio en el Panel Tukán Central/Panel Tukán (nombre, correo y teléfono que se envían), «Nueva clave del dueño» desde Tukán, «Entrar como» del súper usuario, el contacto que ven el personal y los proveedores en sus portales, y no poder ser cambiado ni desactivado. Dentro del programa no quedó ninguna pantalla solo para el dueño.
- [x] Pruebas: logica_99 (63) y web_99 (62). Totales: **lógica 2582/2582 y pantallas 2156/2156**, sin avisos de PHP. Manual (capítulo de equipo, con una administradora en los datos de ejemplo y capturas nuevas), MANUAL.md y guía Primeros pasos actualizados y PDF regenerados.

## Hecho (21 oct 2026) — Ayuda, Asistente IA y demo por correo (librerías compartidas del Panel Tukán Central)
- [x] Librerías copiadas sin cambios: `src/tukan_ia.php` (versión 1) y `src/tukan_correo.php` (versión 1), con las guías `compartido/LEEME_IA.md` y `LEEME_CORREO.md`.
- [x] Migración **110** repetible (tablas `tukan_ia_faq`, `tukan_ia_gasto`, `negocios.ia_tope_usd`) y **111** (Asistente IA incluido en Agencia: `'*,asistente_ia'`). La papelera no toca `tukan_ia_gasto` (negocio_id de texto).
- [x] **Ayuda** para todos los planes y cargos (Más → Ayuda): FAQ guardada, IA con el manual (manual.html convertido y en caché) o el manual tal cual + WhatsApp. 10 preguntas por minuto por usuario; sin el módulo, 30 respuestas nuevas al mes por negocio. Nunca dice «límite», «cupo» ni «saldo».
- [x] **Asistente IA** (`asistente_ia`, grupo Extras): **incluido en el plan Agencia** (decisión del dueño); en Esencial y Planner, extra por cotización («Pídalo y le cotizamos», sin precio visible). **Cotización a partir de un mensaje** (JSON validado contra lugares, paquetes y servicios; borrador) y **Leer cotización de proveedor** (foto o PDF → partidas «Pidiendo precios»). Sin IA: mensaje neutro con el enlace para hacerlo a mano.
- [x] **Guardián por negocio**: **4,00 USD al mes con el módulo** (incluido o comprado aparte; decisión del dueño, 21 oct) y 0,50 sin él (solo la Ayuda; lo que sugiere la guía), o el tope que ponga Tukán (`ia_tope`). Tope silencioso.
- [x] Panel Tukán → **Preguntas IA** (listar, aprobar, editar, borrar, agregar) y conector v2 **`ia_uso`** / **`ia_tope`** (Anexo C).
- [x] **Enviar por correo** en la demo lista (sello cifrado de un solo uso, CSRF, auditoría sin la contraseña; sin `/etc/tukan-correo.env`: «Configure el correo de Tukán»).
- [x] Pruebas con la API de Anthropic y el SMTP de mentira: logica_110 y web_110. Totales: **lógica 2679/2679 y pantallas 2257/2257**, sin avisos de PHP. Manual (capítulo 18 «Ayuda e inteligencia artificial», capturas 37 a 39), MANUAL.md y Primeros pasos actualizados y PDF regenerados.

## Pendiente
0000a. [ ] DIMI: actualizar en el servidor (volver a correr el instalador del README): aplica las migraciones 110 (ayuda e IA) y 111 (Asistente IA incluido en Agencia). La IA usa `/etc/tukan-ia.env` y el correo `/etc/tukan-correo.env` del Panel Tukán Central; `www-data` debe poder leerlos. Revisar Panel Tukán → Preguntas IA cada semana
0000. [ ] DIMI: actualizar en el servidor (volver a correr el instalador del README): aplica la migración 099 (rol administrador)
000. [ ] DIMI: actualizar en el servidor (volver a correr el instalador del README): aplica la migración 098 (módulos y pedidos de demo). Revisar los precios de los módulos en Panel Tukán → Planes y precios
0. [ ] DIMI: al actualizar en el servidor, volver a correr el instalador del README: crea la carpeta `respaldos/borrados` y pasa las tareas de cada hora al usuario `www-data`
1. [ ] DIMI: instalar en qfcorp (línea del README) y crear la ruta del túnel `tuplanner.tukanec.com` → HTTP → localhost:80 (Host header)
2. [ ] DIMI: crear el super usuario (`php /var/www/tuplanner/bin/crear_admin.php`) y conectar al Panel Tukán Central (tipo "TuPlanner")
3. [ ] Confirmar precios y qué va en cada plan después de hablar con 2-3 wedding planners
4. [ ] Agregar TuPlanner a tukansoftware.com (repo qfcorp/tukan-web)
5. [ ] Ideas para después: cronograma de pagos a proveedores con recordatorio al cliente por WhatsApp, comparar 2-3 cotizaciones de proveedores por partida, portal del proveedor ligado a las partidas, lista de regalos
6. [ ] Probar Payphone con claves reales (solo se probó con simulador)

---
## Historia heredada de TuEvento (13 oct 2026)
## Hecho (13 oct 2026) — piloto completo
- [x] Base de datos (36 tablas + 5 migraciones) con **bloqueo de doble reserva en la base** (restricción de exclusión por espacio y horario)
- [x] Ventas: calendario mensual y "próximos sábados", eventos (lista y tablero), evento con estados, seguimiento, tareas, clientes, espacios y fechas cerradas, lista de espera
- [x] Página pública del local con "¿está libre tu fecha?" y formulario de consulta (anti-robots, límite por hora)
- [x] Catálogo, paquetes (simulador por invitados), cotizaciones con versiones, IVA por línea, descuento, ganancia (solo dueño), enlace para aceptar, PDF con QR
- [x] Contratos con plantillas y variables, firma del cliente con el dedo, PDF firmado, verificación por QR y huella SHA-256
- [x] Cobros: plan de cuotas automático o a mano, pagos y recibos, comprobantes del cliente, Payphone, garantía, gastos y ganancia real, tablero de cobranza, mensajes de WhatsApp del día
- [x] Portal del cliente (básico en Salón, completo en Quinta)
- [x] Día del evento: cronograma con modo en vivo, personal con su portal, proveedores, mobiliario con disponibilidad, hoja del evento, boda en vivo con QF
- [x] Invitados: invitación digital, confirmación, mesas, pegar lista, control de ingreso con QR, portal de invitados para los novios, PDFs
- [x] Inicio del día, reportes, exportar a Excel, configuración, usuarios y roles, Telegram, calendario de Google, tareas automáticas, panel Tukán, instalador y respaldo
- [x] **Pruebas en entorno simulado: lógica 795/795 y pantallas 916/916**, sin avisos de PHP; revisado en celular y computadora (Chromium) sin errores de JavaScript; QR leídos con un lector real

## Hecho (13 oct 2026) — portal del cliente, segunda parte (ideas de Tripleseat, Perfect Venue, Planning Pod, Aisle Planner, HoneyBook)
- [x] Formulario del evento: plantillas de ejemplo por tipo de evento (8 tipos de pregunta), copia por evento, el cliente lo llena en su portal con guardado automático y avance, cierre X días antes, aviso al local al terminar, respuestas en el evento, en PDF y en la hoja del evento, recordatorio en Mensajes de hoy
- [x] Extras en línea: el local elige qué productos se piden en el portal (texto y foto); el cliente pide, el local aprueba (versión nueva de la cotización + cuota "Extras") o rechaza con nota
- [x] Encuesta de satisfacción con enlace secreto (NPS + estrellas + comentario), botón de reseña en Google para los que recomiendan, aviso al dueño por notas bajas, reporte de satisfacción y NPS en el reporte del mes
- [x] Pruebas: logica_70 y web_70 (migración 070)

## Hecho (13 oct 2026) — plano del salón y metas de ventas (ideas de Tripleseat, Cvent, Planning Pod y Perfect Venue)
- [x] Plano visual del salón: distribuciones por espacio (con ejemplo de boda o corporativo listo), editor con el dedo y el mouse (cuadrícula de 0,5 m, girar, duplicar, deshacer, acercar con dos dedos, se guarda solo)
- [x] Plano de cada evento unido a las mesas de invitados (en los dos sentidos), colores por ocupación, invitados de cada mesa, mesas sin ubicar, PDF a escala con la lista de mesas, y el plano para mirar en el portal de los novios
- [x] Metas de ventas del local y por vendedor (meta de siempre + meses distintos), avance del mes en el Inicio y Reportes → Metas con el año anterior y descarga para Excel
- [x] Pruebas: lógica logica_80 (189) y pantallas web_80 (137), y prueba real en Chromium con dedo (390 px) y mouse

## Hecho (13 oct 2026) — proveedores y compras con trazabilidad completa (ideas de Planning Pod, Odoo, STEL Order, control de pólizas y Momentus)
- [x] Ficha del proveedor: RUC/cédula, banco para transferir, crédito (días, cupo con aviso, anticipo), preferido; estado de cuenta, pagos, eventos, historial de precios, puntaje y bitácora de todo
- [x] Documentos con vencimiento (RUC, permisos, ARCSA, póliza, contrato) con alertas en la lista, la ficha y por Telegram
- [x] Compras / órdenes de compra OC-0001 con ítems e IVA, estados, PDF con QR de verificación, WhatsApp, comparar pedidos de precio y elegir; el proveedor queda solo en el día del evento
- [x] Factura del proveedor a mano o con el XML del SRI (crea el proveedor si el RUC es nuevo); pagos con cheque posfechado, anulación sin borrar, sin pagar de más; retención y descuentos por incidencias
- [x] Por pagar: total, vencido, esta semana, antigüedad 1-30/31-60/61-90/+90, por proveedor, cheques, cupo pasado, Excel; tarjeta en el Inicio; compras en la ganancia real y en Reportes
- [x] Día del evento: llegada real (coordinador o "Ya llegué" del proveedor), puntualidad, cumplimiento y estrellas, bloques del cronograma a cargo de un proveedor; incidencias con foto (graves avisan al dueño), también en la hoja del evento
- [x] Portal del proveedor (Quinta): sus eventos, cronograma, confirmar órdenes, subir factura, pagos y saldo
- [x] Tareas automáticas: pagos por vencer y vencidos, documentos por vencer, proveedores sin confirmar
- [x] Pruebas: logica_60 (321) y web_60 (210), migración 060; revisado en celular (390 px) y computadora sin errores de JavaScript

## Hecho (13 oct 2026) — anexo de contrato por extras
- [x] Si se aprueban extras y el contrato ya está firmado, se prepara solo un anexo ("Anexo 1 al contrato N.º X") con los extras, su valor, el nuevo total y la cuota "Extras"; el cliente lo firma con el dedo (huella SHA-256, QR de verificación, PDF firmado)
- [x] Plantilla editable del anexo en Plantillas de contrato; tarjeta "Tienes un anexo por firmar" en el portal; anexos en la pestaña Contrato; recordatorio en Mensajes de hoy; aviso al local cuando se firma
- [x] Decisión: un pedido de extras aprobado no se deshace (para quitar extras se hace una cotización nueva); sin contrato firmado no hay anexo, los extras entran en el contrato cuando se genere
- [x] Pruebas: logica_70 y web_70 ampliadas (migración 090)

## Hecho (13 oct 2026) — puntaje de proveedores configurable por local
- [x] Proveedores → Cómo se calcula el puntaje: pesos de calificación, puntualidad, cumplimiento e incidencias (suman 100 %; 0 = no cuenta), minutos de tolerancia, cuánto resta cada incidencia (leve, media, grave), tope del nivel con incidencia grave, umbrales de Excelente/Bueno/Regular, periodo (todo, 12 o 6 meses) y mínimo de eventos ("Sin datos suficientes")
- [x] Vista previa en vivo con 3 proveedores reales (puntaje y puesto antes → después) y botón "Volver a los valores recomendados"; solo el dueño guarda, el equipo ve la explicación; cada cambio queda anotado con quién, cuándo, antes y después
- [x] Se usa en la ficha, el ranking, los selectores de compras y del día del evento, y la puntualidad (también en el portal del proveedor). Sin cambiar nada, los resultados son los mismos de antes
- [x] Pruebas: logica_60 (389) y web_60 (258), migración 091

## Hecho (13 oct 2026) — conexión con el Panel Tukán Central
- [x] `tukan_central.php` (protocolo Tukán Central v1): entrar con un clic desde el panel central (crea el super usuario la primera vez y después usa el mismo; queda en el registro) y cifras para el panel central (negocios por estado igual que el panel, nuevos en 30 días, cobrado del mes y del mes anterior, pruebas que vencen en 7 días, pagos vencidos, eventos y cotizaciones del mes, usuarios, ingreso mensual)
- [x] Botón "← Panel Tukán" en el panel cuando se entró desde el central; `TUKAN_SOLO_CENTRAL=1` apaga el ingreso local del super usuario (emergencia: ponerlo en 0 y `bin/crear_admin.php`)
- [x] Cada enlace dura 60 s y sirve una vez, 30 pedidos por minuto por IP, sin datos personales; sin configurar responde 404
- [x] Comando para conectar: `sudo bash /var/www/tuplanner/deploy/conectar_central.sh {ID} {SECRETO} {URL}` (guarda en config/.env y hace una prueba local)
- [x] Pruebas: logica_95 (120) y web_95 (157), migración 095. Totales: lógica 1829/1829 y pantallas 1717/1717

## Hecho (13 oct 2026) — Panel Tukán Central v2
- [x] El panel central ya puede, para TuPlanner: ver todos los clientes con su estado y vencimiento, abrir su ficha (pagos, últimas acciones, notas, WhatsApp listo), ver las solicitudes (registros nuevos, pruebas por terminar o terminadas, planes por vencer o vencidos, dominios gratis) y marcarlas atendidas, y ver las finanzas por mes
- [x] Desde el central: crear demos (con datos de ejemplo; la clave se ve una sola vez), alargar la prueba (no a quien ya paga), registrar pago con la fecha en que pagó, anular pago, suspender, reactivar, cancelar, cambiar plan, nueva clave del dueño, nota interna y cambiar precios de los planes. Si el central reintenta, no se cobra ni se crea dos veces
- [x] "Por vencer" ahora es lo mismo en todos los programas: al día y vence en 7 días o menos. Los que están en días de gracia cuentan como vencidos (antes TuPlanner los contaba como "por vencer")
- [x] Entrar desde el central puede abrir directo la ficha de un negocio (`ir`); los errores traen un código para que el central diga qué pasó; límite 120 pedidos y 30 rechazados por minuto
- [x] Se guarda de dónde vino cada negocio (se registró solo, panel de Tukán o demo del central)
- [x] Pruebas: logica_96 (301) y web_96 (86), migración 096. Totales: lógica 2135/2135 y pantallas 1803/1803
- Falta (no existe en TuPlanner): pedidos de demo, comprobantes de pago y soporte. Si se quieren en el central, hay que construirlos aquí primero (subir comprobante desde la pantalla de prueba terminada y un formulario de ayuda)
