# App de iPhone (App Store, regla 3.1.1 de Apple; regla SIEMPRE de Dimitry desde el 12 nov 2026): dentro de la app NO se ve ningún
# precio de los planes o módulos de Tukán, ni botones, enlaces o formularios para pagar, contratar, cotizar, mejorar o cancelar el
# plan, ni textos que digan dónde pagar (WhatsApp de ventas, Google Play, pagos.tukanec.com). Se reconoce por «TukanIOS» en el
# navegador (User-Agent) o por la cookie canal=ios que deja ?app=ios (u ?origen=ios). Lo que el negocio cobra a SUS clientes sigue igual.
# Sin TukanIOS (la web y la app de Google Play) todo queda como antes. Lo carga tests/run_web.sh (con web_base.sh).
UA_IOS="Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 TukanIOS/1.0.0"
mkdir -p "$TMP/curl_ios"; printf 'user-agent = "%s"\n' "$UA_IOS" > "$TMP/curl_ios/.curlrc"
# iphone: todas las llamadas de curl (get, post) mandan el navegador de la app de iPhone · web: el navegador de siempre
iphone() { export CURL_HOME="$TMP/curl_ios"; }
web() { unset CURL_HOME; }
# Revisa que la página no tenga nada de lo que vende Tukán: sin_venta "Qué pantalla"
sin_venta() {
  no_contiene "$1: sin montos en dólares" "$TMP/pag.html" '\$[0-9]'
  no_contiene "$1: sin «USD»" "$TMP/pag.html" "USD"
  no_contiene "$1: sin «Paga» ni «Pagar»" "$TMP/pag.html" 'Paga\b\|Pagar\b'
  no_contiene "$1: sin «Contratar»" "$TMP/pag.html" "ontratar"
  no_contiene "$1: sin «cotización» del plan" "$TMP/pag.html" 'Pedir cotizaci\|por cotizaci\|cotizamos\|con el valor'
  no_contiene "$1: sin «Renovar»" "$TMP/pag.html" "Renov"
  no_contiene "$1: sin WhatsApp de ventas" "$TMP/pag.html" "wa.me/"
  no_contiene "$1: sin pagos.tukanec.com" "$TMP/pag.html" "pagos.tukanec.com"
  no_contiene "$1: sin decir que se paga en Google Play" "$TMP/pag.html" "Google Play"
}
puerto_libre() { php -r '$s = stream_socket_server("tcp://127.0.0.1:0"); $n = stream_socket_get_name($s, false); echo substr($n, strrpos($n, ":") + 1);'; }
sql "UPDATE planes SET nombre='Básico', precio_mensual=12.99, modulos='', modulos_elegir=3, precio_modulo_extra=5 WHERE codigo='esencial'; UPDATE planes SET nombre='Completo', precio_mensual=25, modulos='*,asistente_ia' WHERE codigo='quinta'" > /dev/null
sql "UPDATE modulos SET precio_mensual = CASE WHEN id='asistente_ia' THEN 3 WHEN id='facturacion' THEN 0 ELSE 5 END" > /dev/null

echo "1. Cómo se reconoce la app de iPhone"
web; rm -f "$JAR"
get "r=login" > /dev/null
no_contiene "Navegador normal: sin la marca tukan-ios" "$TMP/pag.html" "tukan-ios"
iphone; get "r=login" > /dev/null
contiene "Con TukanIOS en el navegador: <body> con la clase tukan-ios" "$TMP/pag.html" "tukan-ios"
contiene "… la pantalla de entrar sigue igual" "$TMP/pag.html" "Solicitar demo"
web; rm -f "$JAR"
get "r=login&app=ios" > /dev/null
contiene "?app=ios deja la cookie canal=ios" "$JAR" "canal"
get "r=login" > /dev/null
contiene "… y con la cookie se sabe que es la app (sin TukanIOS)" "$TMP/pag.html" "tukan-ios"
rm -f "$JAR"; get "r=login&origen=ios" > /dev/null
contiene "?origen=ios también deja la cookie" "$JAR" "canal"
rm -f "$JAR"; get "r=login&app=play" > /dev/null
no_contiene "?app=play (Google Play) no deja la cookie del iPhone" "$JAR" "canal"

