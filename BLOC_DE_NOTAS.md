# Bloc de notas — TuPlanner (salones de eventos, quintas y haciendas · marca Tukán)

Última actualización: 13 de octubre de 2026

## Qué es
Programa para salones de eventos, quintas y haciendas de Ecuador. Nació del estudio de nichos (12 oct 2026): no había ningún programa ecuatoriano para este mercado.
Se armó juntando lo mejor de los programas existentes y lo que Tukán ya tenía:
- **Brinden** (Argentina): agenda con aviso de doble reserva, pipeline presupuestado → señado → reservado, presupuestos en PDF por WhatsApp, caja de señas, perfil público con formulario.
- **EventControl** (México): cotizaciones y contratos con paquetes, compras y gastos por evento.
- **Evento Plus**: avisos automáticos por WhatsApp en cada etapa.
- **OfiEventos** (España, en español): presupuestos, planning, salones, invitados, inventario, control de cobros, escandallo de costos.
- **Anolla / Infor / ReServe**: lista de espera, eventos que se repiten, comunicación automática, órdenes de servicio (BEO), contratos con la marca.
- **ERP QF**: propuestas en PDF, firma con QR, personal por evento y personal mínimo, cronómetro de escaleta, reportes, exportar.
- **TuSalón**: agenda, anticipos con transferencia/DeUna/Payphone, planes y prueba gratis, Telegram, app instalable.
- **TuPass**: accesos con QR y transmisión (boda en vivo).

## Decisiones tomadas
- [x] Nombre provisional: **TuPlanner** (se puede cambiar)
- [x] Planes: **Salón $39/mes** (hasta 2 espacios y 3 usuarios) · **Quinta $69/mes** (todo, ilimitado). Semestral paga 5 recibe 6; anual paga 10 recibe 12 + dominio. Prueba gratis 7 días. Precios y lo que incluye cada plan se cambian después (panel Tukán → Planes).
- [x] Plan Quinta incluye: portal del cliente completo, invitados con QR, día del evento, mobiliario y boda en vivo.
- [x] **Sin facturación electrónica** por ahora (misma decisión pendiente que la veterinaria: opción B, proveedor registrado en el SRI).
- [x] Tecnología igual que TuSalón: PHP + PostgreSQL, en el servidor qfcorp, dirección **tuplanner.tukanec.com**.

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

## Pendiente
0. [ ] DIMI: cuando el Panel Tukán Central esté listo, correr `sudo bash /var/www/tuplanner/deploy/conectar_central.sh {ID} {SECRETO} {URL}` con los datos que da el panel (después de actualizar TuPlanner)
1. [ ] DIMI: instalar en el servidor (comando en el README) y crear la ruta del túnel `tuplanner.tukanec.com`
2. [ ] DIMI: crear el super usuario del panel Tukán (`php /var/www/tuplanner/bin/crear_admin.php`)
3. [ ] Probar Payphone con claves reales de un local (solo se probó con simulador)
4. [ ] Lista de 10 salones/quintas conocidos por QF Producciones → entrevistas y piloto gratis con 2-3
5. [ ] Decidir nombre definitivo (TuPlanner / TuFecha / TuQuinta), precios y qué va en cada plan después del piloto
6. [ ] Facturación electrónica (opción B, proveedor registrado en el SRI) cuando se decida
7. [ ] WhatsApp automático (API oficial, pagada); hoy hay WhatsApp con un toque
8. [ ] Agregar TuPlanner a tukansoftware.com
9. [ ] Manual en PDF y presentación de ventas (como TuSalón)
