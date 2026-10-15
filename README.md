# TuPlanner

Programa para **wedding planners y organizadores de eventos** (bodas, quince años, pedidas de mano, baby showers, graduaciones, corporativos…) de Ecuador, de la marca Tukán Software.
Nació como copia de TuEvento (qfcorp/tuevento, para salones y quintas) y se adaptó al organizador, que no tiene local: trabaja en las quintas, haciendas e iglesias de otros y lleva todo el evento.
Dirección: **tuplanner.tukanec.com** (servidor qfcorp). Web de la marca: tukansoftware.com.

- Plan, avance y pendientes: [BLOC_DE_NOTAS.md](BLOC_DE_NOTAS.md)
- Cómo está hecho y reglas para programar: [docs/GUIA_DESARROLLO.md](docs/GUIA_DESARROLLO.md)

## Qué hace
**Lo propio del organizador** (migración 100):
- **Planificación** de cada evento (pestaña nueva):
  - **Lista de tareas por meses** ("12 meses antes", "6 meses antes", …, "El último mes", "Después del evento"): lista típica de boda (28 tareas), de quince años (17) y general; cada tarea es **del organizador o del cliente** (el cliente marca las suyas en su portal).
  - **Presupuesto por proveedor**: una partida por cada cosa que hay que contratar (lugar, banquete, fotos, flores, vestido…), con estimado, precio contratado, estado (idea, pidiendo precios, contratado, descartado), fecha para pagarle y **quién le paga**:
    - *El cliente le paga directo* → se anotan sus pagos (el organizador o el cliente desde su portal) y se ve cuánto falta.
    - *Va en nuestro contrato* (reventa) → el organizador lo cobra en su cotización y lo paga con Compras. **El cliente no ve ese costo.**
  - **Repartir el presupuesto** del cliente con los porcentajes típicos de Ecuador (boda, quince años u otro) para arrancar en un clic.
  - **Honorarios**: tarifa fija o **porcentaje del presupuesto**; un botón los pone en la cotización (reemplaza la línea anterior).
  - **Comisiones de proveedores** (porcentaje o valor fijo, solo cuando la partida está contratada), lo cobrado y lo que falta; pantalla **Comisiones** con todo lo que te deben, recordatorio por WhatsApp y lo cobrado por periodo.
  - **Dinero del evento**: presupuesto del cliente, costo total, cuánto queda o se pasa, lo pagado al organizador y a proveedores.
- **Ideas** (pestaña nueva): tablero de inspiración con fotos del organizador y del cliente, "me encanta / no va", notas, **temática y paleta de colores**.
- **Portal del cliente**: tarjetas "Tu planificación" (sus tareas, presupuesto, próximas reuniones) e "Ideas para tu evento" (sube fotos y marca las que le encantan); el lugar del evento sale con su dirección y mapa.
- **Lugares** (antes "espacios"): las quintas, haciendas e iglesias donde trabaja, con dirección, mapa, contacto y tipo (ceremonia o recepción). Un evento puede quedar con **lugar por definir**.
- **Máximo de eventos por día** del equipo: aviso en el evento y la página pública dice "quedan pocos cupos" o "sin cupo".
- Tipos de evento nuevos: pedida de mano, baby shower y despedida de soltera/o. Categorías nuevas: honorarios, flores, pastel y postres, vestido y traje, maquillaje y peinado, invitaciones, transporte.
- Planes: **Planner $39** (3 usuarios, todo lo del organizador) y **Agencia $69** (usuarios sin límite + invitados con QR, día del evento, plano de mesas, decoración y menaje, portal de proveedores y boda en vivo).