echo "2. Mi plan: en la web igual que antes; en el iPhone sin precios, sin cotizar, sin pagar y sin cancelar"
web; registrar esencial "Local iPhone $RANDOM"; pagar_plan esencial
sql "UPDATE negocios SET modulos_extra='paquetes' WHERE id=$NID" > /dev/null
check "Web: Mi plan abre" "200" "$(get "r=planes")"
contiene "Web: precio de su plan" "$TMP/pag.html" 'class="mp-precio"'
contiene "Web: Pedir cotización" "$TMP/pag.html" "Pedir cotización"
contiene "Web: WhatsApp de ventas" "$TMP/pag.html" "wa.me/"
contiene "Web: Cancelar mi plan" "$TMP/pag.html" 'id="cancelar-plan"'
no_contiene "Web: sin la marca tukan-ios" "$TMP/pag.html" "tukan-ios"
iphone
check "iPhone: Mi plan abre" "200" "$(get "r=planes")"
contiene "iPhone: <body> con tukan-ios" "$TMP/pag.html" "tukan-ios"
contiene "iPhone: muestra el plan" "$TMP/pag.html" "Plan <strong>Básico</strong>"
contiene "iPhone: muestra hasta cuándo está activo" "$TMP/pag.html" "Pagado hasta el"
contiene "iPhone: puede elegir los módulos incluidos en su plan (no cuesta más)" "$TMP/pag.html" "Elegir este módulo"
sin_venta "iPhone Mi plan"
no_contiene "iPhone: sin el precio de su plan" "$TMP/pag.html" 'class="mp-precio"'
no_contiene "iPhone: sin «Cuánto pagas»" "$TMP/pag.html" "Cuánto pagas"
no_contiene "iPhone: sin otros planes" "$TMP/pag.html" 'id="mp-t-planes"'
no_contiene "iPhone: sin pagos hechos" "$TMP/pag.html" "Pagos hechos"
no_contiene "iPhone: sin «Cancelar mi plan»" "$TMP/pag.html" 'id="cancelar-plan"'
no_contiene "iPhone: sin formulario para cancelar" "$TMP/pag.html" 'value="cancelar_plan"'
get "r=mas" > /dev/null
no_contiene "iPhone Más: sin «lo que pagas»" "$TMP/pag.html" "lo que pagas"

echo "3. En el iPhone el servidor rechaza cotizar, cambiar de plan y cancelar (mensaje neutro)"
ANTES=$(sql "SELECT count(*) FROM modulos_solicitudes WHERE negocio_id=$NID")
post_desde "r=mi_cuenta" "r=planes" --data-urlencode "accion=pedir_plan" --data-urlencode "plan=quinta" > /dev/null
check "Pedir otro plan: no se guarda" "$ANTES" "$(sql "SELECT count(*) FROM modulos_solicitudes WHERE negocio_id=$NID")"
get "r=planes" > /dev/null
contiene "… mensaje neutro" "$TMP/pag.html" "Esta acción no está disponible en la app."
post_desde "r=mi_cuenta" "r=planes" --data-urlencode "accion=pedir" --data-urlencode "modulo=asistente_ia" > /dev/null
check "Cotizar un módulo fuera del plan: no se guarda" "$ANTES" "$(sql "SELECT count(*) FROM modulos_solicitudes WHERE negocio_id=$NID")"
post_desde "r=mi_cuenta" "r=planes" --data-urlencode "accion=pedir" --data-urlencode "modulo=contratos" > /dev/null
check "Elegir un módulo incluido en su plan sí se guarda" "$((ANTES+1))" "$(sql "SELECT count(*) FROM modulos_solicitudes WHERE negocio_id=$NID")"
post_desde "r=mi_cuenta" "r=planes" -d accion=cancelar_plan -d confirmo=1 --data-urlencode "motivo=desde el iPhone" > /dev/null
check "Cancelar el plan: no se guarda" "0" "$(sql "SELECT count(*) FROM tukan_cancelaciones WHERE cliente_id='$NID'" 2>/dev/null || echo 0)"
get "r=planes" > /dev/null
contiene "… mensaje neutro" "$TMP/pag.html" "Esta acción no está disponible en la app."
no_contiene "… sin decir dónde se cancela" "$TMP/pag.html" "Google Play"
post_desde "r=mi_cuenta" "r=planes" -d accion=pasar_completo > /dev/null
check "Pasarse al Completo: el plan no cambia" "esencial" "$(sql "SELECT plan_codigo FROM negocios WHERE id=$NID")"

