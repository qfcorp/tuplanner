#!/usr/bin/env bash
# Pagar el plan SOLO con tarjeta desde Mi plan (src/PagoPlan.php, 10 nov 2026), de punta a punta por HTTP con el Panel Tukán
# Central SIMULADO (tests/panel_simulado.php: revisa la firma del pedido y contesta como plux_api.php; no cobra nada).
# Levanta un segundo sitio de prueba conectado a ese Panel simulado (el sitio de siempre apunta a un Panel que no existe).
# Cubre: Panel que no contesta / apagado / prendido, demo que paga, cliente con suscripción viva, regreso aprobado y no
# completado, modo de prueba, la app de Google Play (no se muestra) y registrar_pago (lo que manda el Panel al aprobarse).
# Lo carga tests/run_web.sh (con web_base.sh).
puerto_libre() { php -r '$s = stream_socket_server("tcp://127.0.0.1:0"); $n = stream_socket_get_name($s, false); echo substr($n, strrpos($n, ":") + 1);'; }
sql "UPDATE planes SET nombre='Básico', precio_mensual=12.99, modulos='', modulos_elegir=3, precio_modulo_extra=5 WHERE codigo='esencial'; UPDATE planes SET nombre='Completo', precio_mensual=25, modulos='*,asistente_ia' WHERE codigo='quinta'" > /dev/null
sql "UPDATE modulos SET precio_mensual = CASE WHEN id='asistente_ia' THEN 3 WHEN id='facturacion' THEN 0 ELSE 5 END" > /dev/null
PP=$(puerto_libre); PSITIO=$(puerto_libre)
PSIM="$TMP/panelsim"; mkdir -p "$PSIM"
panel_modo() { printf '{"activo": %s, "pruebas": %s, "caido": %s}' "$1" "${2:-true}" "${3:-false}" > "$PSIM/config.json"; }
panel_modo true
PANEL_SIM_DIR="$PSIM" php -S "127.0.0.1:$PP" tests/panel_simulado.php > /dev/null 2>&1 &
PANELSIM=$!
TUKAN_CENTRAL_URL="http://127.0.0.1:$PP" TUPLANNER_DB_NAME=$DB TUPLANNER_DB_USER=tuplanner TUPLANNER_DB_PASS=$PGPASSWORD TUPLANNER_URL="http://127.0.0.1:$PSITIO" \
  php -S "127.0.0.1:$PSITIO" -t public tests/router.php > "$TMP/servidor_pago.log" 2>&1 &
