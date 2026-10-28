# App de Android "TuPlanner" (Google Play)

La app abre este sistema a pantalla completa (Trusted Web Activity). Se compila en el repositorio `qfcorp/tukan-apps`
(app `tuplanner`, paquete `com.tukansoftware.tuplanner`, abre `https://tuplanner.tukanec.com/?r=inicio&app=play`).
Guía completa para publicar: `tukan-apps/tienda/tuplanner/PUBLICAR_TUPLANNER_CON_CLAUDE.md`.
Todo lo que se cambia en el sistema web se ve en la app al instante (no hay que subir otra versión).

| Qué | Dónde |
|---|---|
| Archivo que Android revisa para abrir sin barra de navegador | `/.well-known/assetlinks.json` → lo arma `src/AppAndroid.php` (nginx lo manda a `public/index.php`) |
| Huella de nuestra llave de subida | fija en `src/AppAndroid.php` (`HUELLA_SUBIDA`) |
| Huella de «Firma de apps de Google Play» | `config/.env` → `TUPLANNER_ANDROID_HUELLAS` (la pone `deploy/app_android.sh HUELLA`; el instalador la conserva) |
| Modo app | `?app=play` deja la cookie `tp_app` (1 año); `en_app_android()` en `app/bootstrap.php`. `?app=web` la quita |
| Política de privacidad (pública) | https://tuplanner.tukanec.com/privacidad.php (también `/?r=privacidad`), sección `#borrar-cuenta` |
| Borrar la cuenta desde la app | **Más → Mi cuenta → Borrar mi cuenta** (pedido por correo a info@tukansoftware.com) |
| Cuenta para el revisor de Google | `deploy/demo_google.php crear | borrar` y `src/DemoGoogle.php` |
| Pruebas | `tests/logica_120_app_android.php` y `tests/web_120_app_android.sh` |

## Sin cobro dentro de la app (versión 1.0.0)

Decisión de Dimitry (28 oct 2026, igual que TuFactura): la app es **solo para entrar con la cuenta que da Tukán Software**.
Dentro de la app (`en_app_android()`) **no** se ve nada para comprar o pagar el software por fuera de Google Play:

| Pantalla | En la web | En la app |
|---|---|---|
| Mi plan | Precio al mes, desglose, «Cuánto pagas» con **Pagar** (WhatsApp), otros planes con **Pedir cotización**, pagos hechos, «Preguntar por WhatsApp» | Plan, estado, fecha, módulos encendidos y **Elegir este módulo** (solo los incluidos en el plan Básico: no cuesta más). Sin montos, sin cotizaciones, sin pagos, sin WhatsApp de ventas. El servidor rechaza `pedir_plan` y pedir módulos fuera del plan |
| Módulo apagado | «Pedir cotización» | Solo «Ver Mi plan» |
| Franja «Tu plan vence…» | con **Renovar** | sin Renovar |
| Demo terminada / plan vencido / cuenta pausada | WhatsApp de Tukán y «Ver mi plan y pedir cotización» | Texto neutro, sin enlaces |
| Límite de lugares o usuarios | «cambia de plan (el plan Completo no tiene límite)» | «desactiva uno» |
| Más → Mi plan | «Tus módulos, lo que pagas y lo que puedes agregar» | «Tu plan y tus módulos» |

**Lo que se queda igual en la app** (y por qué): lo que el **organizador cobra a sus propios clientes** — plan de cuotas, pagos y recibos,
comprobantes de transferencia o DeUna, Payphone del portal del cliente, garantía, extras del portal. Son pagos de **servicios reales
que se prestan fuera del celular** (organización y coordinación del evento, banquete, decoración, flores, música, transporte, lugar), no del software ni de
contenido digital. La política de Pagos de Google Play excluye de Google Play Billing a los bienes y servicios físicos, así que ahí no
hay que esconder nada. «Solicitar demo» en la pantalla de entrar también se queda: no muestra precios ni cobra (Tukán crea la cuenta).
La **Ayuda** sigue con su WhatsApp de soporte (no vende nada).

Si Google pregunta (Contenido de la app o un rechazo por Pagos), responder:
> TuPlanner is a business tool for wedding planners and event organizers. The app does not sell any digital goods or subscriptions: accounts are created by
> Tukán Software for each business, and inside the app there are no prices, purchase buttons or links to pay for the software.
> The payments that appear in the app (installments, receipts, card payments through Payphone in the customer portal) are payments
> from the planner's own clients for real-world services delivered at the event (event planning and coordination, venue, catering,
> decoration, flowers, music, transportation),
> which are outside the scope of Google Play Billing.