**Heredado de TuEvento:**
- Calendario por espacios **sin doble reserva** (la base de datos lo impide), con horas de montaje y desmontaje, fechas cerradas y "próximos sábados".
- Embudo de ventas: consulta → cotizado → separado → confirmado → realizado, con seguimiento (llamadas, visitas, degustaciones) y tareas por evento.
- Catálogo, paquetes y **cotizaciones** con IVA por producto, descuento, versiones, ganancia, enlace para que el cliente la acepte y PDF con QR.
- **Contratos** desde plantillas con variables, **firmados por el cliente en su celular**, con verificación por QR (detecta si alguien cambió el texto).
- **Plan de cuotas** automático, pagos, recibos, comprobantes de transferencia/DeUna que sube el cliente, **Payphone**, garantía por daños, gastos y ganancia real del evento. La fecha se separa sola al pagar la reserva.
- **Portal del cliente** (sin contraseña, con enlace secreto): su evento, documentos, pagos, invitados, cronograma y transmisión.
- **Formulario del evento** por tipo (boda, quince años, corporativo…): el cliente lo llena en su portal (se guarda solo) hasta X días antes; el local ve y corrige las respuestas, que salen en la hoja del evento. Recordatorio por WhatsApp.
- **Extras en línea**: el cliente pide desde su portal hora extra, hora loca, fotocabina…; el local aprueba y se suma solo a la cotización y al plan de pagos. Si el contrato ya estaba firmado, se prepara un **anexo de contrato** que el cliente firma con el dedo (con QR de verificación y PDF).
- **Encuesta de satisfacción** después del evento (NPS y estrellas): a quien le encantó se le pide la reseña en Google; una nota baja avisa al dueño. Reporte de satisfacción.
- **Día del evento**: cronograma minuto a minuto con **modo en vivo**, personal con portal propio, proveedores, mobiliario con disponibilidad por fecha, hoja del evento (PDF).
- **Invitados con QR**: invitación digital, confirmación de asistencia, mesas, control de ingreso en la puerta escaneando el QR.
- **Proveedores y compras**: ficha de cada proveedor (RUC, cuenta del banco, días y cupo de crédito, anticipo), documentos con vencimiento (permisos, ARCSA, póliza) con aviso, órdenes de compra por evento (pedido de precio → pedida → confirmada → entregada) en PDF con QR y por WhatsApp, comparar precios y elegir, factura del proveedor a mano o **leyendo el XML del SRI**, pagos con cheques posfechados y anulación, **Por pagar** (vencido, esta semana, antigüedad de saldos, cupo, Excel), puntualidad y calificación el día del evento, **incidencias** con foto y descuento al proveedor, ranking de proveedores con **puntaje configurable por el dueño** (pesos, tolerancia, incidencias, niveles, periodo y mínimo de eventos, con vista previa), bitácora de todo y **portal del proveedor** (sus eventos, confirmar órdenes, subir facturas, sus pagos y "Ya llegué"). Las compras cuentan en la ganancia real del evento.
- **Plano visual del salón**: distribuciones de cada espacio en metros (mesas redondas, rectangulares e imperiales, pista, tarima, barra, buffet, DJ…) que se arrastran con el dedo o el mouse; el plano de cada evento está unido a las mesas de los invitados (se ve quién está sentado y qué mesa está llena), en PDF y para que los novios lo miren.
- **Metas de ventas**: meta del mes (y por vendedor), avance en el Inicio y reporte con la comparación con el mismo mes del año anterior.
- **Boda en vivo** con QF Producciones (pedido por WhatsApp y enlace en portal e invitaciones).
- Página pública del local con consulta de fechas libres y formulario que entra al sistema.
- Inicio con el día, cobranza, mensajes por WhatsApp listos para enviar, reportes (ventas, cobrado, ocupación, conversión, rentabilidad, comisiones), exportar a Excel, calendario para Google Calendar, avisos por Telegram.
- Usuarios: dueño, vendedor, coordinador (solo el día del evento) y consulta (solo mira). Panel Tukán para administrar todos los negocios y sus planes.