SITIOPAGO=$!
for _ in $(seq 1 50); do curl -s -o /dev/null "http://127.0.0.1:$PP/" && curl -s -o /dev/null "http://127.0.0.1:$PSITIO/?r=login" && break; sleep 0.1; done
BASE_DE_SIEMPRE=$BASE
# Último pedido que recibió el Panel simulado: ps_pedido campo[.campo]
ps_pedido() { tail -1 "$PSIM/pedidos.log" | php -r '$v = json_decode((string) stream_get_contents(STDIN), true); foreach (explode(".", $argv[1]) as $k) $v = is_array($v) ? ($v[$k] ?? null) : null; echo is_array($v) ? json_encode($v) : $v;' "$1"; }
ps_crear() { grep -c '"accion":"crear"' "$PSIM/pedidos.log" 2>/dev/null || true; }
# Cambia el estado de un cobro del Panel simulado: ps_estado id estado
ps_estado() { php -r '$a = $argv[1]; $c = json_decode((string) file_get_contents($a), true); foreach ($c as $i => $f) if ($f["id"] === (int) $argv[2]) { $c[$i]["estado"] = $argv[3]; if ($argv[3] === "activa") { $c[$i]["cobra_sola"] = true; $c[$i]["pagado_hasta"] = date("Y-m-d", strtotime("+1 month")); } } file_put_contents($a, json_encode($c));' "$PSIM/cobros.json" "$1" "$2"; }
# El Panel registra el pago en TuPlanner (conector v2, como cuando Plux aprueba): ps_registrar negocio plan periodo monto -> código HTTP
ps_registrar() {
  printf '{"operacion_id":"%s","accion":"registrar_pago","cliente_id":"%s","datos":{"plan":"%s","periodo":"%s","metodo":"plux","monto":%s,"fecha":"%s","nota":"Plux (prueba)"}}' \
     "$(php -r 'echo bin2hex(random_bytes(16));')" "$1" "$2" "$3" "$4" "$(date +%F)" > "$TMP/ps_body.json"
  local tok; tok=$(php -r '$t = time(); $c = (string) file_get_contents($argv[1]); $b = fn($s) => rtrim(strtr(base64_encode($s), "+/", "-_"), "=");
    $a = $b(json_encode(["v" => 2, "tipo" => "ejecutar", "inst" => getenv("TUKAN_CENTRAL_ID"), "email" => "sistema@tukanec.com", "nombre" => "Tukán Pagos", "rol" => "dueno", "iat" => $t, "exp" => $t + 30,
                         "nonce" => bin2hex(random_bytes(16)), "bh" => $b(hash("sha256", $c, true))]));
    echo $a . "." . $b(hash_hmac("sha256", $a, getenv("TUKAN_CENTRAL_SECRET"), true));' "$TMP/ps_body.json")
  curl -s -o "$TMP/ps.out" -w "%{http_code}" -X POST -H "Content-Type: application/json; charset=utf-8" -H "X-Tukan-Token: $tok" -H "CF-Connecting-IP: 198.51.100.77" \
       --data-binary "@$TMP/ps_body.json" "$BASE/tukan_central.php?accion=ejecutar"
}

echo "1. El Panel no contesta (el sitio de siempre): Mi plan queda igual que antes"
registrar esencial "Local Paga Solo $RANDOM"; pagar_plan esencial; NB=$NID; EB=$EMAIL
sql "UPDATE negocios SET modulos_extra='paquetes,contratos,portal_cliente,plano,asistente_ia', ruc='1790011223001', razon_social='Paga Solo S.A.' WHERE id=$NB" > /dev/null
check "Mi plan abre" "200" "$(get "r=planes")"
no_contiene "Sin «Pagar con tarjeta»" "$TMP/pag.html" "Pagar con tarjeta"
no_contiene "… ni la sección" "$TMP/pag.html" 'id="pagar-tarjeta"'
contiene "Sigue «Cuánto pagas» con Pagar por WhatsApp" "$TMP/pag.html" "Cuánto pagas"
contiene "… y los otros planes por cotización" "$TMP/pag.html" "Todos los módulos, con el Asistente IA · precio por cotización"
sed -e 's/name="csrf" value="[^"]*"/CSRF/g' "$TMP/pag.html" > "$TMP/plan_sin_panel.html"
get "r=planes&plux=aprobado" > /dev/null
no_contiene "Sin Panel, ?plux=aprobado no muestra nada" "$TMP/pag.html" "Pago recibido"
r=$(post "r=planes" -d accion=pagar_tarjeta -d plan=esencial -d periodo=mensual)
check "Pagar sin Panel: vuelve a Mi plan (no va a ninguna página de pago)" "302 $BASE/?r=planes#pagar-tarjeta" "$r"
get "r=planes" > /dev/null
contiene "… y explica que no está disponible" "$TMP/pag.html" "El pago con tarjeta no está disponible por ahora"

echo "2. Panel conectado pero con el cobro con tarjeta APAGADO para TuPlanner, o caído: igual que antes"
BASE="http://127.0.0.1:$PSITIO"
panel_modo false
entrar "$EB" claveSegura1
check "Mi plan abre" "200" "$(get "r=planes")"
no_contiene "Apagado: sin «Pagar con tarjeta»" "$TMP/pag.html" "Pagar con tarjeta"
sed -e 's/name="csrf" value="[^"]*"/CSRF/g' "$TMP/pag.html" > "$TMP/plan_apagado.html"
check "Apagado: la página es EXACTAMENTE la misma que sin Panel" "" "$(diff "$TMP/plan_sin_panel.html" "$TMP/plan_apagado.html" | head -5)"
check "El Panel recibió la pregunta «estado» firmada, con el negocio y el correo del dueño" "estado|$NB|$EB" "$(ps_pedido accion)|$(ps_pedido cliente_id)|$(ps_pedido _email)"
r=$(post "r=planes" -d accion=pagar_tarjeta -d plan=esencial -d periodo=mensual)
check "Apagado: pagar no crea nada" "0" "$(ps_crear)"
get "r=planes" > /dev/null
contiene "… y explica que no está disponible" "$TMP/pag.html" "El pago con tarjeta no está disponible por ahora"
panel_modo true true true
check "Panel caído (error 503): Mi plan abre" "200" "$(get "r=planes")"
sed -e 's/name="csrf" value="[^"]*"/CSRF/g' "$TMP/pag.html" > "$TMP/plan_caido.html"
check "Caído: la página es EXACTAMENTE la misma que sin Panel" "" "$(diff "$TMP/plan_sin_panel.html" "$TMP/plan_caido.html" | head -5)"

echo "3. Prendido: el cliente Básico ve su plan, los períodos con su precio y «Pagar con tarjeta»"
panel_modo true
check "Mi plan abre" "200" "$(get "r=planes")"
contiene "Sección «Pagar con tarjeta»" "$TMP/pag.html" 'id="pagar-tarjeta"'
contiene "Aviso de modo de prueba" "$TMP/pag.html" "modo de prueba: no se cobra de verdad"
contiene "Va a pagar su plan Básico" "$TMP/pag.html" "Vas a pagar el plan <strong>Básico</strong> (el tuyo)"
contiene "… con lo que incluye" "$TMP/pag.html" "la base + 3 módulos que tú eliges"
contiene "… y la cuenta: plan + módulos extra = \$20,99 + IVA al mes" "$TMP/pag.html" "Plan \$12,99 + tus módulos extra \$8,00 = <strong>\$20,99 + IVA al mes</strong>"
contiene "Cada mes \$20,99 + IVA" "$TMP/pag.html" "<strong>Cada mes</strong>: \$20,99 + IVA"
contiene "Cada 6 meses \$104,95 + IVA (paga 5, recibe 6)" "$TMP/pag.html" "<strong>Cada 6 meses</strong>: \$104,95 + IVA <small class=\"suave\">(paga 5, recibe 6)</small>"
contiene "Cada año \$209,90 + IVA (paga 10, recibe 12)" "$TMP/pag.html" "<strong>Cada año</strong>: \$209,90 + IVA <small class=\"suave\">(paga 10, recibe 12 + dominio propio)</small>"
contiene "Suscripción marcada por defecto y dice que se cobra sola" "$TMP/pag.html" 'name="forma" value="suscripcion" checked'
contiene "… con el texto claro" "$TMP/pag.html" "se cobra sola a tu tarjeta cada período que elegiste (ese valor + IVA)"
contiene "También puede pagar solo esta vez" "$TMP/pag.html" 'name="forma" value="una_vez"'
contiene "Botón «Pagar con tarjeta»" "$TMP/pag.html" '<button class="boton" type="submit">Pagar con tarjeta</button>'
contiene "Puede elegir el otro plan para pagarlo" "$TMP/pag.html" "r=planes&amp;plan=quinta#pagar-tarjeta"
contiene "… también desde la lista de planes" "$TMP/pag.html" "Elegir este plan y pagar con tarjeta"
no_contiene "El precio del Completo no sale mientras no lo elija" "$TMP/pag.html" "\$25,00"
contiene "Sigue «Cancelar mi plan»" "$TMP/pag.html" 'id="cancelar-plan"'
get "r=planes&plan=quinta" > /dev/null
contiene "Elige el Completo: va a pagar el Completo" "$TMP/pag.html" "Vas a pagar el plan <strong>Completo</strong>:"
contiene "… todos los módulos con el Asistente IA" "$TMP/pag.html" "todos los módulos, con el Asistente IA"
contiene "… a \$25 + IVA al mes, \$125 por 6 meses y \$250 al año" "$TMP/pag.html" "<strong>Cada 6 meses</strong>: \$125,00 + IVA"
contiene "… (anual)" "$TMP/pag.html" "<strong>Cada año</strong>: \$250,00 + IVA"
get "r=planes&plan=platino" > /dev/null
contiene "Un plan que no existe: se queda en el suyo" "$TMP/pag.html" "Vas a pagar el plan <strong>Básico</strong> (el tuyo)"

echo "4. Pagar: lo manda a la página de pago y el Panel recibe el pedido bien armado"
r=$(post "r=planes" -d accion=pagar_tarjeta -d plan=esencial -d periodo=semestral -d forma=suscripcion)
check "Lo lleva a la página de pago que dio el Panel" "302 http://127.0.0.1:$PP/pagar/1" "$r"
check "Pedido: crear una suscripción" "crear|suscripcion" "$(ps_pedido accion)|$(ps_pedido tipo)"
check "… del plan Básico cada 6 meses (recibe 6)" "esencial|Básico|semestral|6" "$(ps_pedido plan)|$(ps_pedido plan_nombre)|$(ps_pedido periodo)|$(ps_pedido meses)"
check "… por \$104,95 sin IVA" "104.95" "$(ps_pedido subtotal)"
check "… de este negocio, pagado por el dueño" "$NB|$EB" "$(ps_pedido cliente_id)|$(ps_pedido comprador.correo)"
check "… con su RUC para la factura" "1790011223001|Paga Solo S.A." "$(ps_pedido fiscal.identificacion)|$(ps_pedido fiscal.razon_social)"
check "… y vuelve a Mi plan" "http://127.0.0.1:$PSITIO/?r=planes" "$(ps_pedido volver)"
check "TuPlanner no cambia nada todavía (lo que vale es el aviso del Panel)" "0" "$(sql "SELECT count(*) FROM suscripciones WHERE negocio_id=$NB")"

echo "5. Con una suscripción viva: se ve su estado y NO se ofrece crear otra"
get "r=planes" > /dev/null
contiene "Se ve el pago esperando" "$TMP/pag.html" "Esperando el pago"
contiene "… con su valor con IVA" "$TMP/pag.html" "\$120,69 con IVA"
contiene "… «Pagar ahora» lleva a la página de pago" "$TMP/pag.html" "href=\"http://127.0.0.1:$PP/pagar/1\">Pagar ahora"
contiene "… y se puede cancelar para elegir otro" "$TMP/pag.html" 'name="accion" value="cancelar_tarjeta"'
contiene "Explica que tiene un pago sin terminar" "$TMP/pag.html" "Tienes un pago con tarjeta sin terminar"
no_contiene "Sin el botón de pagar otra vez" "$TMP/pag.html" 'value="pagar_tarjeta"'
no_contiene "Sin «Elegir este plan y pagar con tarjeta»" "$TMP/pag.html" "Elegir este plan y pagar con tarjeta"
r=$(post "r=planes" -d accion=pagar_tarjeta -d plan=esencial -d periodo=mensual)
check "Si igual lo manda: no crea otra" "1" "$(ps_crear)"
get "r=planes" > /dev/null
contiene "… y lo explica" "$TMP/pag.html" "Ya tienes una suscripción con tarjeta"
ps_estado 1 activa
get "r=planes" > /dev/null
contiene "Aprobada: suscripción activa, se cobra sola" "$TMP/pag.html" "Suscripción activa"
contiene "… dice que se cobra sola" "$TMP/pag.html" "se cobra sola a tu tarjeta"
contiene "… y que no hace falta pagar otra vez" "$TMP/pag.html" "Ya tienes una suscripción con tarjeta: se cobra sola"
no_contiene "La activa no trae «Cancelar este pago» (va por Cancelar mi plan)" "$TMP/pag.html" 'value="cancelar_tarjeta"'
no_contiene "Sin botón de pagar" "$TMP/pag.html" 'value="pagar_tarjeta"'
contiene "Sigue «Cancelar mi plan»" "$TMP/pag.html" 'name="confirmo" value="1" required'
post "r=planes" -d accion=cancelar_tarjeta -d id=1 > /dev/null
get "r=planes" > /dev/null
contiene "Cancelar la activa por este botón: no deja" "$TMP/pag.html" "Ese pago ya no se puede cancelar aquí"

echo "6. Regreso de la página de pago"
get "r=planes&plux=aprobado" > /dev/null
contiene "Aprobado: «¡Pago recibido!»" "$TMP/pag.html" "¡Pago recibido! Tu plan se actualiza en unos segundos"
get "r=planes&plux=rechazado" > /dev/null
contiene "Otro valor: «El pago no se completó»" "$TMP/pag.html" "El pago no se completó. Puedes intentarlo otra vez"
no_contiene "… sin decir que se recibió" "$TMP/pag.html" "Pago recibido"
get "r=planes" > /dev/null
no_contiene "Sin regreso: ningún aviso" "$TMP/pag.html" 'id="pago-'
panel_modo true false
get "r=planes" > /dev/null
no_contiene "Con cobros de verdad: sin el aviso de modo de prueba" "$TMP/pag.html" "modo de prueba"
panel_modo true

echo "7. Una cuenta DEMO elige plan, paga y queda como cliente activo"
registrar quinta "Quinta Demo Paga $RANDOM"; ND=$NID; ED=$EMAIL
check "Es una demo" "prueba" "$(sql "SELECT estado FROM negocios WHERE id=$ND")"
check "Mi plan abre" "200" "$(get "r=planes")"
contiene "La demo ve «Pagar con tarjeta»" "$TMP/pag.html" 'id="pagar-tarjeta"'
contiene "… con su plan de la demo (Completo \$25)" "$TMP/pag.html" "<strong>Cada mes</strong>: \$25,00 + IVA"
contiene "… y dice que la demo pasa a ser su cuenta" "$TMP/pag.html" "tu demo pasa a ser tu cuenta: nada de lo que cargaste se pierde"
get "r=planes&plan=esencial" > /dev/null
contiene "Elige el Básico: \$12,99 + IVA al mes" "$TMP/pag.html" "<strong>Cada mes</strong>: \$12,99 + IVA"
contiene "… \$64,95 por 6 meses" "$TMP/pag.html" "<strong>Cada 6 meses</strong>: \$64,95 + IVA"
contiene "… \$129,90 por un año" "$TMP/pag.html" "<strong>Cada año</strong>: \$129,90 + IVA"
r=$(post_desde "r=planes&plan=esencial" "r=planes" -d accion=pagar_tarjeta -d plan=esencial -d periodo=mensual -d forma=una_vez)
check "Paga el Básico un mes, solo esta vez: va a la página de pago" "302 http://127.0.0.1:$PP/pagar/2" "$r"
check "Pedido: un cobro de una vez del Básico por \$12,99" "cobro|esencial|mensual|1|12.99" "$(ps_pedido tipo)|$(ps_pedido plan)|$(ps_pedido periodo)|$(ps_pedido meses)|$(ps_pedido subtotal)"
check "Sin RUC: no manda datos de factura" "[]" "$(ps_pedido fiscal)"
check "Sigue en demo hasta que el Panel avise" "prueba" "$(sql "SELECT estado FROM negocios WHERE id=$ND")"
check "Plux aprobó: el Panel registra el pago (registrar_pago)" "200" "$(ps_registrar $ND esencial mensual 12.99)"
check "La demo queda ACTIVA con el plan Básico, pagada un mes" "activo|esencial|$(sql "SELECT (CURRENT_DATE + interval '1 month' - interval '1 day')::date")" "$(sql "SELECT estado||'|'||plan_codigo||'|'||activo_hasta FROM negocios WHERE id=$ND")"
check "El pago quedó guardado: Básico mensual \$12,99 por Plux" "esencial|mensual|12.99|plux" "$(sql "SELECT plan_codigo||'|'||periodo||'|'||monto||'|'||metodo_pago FROM suscripciones WHERE negocio_id=$ND")"
get "r=planes&plux=aprobado" > /dev/null
contiene "Al volver: pago recibido" "$TMP/pag.html" "¡Pago recibido!"
contiene "Ya es cliente: Plan Básico activo" "$TMP/pag.html" "Plan <strong>Básico</strong>"
contiene "… «Pagado hasta el»" "$TMP/pag.html" "Pagado hasta el"
contiene "… con sus 3 módulos por elegir" "$TMP/pag.html" 'data-por-elegir="3"'
contiene "… y el pago en «Pagos hechos»" "$TMP/pag.html" "Básico · Mensual"
contiene "Ahora tiene «Cancelar mi plan»" "$TMP/pag.html" 'id="cancelar-plan"'
no_contiene "Ya no dice que está en demo" "$TMP/pag.html" "Estás en tu demo"

echo "8. Demo con suscripción: paga cada año el Completo; puede cancelar el pago sin terminar y elegir otro"
registrar quinta "Hacienda Demo Anual $RANDOM"; NA=$NID
r=$(post "r=planes" -d accion=pagar_tarjeta -d plan=quinta -d periodo=anual)
check "Sin elegir forma: es suscripción (por defecto)" "suscripcion|quinta|anual|12|250" "$(ps_pedido tipo)|$(ps_pedido plan)|$(ps_pedido periodo)|$(ps_pedido meses)|$(ps_pedido subtotal)"
get "r=planes" > /dev/null
contiene "Tiene el pago sin terminar" "$TMP/pag.html" "Tienes un pago con tarjeta sin terminar"
post "r=planes" -d accion=cancelar_tarjeta -d id=3 > /dev/null
check "Cancelar este pago: el Panel recibe «cancelar»" "cancelar|$NA|3" "$(ps_pedido accion)|$(ps_pedido cliente_id)|$(ps_pedido id)"
get "r=planes" > /dev/null
contiene "… y vuelve a ofrecer «Pagar con tarjeta»" "$TMP/pag.html" 'value="pagar_tarjeta"'
post "r=planes" -d accion=cancelar_tarjeta -d id=1 > /dev/null
check "No puede cancelar el pago de otro negocio: ni se le pide al Panel" "1|activa" "$(grep -c '"accion":"cancelar"' "$PSIM/pedidos.log")|$(php -r 'echo json_decode((string) file_get_contents($argv[1]), true)[0]["estado"];' "$PSIM/cobros.json")"
r=$(post "r=planes" -d accion=pagar_tarjeta -d plan=quinta -d periodo=trimestral)
check "Período que no existe: no crea nada" "3" "$(ps_crear)"
check "Plux aprobó el anual: registrar_pago" "200" "$(ps_registrar $NA quinta anual 250)"
check "Queda activa 12 meses con el Completo" "activo|quinta|$(sql "SELECT (CURRENT_DATE + interval '12 months' - interval '1 day')::date")" "$(sql "SELECT estado||'|'||plan_codigo||'|'||activo_hasta FROM negocios WHERE id=$NA")"

echo "9. Permisos y la app de Google Play"
VEND="vend$RANDOM@prueba.ec"
sql "INSERT INTO usuarios (negocio_id, nombre, email, password_hash, rol) SELECT $NA, 'Vendedora', '$VEND', password_hash, 'vendedor' FROM usuarios WHERE negocio_id=$NA AND rol='dueno'" > /dev/null
entrar "$VEND" claveSegura1
get "r=planes" > /dev/null
no_contiene "Un vendedor no ve «Pagar con tarjeta» (Mi plan es del dueño o administrador)" "$TMP/pag.html" 'id="pagar-tarjeta"'
ANTES=$(ps_crear)
post "r=planes" -d accion=pagar_tarjeta -d plan=quinta -d periodo=mensual > /dev/null
check "… ni puede pedir el pago" "$ANTES" "$(ps_crear)"
registrar esencial "Local App Tarjeta $RANDOM"; pagar_plan esencial
get "r=planes" > /dev/null
contiene "En la web: «Pagar con tarjeta»" "$TMP/pag.html" 'id="pagar-tarjeta"'
get "r=inicio&app=play" > /dev/null
LINEAS=$(wc -l < "$PSIM/pedidos.log")
check "En la app, Mi plan abre" "200" "$(get "r=planes&plux=aprobado")"
no_contiene "En la app: sin «Pagar con tarjeta»" "$TMP/pag.html" "Pagar con tarjeta"
no_contiene "… sin la sección" "$TMP/pag.html" 'id="pagar-tarjeta"'
no_contiene "… sin ningún monto en dólares" "$TMP/pag.html" '\$[0-9]'
no_contiene "… sin enlaces para elegir plan y pagar" "$TMP/pag.html" "plan=quinta"
check "… y ni siquiera se le pregunta al Panel" "$LINEAS" "$(wc -l < "$PSIM/pedidos.log")"
ANTES=$(ps_crear)
r=$(post "r=planes" -d accion=pagar_tarjeta -d plan=esencial -d periodo=mensual)
check "En la app el servidor no acepta pagar con tarjeta" "$ANTES" "$(ps_crear)"
get "r=inicio&app=web" > /dev/null

BASE=$BASE_DE_SIEMPRE
kill $PANELSIM $SITIOPAGO 2>/dev/null
if grep -qiE "fatal|warning|deprecated|notice" "$TMP/servidor_pago.log"; then
  FALLAS=$((FALLAS+1)); echo "  FALLA avisos de PHP en el sitio conectado al Panel simulado:"; grep -iE "fatal|warning|deprecated|notice" "$TMP/servidor_pago.log" | sort | uniq -c | head -10
else OK=$((OK+1)); echo "  OK    Sin avisos de PHP en el sitio conectado al Panel simulado"; fi