## En el servidor (qfcorp, usuario qf)

Actualizar con el instalador del README (aplica todo y conserva la configuración). Después:
```bash
sudo bash /var/www/tuplanner/deploy/app_android.sh
```
Debe terminar en `OK: la app de Android ya puede verificar el sitio.`

## Cuenta demo para el revisor de Google

```bash
php /var/www/tuplanner/deploy/demo_google.php crear
```
Crea el negocio **Agencia Ejemplo Google Play** (aparte, en demo por 1 año, plan Completo, **sin pagos a Tukán**: no cuenta en lo cobrado)
con lugares, catálogo, paquetes y 6 eventos de ejemplo (boda confirmada con pagos y contrato, quince años separado, corporativo cotizado,
dos consultas y un aniversario realizado; clientes con correos de example.com), y su dueño `revisor.google@tukanec.com`.
La contraseña (`Demo-` y 6 números) sale **una sola vez**; si se pierde, se corre otra vez `crear` (rehace todo con fechas de hoy).
El revisor no puede cambiar su contraseña. Si el correo fuera de un negocio que no es el de ejemplo, el script se niega y no toca nada.
Cuando Google apruebe:
```bash
php /var/www/tuplanner/deploy/demo_google.php borrar
```
(En el Panel Tukán Central aparece como una demo más; no hace falta atenderla.)

## Después de subir la app a Google Play por primera vez (MUY importante)

Google vuelve a firmar la app con **su propia llave**. Sin su huella, la app bajada de Google Play abre con la barra del navegador.
1. Play Console (fierrodimitry@gmail.com → «tukan software») → TuPlanner → **Prueba y lanzamiento → Configuración → Integridad de la app → Firma de apps**.
2. Copiar el **Certificado SHA-256** de la «Clave de firma de apps».
3. En el servidor, cambiando solo `HUELLA_DE_GOOGLE`:
```bash
sudo bash /var/www/tuplanner/deploy/app_android.sh HUELLA_DE_GOOGLE
```
Debe terminar en `OK`. Revisar: https://tuplanner.tukanec.com/.well-known/assetlinks.json muestra **las dos** huellas.
Con Google: `https://digitalassetlinks.googleapis.com/v1/assetlinks:check?source.web.site=https://tuplanner.tukanec.com&relation=delegate_permission/common.handle_all_urls&target.android_app.package_name=com.tukansoftware.tuplanner&target.android_app.certificate.sha256_fingerprint=HUELLA` → `"linked": true`.

## Cobro con Google Play (APAGADO en la versión 1.0.0)

Queda preparado con dos interruptores apagados:
- Servidor: `TUPLANNER_GOOGLE_PLAY_COBRO=1` en `config/.env` prende `cobro_google_play()` (el instalador lo conserva). Hoy no cambia nada:
  falta programar la pantalla de compra (solo con `cobro_google_play()` y `en_app_android()`), la compra con la Digital Goods API y la
  verificación de cada compra en el servidor con la API de Google Play (cuenta de servicio), como en TuSalón/TuLavadora.
- App: `"cobroGooglePlay": true` en `tukan-apps/apps/tuplanner/app.json` agrega Google Play Billing (versión nueva 1.0.1).

Productos sugeridos cuando se prenda (precio web ÷ 0,85, redondeado a ,99; códigos de plan del servidor `esencial` = Básico y `quinta` = Completo):

| ID del producto | Plan | Período | Precio web | Google Play |
|---|---|---|---|---|
| `tuplanner_basico_mensual` | Básico (`esencial`) | 1 mes | $20 | $23,99 |
| `tuplanner_basico_semestral` | Básico | 6 meses | $100 | $117,99 |
| `tuplanner_basico_anual` | Básico | 1 año | $200 | $235,99 |
| `tuplanner_completo_mensual` | Completo (`quinta`) | 1 mes | $45 | $52,99 |
| `tuplanner_completo_semestral` | Completo | 6 meses | $225 | $264,99 |
| `tuplanner_completo_anual` | Completo | 1 año | $450 | $529,99 |

Mientras esté apagado, en Play Console **no** se crean suscripciones (`tienda/tuplanner/play.json` → `"suscripciones": []`).