## Direcciones
| Quién | Dirección |
|---|---|
| Dueño y equipo | `/` (entrar con correo y contraseña) |
| Página pública del local | `/?r=sitio&s=<nombre-del-local>` |
| Cliente (portal) | `/?r=portal&t=<enlace secreto>` · formulario `/?r=portal_cuestionario&t=…` · extras `/?r=portal_extras&t=…` · encuesta `/?r=encuesta&t=…` |
| Cotización / firma de contrato | `/?r=cot&t=…` · `/?r=firmar&t=…` · anexo `/?r=firmar_anexo&t=…` · verificación `/?r=verificar&c=<código>` |
| Invitado / puerta / en vivo / personal | `/?r=invitacion&t=…` · `/?r=puerta&t=…` · `/?r=vivo&t=…` · `/?r=staff&t=…` |
| Proveedor (portal) / verificar orden de compra | `/?r=portal_proveedor&t=<enlace secreto>` · `/?r=verificar_oc&c=<código>` |
| Tukán (super administrador) | `/?r=admin_entrar` · cuenta creada con `php bin/crear_admin.php` en el servidor · o con un clic desde el Panel Tukán Central |
| Panel Tukán Central (conector) | v1: `/tukan_central.php?accion=sso&t=…` (entrar, con enlace directo `ir`) · `/tukan_central.php?accion=estado&t=…` (cifras en JSON) · v2: `POST /tukan_central.php?accion=<capacidades, clientes, cliente, cotizar, planes, solicitudes, solicitud, finanzas, ejecutar>` |

## Tecnología
PHP 8.3+, PostgreSQL 16 (extensiones `btree_gist` y `pgcrypto`), TCPDF (con composer) para los PDF. Sin librerías de JavaScript.

## Instalar en el servidor (Ubuntu + PHP + PostgreSQL + nginx)
Una sola línea, como el usuario normal (no root). Se puede repetir para actualizar: si ya hay negocios, no borra la base.

```bash
curl -fsSL https://raw.githubusercontent.com/qfcorp/tuplanner/main/deploy/instalar.sh -o ~/instalar_tuplanner.sh && bash ~/instalar_tuplanner.sh
```

Después, en Cloudflare (Zero Trust → Networks → Tunnels → tu túnel → Public Hostname) agregar `tuplanner.tukanec.com` → `HTTP` → `localhost:80`, con *HTTP Host Header* = `tuplanner.tukanec.com`.

Super usuario del panel Tukán: `php /var/www/tuplanner/bin/crear_admin.php`

## Conectar con el Panel Tukán Central (panel.tukanec.com)
El panel central muestra las cifras de todos los programas Tukán y deja entrar a cada panel con un clic.
1. En el panel central, agrega la instalación de TuPlanner: te da un **ID** (ej. `tuplanner`) y un **SECRETO** (64 letras y números).
2. En el servidor, una sola línea:
   ```bash
   sudo bash /var/www/tuplanner/deploy/conectar_central.sh {ID} {SECRETO} {URL}
   ```
   Ejemplo: `sudo bash /var/www/tuplanner/deploy/conectar_central.sh tuplanner 3f9a…c1 https://panel.tukanec.com`.
   Guarda `TUKAN_CENTRAL_ID`, `TUKAN_CENTRAL_SECRET` y `TUKAN_CENTRAL_URL` en `config/.env` (lo demás no se toca), prepara las tablas y hace una prueba: debe terminar en `OK`.
3. Listo: al entrar desde el panel central, el panel de TuPlanner muestra el botón **← Panel Tukán** para volver.

- Sin `TUKAN_CENTRAL_ID` o `TUKAN_CENTRAL_SECRET`, `/tukan_central.php` responde 404 y todo sigue como antes. El instalador conserva estas líneas al actualizar.
- **Entrar solo desde el panel central:** agrega `TUKAN_SOLO_CENTRAL=1` en `config/.env`. La página `/?r=admin_entrar` dice "El ingreso del super usuario es solo desde el Panel Tukán" y no acepta contraseñas. El ingreso de los negocios no cambia.
- **Emergencia** (el panel central no responde): pon `TUKAN_SOLO_CENTRAL=0` en `config/.env` y entra con tu clave; si no la recuerdas, `php /var/www/tuplanner/bin/crear_admin.php` la cambia.
- Cada entrada desde el panel central queda en "Últimos movimientos del panel" ("Entró desde el Panel Tukán Central"). La primera vez se crea el super usuario con el correo del panel central (con una contraseña al azar que nadie conoce).
- Seguridad: cada enlace dura 60 segundos y sirve una sola vez (30 s las operaciones); máximo 120 pedidos y 30 rechazados por minuto por IP; el estado no lleva datos personales (solo nombres de negocios). Detalle del protocolo: `PROTOCOLO.md` (v1) y `PROTOCOLO_V2.md` (v2) del panel central.