echo "4. Módulo que no está en el plan"
check "iPhone: abre Invitados (apagado)" "403" "$(get "r=invitados")"
contiene "… dice que no está en su plan" "$TMP/pag.html" "Este módulo no está en tu plan"
sin_venta "iPhone módulo apagado"
web; get "r=invitados" > /dev/null
contiene "Web: sí ofrece Pedir cotización" "$TMP/pag.html" "Pedir cotización"

echo "5. Límite de lugares (el Básico no tiene límite de lugares: se pone 1 solo para la prueba)"
sql "UPDATE planes SET max_espacios=1 WHERE codigo='esencial'" > /dev/null
iphone; get "r=espacios" > /dev/null
contiene "iPhone: dice el límite" "$TMP/pag.html" "permite 1 lugar activo"
no_contiene "… sin invitar a cambiar de plan" "$TMP/pag.html" "cambia de plan"
web; get "r=espacios" > /dev/null
contiene "Web: sí invita a cambiar de plan" "$TMP/pag.html" "cambia de plan"
sql "UPDATE planes SET max_espacios=NULL WHERE codigo='esencial'" > /dev/null

echo "6. Plan por vencer: sin «Renovar» en el iPhone"
sql "UPDATE negocios SET activo_hasta=CURRENT_DATE + 3 WHERE id=$NID" > /dev/null
get "r=inicio" > /dev/null
contiene "Web: Renovar" "$TMP/pag.html" ">Renovar<"
iphone; get "r=inicio" > /dev/null
contiene "iPhone: avisa cuándo vence (información neutra)" "$TMP/pag.html" "Tu plan vence en 3 días"
no_contiene "… sin Renovar" "$TMP/pag.html" ">Renovar<"

echo "7. Demo terminada, plan vencido y cuenta pausada en el iPhone"
sql "UPDATE negocios SET estado='prueba', prueba_hasta=CURRENT_DATE - 1 WHERE id=$NID" > /dev/null
get "r=prueba_terminada" > /dev/null
contiene "Demo terminada (texto neutro)" "$TMP/pag.html" "Tu demo terminó"
contiene "… sus datos están guardados" "$TMP/pag.html" "Todo está guardado"
sin_venta "iPhone demo terminada"
sql "UPDATE negocios SET estado='activo', activo_hasta=CURRENT_DATE - 40 WHERE id=$NID" > /dev/null
get "r=prueba_terminada" > /dev/null
contiene "Plan vencido (texto neutro)" "$TMP/pag.html" "Tu plan venció"
sin_venta "iPhone plan vencido"
sql "UPDATE negocios SET estado='suspendido', pausa_tipo='falta_pago', pausa_motivo='Pago pendiente' WHERE id=$NID" > /dev/null
get "r=prueba_terminada" > /dev/null
contiene "Cuenta pausada" "$TMP/pag.html" "Tu cuenta está pausada"
sin_venta "iPhone cuenta pausada"
web; get "r=prueba_terminada" > /dev/null
contiene "Web: WhatsApp de Tukán" "$TMP/pag.html" "wa.me/"
sql "UPDATE negocios SET estado='prueba', prueba_hasta=CURRENT_DATE - 1, pausa_tipo=NULL, pausa_motivo=NULL WHERE id=$NID" > /dev/null
get "r=prueba_terminada" > /dev/null
contiene "Web: demo terminada con «contratar»" "$TMP/pag.html" "contratar"
sql "UPDATE negocios SET estado='activo', activo_hasta=CURRENT_DATE + 30 WHERE id=$NID" > /dev/null

echo "8. Pedir demo (registro) en el iPhone: el formulario sí, el WhatsApp de ventas no"
rm -f "$JAR"; iphone
check "iPhone: Solicitar demo abre" "200" "$(get "r=registro")"
contiene "… con el formulario" "$TMP/pag.html" 'id="form-demo"'
sin_venta "iPhone registro"
web; get "r=registro" > /dev/null
contiene "Web: Pedir demo por WhatsApp" "$TMP/pag.html" "wa.me/"

