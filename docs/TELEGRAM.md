# Avisos por Telegram — cómo activarlos

TuPlanner manda los avisos (pagos, firmas, consultas nuevas, cuotas y seguimientos del día) por el bot **genérico de Tukán Software**: **un solo bot para todos los programas Tukán** (decisión de Dimitry, 12 nov 2026). Cada usuario conecta su propio Telegram desde el sistema.

Como Telegram solo permite **un webhook por bot**, el webhook del bot «Tukán Software» está en el **Panel Tukán Central** (`https://panel.tukanec.com/bot_tukan.php`), que reenvía cada actualización al programa que le toca (TuPlanner usa el prefijo **`tn`**). El bot se crea **una sola vez** para todos los programas: ver `docs/BOT_TELEGRAM_TUKAN.md` en el repo `tukan-central` (pasos completos).

## 1. El bot «Tukán Software» (una sola vez, lo hace Dimitry; si ya existe, saltar al paso 2)
1. En Telegram, busca **@BotFather** y escribe `/newbot`.
2. Nombre: `Tukán Software`. Usuario (debe terminar en «bot»): por ejemplo `TukanSoftwareBot`.
3. Guarda el **token** que te da BotFather (es como una contraseña) y configura el Panel Tukán Central como dice `tukan-central/docs/BOT_TELEGRAM_TUKAN.md`.

## 2. Ponerlo en el servidor de TuPlanner
En `/var/www/tuplanner/config/.env` (nunca en GitHub; el instalador conserva las líneas `TELEGRAM_`):

```
TELEGRAM_BOT_TOKEN=el_token_del_bot_Tukan_Software
TELEGRAM_BOT_USERNAME=TukanSoftwareBot
TELEGRAM_WEBHOOK_SECRET=una_frase_secreta_larga_sin_espacios
TELEGRAM_PREFIJO=tn
```

- `TELEGRAM_BOT_TOKEN` y `TELEGRAM_BOT_USERNAME` son los **del bot genérico** (los mismos en todos los programas).
- `TELEGRAM_WEBHOOK_SECRET` es de TuPlanner: **la misma frase** se pone en el Panel Tukán Central (`BOT_TUKAN_TN_SECRETO`). Ya no se llama a `setWebhook` desde TuPlanner.
- `TELEGRAM_PREFIJO=tn` le dice a TuPlanner que use el bot genérico: el enlace de conexión lleva `?start=tn_código`. **Si no se pone, TuPlanner sigue con su bot propio como antes** (el que se creó con `/newbot`, con su propio `setWebhook` a `https://tuplanner.tukanec.com/?r=telegram&secret_token=SECRETO`). Las dos formas se aceptan al leer lo que llega.

## 3. Decirle al Panel Tukán Central dónde está TuPlanner
En `config/.env` del Panel Tukán Central:

```
BOT_TUKAN_TN_URL=https://tuplanner.tukanec.com/?r=telegram
BOT_TUKAN_TN_SECRETO=la_misma_frase_de_TELEGRAM_WEBHOOK_SECRET
```

No hace falta tocar Telegram: el webhook del bot ya apunta al Panel.

## 4. Cada usuario conecta su Telegram
En TuPlanner: **Mi cuenta → Conectar mi Telegram → Abrir Telegram y conectar → Iniciar**.

> **Al cambiar de un bot propio al bot genérico, cada persona debe volver a conectarse una vez**: los chats guardados eran del bot anterior y el bot nuevo no puede escribirles hasta que lo inicien. (El bot propio viejo se puede apagar con `deleteWebhook`.)

En TuPlanner se conectan los usuarios del equipo (cualquiera con cuenta en el programa); los clientes no usan Telegram. No hay botones en los mensajes: son avisos de solo lectura.

## Seguridad
- TuPlanner solo acepta mensajes que traen su frase secreta (`TELEGRAM_WEBHOOK_SECRET`); con el bot genérico esa frase solo la conocen el Panel Tukán Central y TuPlanner. El Panel, a su vez, solo acepta lo que trae el secreto del bot.
- El enlace para conectar sirve una sola vez.
- Sin el módulo «Avisos por Telegram» (o sin configurar el bot) no sale ningún aviso a Telegram y todo lo demás sigue funcionando.

## Pruebas
`php tests/run_tests.php` (lógica, con Telegram simulado: bloque «Telegram con el bot genérico»).