### Qué puede hacer el Panel Tukán Central (protocolo v2)
Con el mismo ID y el mismo secreto, el panel central ve y maneja a los clientes de TuPlanner sin entrar al panel:
- **Clientes**: todos los negocios con su estado (en prueba, al día, por vencer en 7 días, vencido —incluye los días de gracia— y suspendido/cancelado), plan, vencimiento, dueño, último uso, pagos y lo que vale al mes. La ficha trae los pagos (también los anulados), las últimas acciones, las notas internas y los mensajes de WhatsApp listos.
- **Solicitudes**: negocios que se registraron solos (30 días), pruebas que terminan en 2 días / hoy / terminadas sin pagar (60 días), planes que vencen en 5 días o vencidos sin renovar (30 días) y dominios gratis por registrar. Se marcan "en proceso", "atendida" o "descartada" (tabla `tukan_central_atenciones`).
- **Operaciones** (con las mismas funciones del panel de Tukán y una línea en "Últimos movimientos del panel" con el correo del Central): crear demo (negocio en prueba con datos de ejemplo; la contraseña temporal se muestra una sola vez), alargar la prueba, registrar pago (con la fecha en que pagó), anular pago, suspender, reactivar, cancelar, cambiar plan, nueva clave del dueño, nota interna, atender solicitud y guardar plan/precios.
- **Finanzas**: cobrado por mes (por plan, tiempo y forma de pago), nuevos pagadores, devengado e ingreso mensual recurrente.
- Si el Central repite una operación (se cortó la red), TuPlanner no la hace dos veces: devuelve el mismo resultado (`tukan_central_operaciones`, 90 días, sin contraseñas).

## Tareas automáticas y respaldo
El instalador deja programado:
```
7 * * * * php /var/www/tuplanner/bin/tareas.php          (cada hora, minuto 7: no choca con TuEvento)
35 3 * * * /var/www/tuplanner/deploy/respaldo.sh         (cada día 3:35, guarda 14 días en /var/backups/tuplanner)
```
Tareas: vencer cotizaciones, avisar fechas retenidas que se liberan, cuotas por vencer, revisar precios, evento en 7 días, pasar a realizado, devolver garantías, reporte del mes, seguimientos del día, pagos a proveedores que vencen, documentos de proveedores por vencer y proveedores sin confirmar a 3 días del evento.

Telegram (opcional) en `config/.env`: `TELEGRAM_BOT_TOKEN`, `TELEGRAM_BOT_USERNAME` y `TELEGRAM_WEBHOOK_SECRET` (obligatorio si se usa el webhook).

## Pruebas (entorno simulado)
Solo en bases de prueba (se borran y se vuelven a crear):

```bash
# Lógica (2271 pruebas)
TUPLANNER_DB_NAME=tuplanner_prueba TUPLANNER_DB_USER=tuplanner TUPLANNER_DB_PASS=prueba php tests/run_tests.php
# Pantallas de punta a punta (1916 pruebas; levanta un sitio de prueba solo)
PGPASSWORD=prueba bash tests/run_web.sh
# Solo el conector con el Panel Tukán Central (v1 = logica_95/web_95, v2 = logica_96/web_96)
TUPLANNER_DB_NAME=tuplanner_prueba TUPLANNER_DB_USER=tuplanner TUPLANNER_DB_PASS=prueba php tests/run_tests.php logica_9
PGPASSWORD=prueba bash tests/run_web.sh web_9
```