echo "9. Lo que el negocio cobra a SUS clientes sigue igual en el iPhone"
registrar quinta "Agencia iPhone Cobros $RANDOM"; pagar_plan quinta
EV=$(crear_evento_sql "$(date -d '+40 days' +%F)" separado)
sql "UPDATE eventos SET total=2000 WHERE id=$EV" > /dev/null
iphone
check "iPhone: pagos del evento abre" "200" "$(get "r=evento_pagos&id=$EV")"
contiene "… con el registro de pagos del cliente" "$TMP/pag.html" "Registrar un pago recibido"
contiene "… y los montos del evento en dólares" "$TMP/pag.html" '\$2.000,00'

echo "10. Borrar mi cuenta sigue en el iPhone (Apple lo exige)"
check "iPhone: Mi cuenta abre" "200" "$(get "r=mi_cuenta")"
contiene "Sección Borrar mi cuenta" "$TMP/pag.html" 'id="borrar-cuenta"'

echo "11. Google Play sigue como antes (su propio texto para cancelar; sin la marca del iPhone)"
web; get "r=inicio&app=play" > /dev/null
get "r=planes" > /dev/null
contiene "Google Play: cancelar en Google Play" "$TMP/pag.html" "Google Play → Pagos y suscripciones"
no_contiene "Google Play: sin la marca tukan-ios" "$TMP/pag.html" "tukan-ios"
get "r=inicio&app=web" > /dev/null

echo "12. Con el cobro con tarjeta prendido (Panel simulado): en el iPhone no aparece ni se acepta"
PP=$(puerto_libre); PSITIO=$(puerto_libre)
PSIM="$TMP/panelsim_ios"; mkdir -p "$PSIM"
printf '{"activo": true, "pruebas": true, "caido": false}' > "$PSIM/config.json"
PANEL_SIM_DIR="$PSIM" php -S "127.0.0.1:$PP" tests/panel_simulado.php > /dev/null 2>&1 &
PANELSIM_IOS=$!
TUKAN_CENTRAL_URL="http://127.0.0.1:$PP" TUPLANNER_DB_NAME=$DB TUPLANNER_DB_USER=tuplanner TUPLANNER_DB_PASS=$PGPASSWORD TUPLANNER_URL="http://127.0.0.1:$PSITIO" \
  php -S "127.0.0.1:$PSITIO" -t public tests/router.php > "$TMP/servidor_ios.log" 2>&1 &
SITIO_IOS=$!
for _ in $(seq 1 50); do curl -s -o /dev/null "http://127.0.0.1:$PP/" && curl -s -o /dev/null "http://127.0.0.1:$PSITIO/?r=login" && break; sleep 0.1; done
BASE_DE_SIEMPRE=$BASE; BASE="http://127.0.0.1:$PSITIO"
ps_crear() { grep -c '"accion":"crear"' "$PSIM/pedidos.log" 2>/dev/null || true; }
web; registrar esencial "Local iPhone Tarjeta $RANDOM"; pagar_plan esencial
get "r=planes" > /dev/null
contiene "Web: «Pagar con tarjeta»" "$TMP/pag.html" 'id="pagar-tarjeta"'
iphone
check "iPhone: Mi plan abre" "200" "$(get "r=planes")"
no_contiene "iPhone: sin la sección de pagar con tarjeta" "$TMP/pag.html" 'id="pagar-tarjeta"'
no_contiene "… sin «Pasarme al Completo»" "$TMP/pag.html" "Pasarme al Completo"
sin_venta "iPhone Mi plan con tarjeta prendida"
ANTES=$(ps_crear)
post_desde "r=mi_cuenta" "r=planes" -d accion=pagar_tarjeta -d plan=esencial -d periodo=mensual > /dev/null
check "iPhone: el servidor no acepta pagar con tarjeta" "$ANTES" "$(ps_crear)"
get "r=planes" > /dev/null
contiene "… mensaje neutro" "$TMP/pag.html" "Esta acción no está disponible en la app."
web
BASE=$BASE_DE_SIEMPRE
kill $PANELSIM_IOS $SITIO_IOS 2>/dev/null
if grep -qiE "fatal|warning|deprecated|notice" "$TMP/servidor_ios.log"; then
  FALLAS=$((FALLAS+1)); echo "  FALLA avisos de PHP en el sitio con el Panel simulado:"; grep -iE "fatal|warning|deprecated|notice" "$TMP/servidor_ios.log" | sort | uniq -c | head -10
else OK=$((OK+1)); echo "  OK    Sin avisos de PHP en el sitio con el Panel simulado"; fi
