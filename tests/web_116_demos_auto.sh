#!/usr/bin/env bash
# Demos automáticas de 3 días (10 nov 2026, contrato de las demos automáticas): formulario «Solicitar demo» con los 6 datos
# obligatorios, aviso al instante al Panel Tukán Central (demo_aviso.php, token v2 de tipo «demo») con un Panel SIMULADO,
# /manual.pdf y /primeros_pasos.pdf públicos, tarjeta de la demo en Inicio y botones de descarga en Ayuda.
# Lo carga tests/run_web.sh (con web_base.sh).
puerto_libre() { php -r '$s = stream_socket_server("tcp://127.0.0.1:0"); $n = stream_socket_get_name($s, false); echo substr($n, strrpos($n, ":") + 1);'; }
D116_C96() { php -r '$v = json_decode((string) file_get_contents($argv[1]), true); foreach (explode(".", $argv[2]) as $k) $v = is_array($v) ? ($v[$k] ?? null) : null; echo is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : $v;' "$1" "$2"; }
# Pedido completo: d116_pedir correo [campo=valor que reemplaza ...] -> "codigo destino"
d116_pedir() {
  local correo=$1; shift
  declare -A c=([nombre]="Paula Interesada" [nombre_comercial]="Quinta Las Demos" [celular]="0991234567" [direccion]="Av. de los Shyris N35-71 y Suecia" [ciudad]="Quito" [email]="$correo")
  local kv; for kv in "$@"; do c[${kv%%=*}]=${kv#*=}; done
  local a=(); local k; for k in "${!c[@]}"; do a+=(--data-urlencode "$k=${c[$k]}"); done
  post "r=registro" "${a[@]}" --data-urlencode "modulos[]=invitados"
}
rm -f "$JAR"

echo "1. El formulario pide los 6 datos (un solo teléfono: el celular), todos obligatorios, y dice que la demo dura 3 días"
check "Días de demo: 3 (migración 116, una sola vez)" "3|2026-11-10" "$(sql "SELECT (SELECT valor FROM ajustes WHERE clave='dias_prueba')||'|'||(SELECT valor FROM ajustes WHERE clave='demo_3_dias')")"
check "Solicitar demo abre" "200" "$(get "r=registro")"
for campo in nombre nombre_comercial celular direccion ciudad email; do
  check "Campo «$campo» obligatorio en el navegador" "1" "$(grep -c "name=\"$campo\"[^>]* required" "$TMP/pag.html")"
done
no_contiene "Ya no pide teléfono fijo" "$TMP/pag.html" 'name="telefono"'
contiene "El teléfono se llama «Celular / WhatsApp»" "$TMP/pag.html" '<label for="celular">Celular / WhatsApp</label>'
contiene "Dice adónde llega la clave" "$TMP/pag.html" "Te enviaremos la clave de tu demo a este correo: escríbelo bien."
contiene "Dice que la demo dura 3 días" "$TMP/pag.html" "La demo dura 3 días."
contiene "… y que prepararla toma entre 5 y 10 minutos" "$TMP/pag.html" "Preparar tu demo toma entre 5 y 10 minutos aproximadamente."

echo "2. Incompleto: se rechaza campo por campo y conserva lo escrito"
N0=$(sql "SELECT count(*) FROM pedidos_demo")
for campo in nombre nombre_comercial celular direccion ciudad email; do
  d116_pedir "falta_$campo@prueba.ec" "$campo=" > /dev/null
  contiene "Sin «$campo»: error junto al campo" "$TMP/post.html" "id=\"err-$campo\""
  check "… y es el único marcado" "1" "$(grep -o 'class="campo-error"' "$TMP/post.html" | wc -l)"
done
contiene "Conserva lo escrito (nombre comercial)" "$TMP/post.html" 'name="nombre_comercial" value="Quinta Las Demos"'
contiene "… (dirección)" "$TMP/post.html" 'name="direccion" value="Av. de los Shyris N35-71 y Suecia"'
d116_pedir "correo malo@" > /dev/null
contiene "Correo malo: rechazado junto al campo" "$TMP/post.html" 'id="err-email" role="alert" style="color:#7E2A23;display:block">El correo no es válido'
d116_pedir "cel@prueba.ec" "celular=0212345678" > /dev/null
contiene "Celular que no empieza con 09: rechazado" "$TMP/post.html" "Escribe tu celular o WhatsApp: 09XXXXXXXX o +5939XXXXXXXX."
d116_pedir "tel@prueba.ec" "celular=abc123" > /dev/null
contiene "Celular con letras: rechazado" "$TMP/post.html" 'id="err-celular"'
check "Nada de eso se guardó" "$N0" "$(sql "SELECT count(*) FROM pedidos_demo")"

echo "3. Completo y el Panel NO contesta (sitio de siempre): el pedido se guarda igual y sale la página de gracias"
C1="paula$RANDOM@interesada.ec"
d116_pedir " ${C1^^} " "celular=+593 99 123 4567" > /dev/null
contiene "Página de gracias con el texto del contrato" "$TMP/post.html" "¡Listo! Estamos preparando tu demo: toma entre 5 y 10 minutos aproximadamente. Cuando esté lista te llega al correo tu clave, el manual y la guía de primeros pasos. Revisa también la carpeta de spam."
no_contiene "Sin ningún error a la vista" "$TMP/post.html" "aviso-error"
check "Guardado con los 6 datos (correo en minúsculas y sin espacios; celular solo con números y +; la columna telefono igual al celular)" "Paula Interesada|Quinta Las Demos|+593991234567|+593991234567|Av. de los Shyris N35-71 y Suecia|Quito|$C1|nueva" \
      "$(sql "SELECT nombre||'|'||negocio||'|'||telefono||'|'||whatsapp||'|'||direccion||'|'||ciudad||'|'||email||'|'||estado FROM pedidos_demo WHERE email='$C1'")"
PD=$(sql "SELECT id FROM pedidos_demo WHERE email='$C1'")

echo "4. El Panel lo lee por el conector (solicitud demo_pedida) con las claves exactas"
printf '{"id":"demo_pedida:%s"}' "$PD" > "$TMP/d116_body.json"
TOK=$(php -r '$t = time(); $c = (string) file_get_contents($argv[1]); $b = fn($s) => rtrim(strtr(base64_encode($s), "+/", "-_"), "=");
  $a = $b(json_encode(["v" => 2, "tipo" => "solicitud", "inst" => getenv("TUKAN_CENTRAL_ID"), "email" => "sistema@tukanec.com", "nombre" => "Panel", "rol" => "dueno", "iat" => $t, "exp" => $t + 60,
                       "nonce" => bin2hex(random_bytes(16)), "bh" => $b(hash("sha256", $c, true))]));
  echo $a . "." . $b(hash_hmac("sha256", $a, getenv("TUKAN_CENTRAL_SECRET"), true));' "$TMP/d116_body.json")
r=$(curl -s -o "$TMP/d116.out" -w "%{http_code}" -X POST -H "Content-Type: application/json; charset=utf-8" -H "X-Tukan-Token: $TOK" -H "CF-Connecting-IP: 198.51.100.88" --data-binary "@$TMP/d116_body.json" "$BASE/tukan_central.php?accion=solicitud")
check "Acción «solicitud» del conector: 200" "200" "$r"
check "Es una demo pedida, pendiente" "demo_pedida|pendiente" "$(D116_C96 "$TMP/d116.out" solicitud.tipo)|$(D116_C96 "$TMP/d116.out" solicitud.estado)"
check "detalle.nombre" "Paula Interesada" "$(D116_C96 "$TMP/d116.out" solicitud.detalle.nombre)"
check "detalle.nombre_comercial" "Quinta Las Demos" "$(D116_C96 "$TMP/d116.out" solicitud.detalle.nombre_comercial)"
check "detalle.telefono (el mismo valor del celular, por compatibilidad)" "+593991234567" "$(D116_C96 "$TMP/d116.out" solicitud.detalle.telefono)"
check "detalle.celular" "+593991234567" "$(D116_C96 "$TMP/d116.out" solicitud.detalle.celular)"
check "detalle.direccion" "Av. de los Shyris N35-71 y Suecia" "$(D116_C96 "$TMP/d116.out" solicitud.detalle.direccion)"
check "detalle.ciudad" "Quito" "$(D116_C96 "$TMP/d116.out" solicitud.detalle.ciudad)"
check "detalle.email" "$C1" "$(D116_C96 "$TMP/d116.out" solicitud.detalle.email)"
check "Sigue mandando sus datos propios (módulos que le interesan)" '["invitados"]' "$(D116_C96 "$TMP/d116.out" solicitud.detalle.modulos)"
check "Crear demo trae 3 días por defecto" "3" "$(php -r '$j = json_decode((string) file_get_contents($argv[1]), true); foreach ($j["solicitud"]["acciones_posibles"] as $a) if ($a["accion"] === "crear_demo") foreach ($a["campos"] as $c) if ($c["campo"] === "dias") echo $c["valor"];' "$TMP/d116.out")"

echo "5. Aviso al instante: llega al Panel simulado con el token «demo» bien firmado"
PD_P=$(puerto_libre); PD_S=$(puerto_libre)
mkdir -p "$TMP/panel116"
cat > "$TMP/panel116/router.php" <<'PHP'
<?php
// Panel simulado: solo demo_aviso.php. Revisa la firma (token v2 de tipo «demo», bh = sha256 del cuerpo) y guarda lo que llega.
$dir = getenv('PANEL116_DIR');
$ruta = (string) parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($ruta !== '/demo_aviso.php') { echo 'ok'; return; }
$cuerpo = (string) file_get_contents('php://input');
$b64 = fn(string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
$partes = explode('.', (string) ($_SERVER['HTTP_X_TUKAN_TOKEN'] ?? ''));
$p = count($partes) === 2 ? json_decode((string) base64_decode(strtr($partes[0], '-_', '+/')), true) : null;
$bien = is_array($p) && hash_equals($b64(hash_hmac('sha256', $partes[0], (string) getenv('TUKAN_CENTRAL_SECRET'), true)), $partes[1])
    && ($p['v'] ?? 0) === 2 && ($p['tipo'] ?? '') === 'demo' && ($p['inst'] ?? '') === getenv('TUKAN_CENTRAL_ID')
    && ($p['bh'] ?? '') === $b64(hash('sha256', $cuerpo, true)) && (int) ($p['exp'] ?? 0) >= time();
file_put_contents("$dir/avisos.log", ($bien ? 'BIEN ' : 'MAL ') . $_SERVER['REQUEST_METHOD'] . ' ' . $cuerpo . "\n", FILE_APPEND);
http_response_code($bien ? 202 : 401);
header('Content-Type: application/json');
echo json_encode(['ok' => $bien]);
PHP
PANEL116_DIR="$TMP/panel116" php -S "127.0.0.1:$PD_P" "$TMP/panel116/router.php" > /dev/null 2>&1 &
D116_PANEL=$!
TUKAN_CENTRAL_URL="http://127.0.0.1:$PD_P" TUPLANNER_DB_NAME=$DB TUPLANNER_DB_USER=tuplanner TUPLANNER_DB_PASS=$PGPASSWORD TUPLANNER_URL="http://127.0.0.1:$PD_S" \
  php -S "127.0.0.1:$PD_S" -t public tests/router.php > "$TMP/servidor_116.log" 2>&1 &
D116_SITIO=$!
for _ in $(seq 1 50); do curl -s -o /dev/null "http://127.0.0.1:$PD_P/" && curl -s -o /dev/null "http://127.0.0.1:$PD_S/?r=login" && break; sleep 0.1; done
BASE_116=$BASE; BASE="http://127.0.0.1:$PD_S"; rm -f "$JAR"
C2="aviso$RANDOM@interesada.ec"
d116_pedir "$C2" > /dev/null
contiene "Página de gracias" "$TMP/post.html" "¡Listo! Estamos preparando tu demo: toma entre 5 y 10 minutos aproximadamente."
PD2=$(sql "SELECT id FROM pedidos_demo WHERE email='$C2'")
check "El Panel recibió el aviso: POST firmado, con el id de la solicitud" "BIEN POST {\"accion\":\"demo_pedida\",\"id\":\"demo_pedida:$PD2\"}" "$(tail -1 "$TMP/panel116/avisos.log")"
d116_pedir "incompleto@interesada.ec" "ciudad=" > /dev/null
check "Un pedido rechazado no avisa al Panel" "1" "$(wc -l < "$TMP/panel116/avisos.log")"
kill $D116_PANEL 2>/dev/null; sleep 0.3
C3="caido$RANDOM@interesada.ec"
d116_pedir "$C3" > /dev/null
contiene "Panel caído: la página de gracias sale igual" "$TMP/post.html" "¡Listo! Estamos preparando tu demo: toma entre 5 y 10 minutos aproximadamente."
no_contiene "… sin error para el interesado" "$TMP/post.html" "aviso-error"
check "… y el pedido quedó guardado" "nueva" "$(sql "SELECT estado FROM pedidos_demo WHERE email='$C3'")"
BASE=$BASE_116; kill $D116_SITIO 2>/dev/null
if grep -qiE "fatal|warning|deprecated|notice" "$TMP/servidor_116.log"; then FALLAS=$((FALLAS+1)); echo "  FALLA avisos de PHP en el sitio conectado al Panel simulado"; grep -iE "fatal|warning|deprecated|notice" "$TMP/servidor_116.log" | head -5
else OK=$((OK+1)); echo "  OK    Sin avisos de PHP en el sitio conectado al Panel simulado"; fi

echo "6. Manual y primeros pasos: públicos, sin sesión, como PDF"
rm -f "$JAR"
r=$(curl -s -D "$TMP/cab116.txt" -o "$TMP/manual116.pdf" -w "%{http_code}" "$BASE/manual.pdf")
check "/manual.pdf responde 200 sin sesión" "200" "$r"
contiene "… como application/pdf" "$TMP/cab116.txt" "Content-Type: application/pdf"
contiene "… y se descarga con nombre claro" "$TMP/cab116.txt" 'filename="Manual_TuPlanner.pdf"'
check "… es el PDF de docs/" "$(md5sum < docs/Manual_TuPlanner.pdf)" "$(md5sum < "$TMP/manual116.pdf")"
r=$(curl -s -D "$TMP/cab116.txt" -o "$TMP/pasos116.pdf" -w "%{http_code}" "$BASE/primeros_pasos.pdf")
check "/primeros_pasos.pdf responde 200 sin sesión" "200" "$r"
contiene "… como application/pdf" "$TMP/cab116.txt" "Content-Type: application/pdf"
contiene "… con nombre claro" "$TMP/cab116.txt" 'filename="TuPlanner_Primeros_Pasos.pdf"'
check "… empieza como un PDF" "%PDF" "$(head -c 4 "$TMP/pasos116.pdf")"

echo "7. Tarjeta de la demo en Inicio (solo en demo) y botones en Ayuda (para todos)"
registrar quinta "Quinta Tarjeta Demo $RANDOM"
check "La demo nueva dura 3 días" "$(date -d '+3 days' +%F)" "$(sql "SELECT prueba_hasta FROM negocios WHERE id=$NID")"
get "r=inicio" > /dev/null
contiene "Inicio de la demo: tarjeta con la fecha" "$TMP/pag.html" "<strong>Tu demo dura hasta el $(date -d '+3 days' +%d/%m).</strong> Descarga el manual y la guía de primeros pasos."
contiene "… botón del manual" "$TMP/pag.html" 'href="/manual.pdf" download="Manual_TuPlanner.pdf">Descargar el manual</a>'
contiene "… botón de primeros pasos" "$TMP/pag.html" 'href="/primeros_pasos.pdf" download="TuPlanner_Primeros_Pasos.pdf">Descargar primeros pasos</a>'
get "r=ayuda" > /dev/null
contiene "Ayuda (demo): los dos botones" "$TMP/pag.html" 'id="ayuda-descargas"'
pagar_plan quinta
get "r=inicio" > /dev/null
no_contiene "Cliente que ya paga: sin la tarjeta de la demo" "$TMP/pag.html" 'id="tarjeta-demo"'
get "r=ayuda" > /dev/null
contiene "Ayuda (cliente): botón del manual" "$TMP/pag.html" 'href="/manual.pdf" download="Manual_TuPlanner.pdf">Descargar el manual</a>'
contiene "Ayuda (cliente): botón de primeros pasos" "$TMP/pag.html" 'href="/primeros_pasos.pdf" download="TuPlanner_Primeros_Pasos.pdf">Descargar primeros pasos</a>'
echo "8. «3 días en todo»: la demo que Tukán crea a mano en el panel del programa también dura 3 días (y se puede alargar)"
ADM116="super116$RANDOM@tukan.ec"
TUPLANNER_DB_NAME=$DB TUPLANNER_DB_USER=tuplanner TUPLANNER_DB_PASS=$PGPASSWORD TUPLANNER_ADMIN_NOMBRE="Súper Demos" TUPLANNER_ADMIN_EMAIL="$ADM116" TUPLANNER_ADMIN_CLAVE="ClaveDelPanel123" php bin/crear_admin.php > /dev/null 2>&1
rm -f "$JAR"
post "r=admin_entrar" --data-urlencode "email=$ADM116" --data-urlencode "clave=ClaveDelPanel123" > /dev/null
check "Nueva demo abre" "200" "$(get "r=admin_nuevo")"
contiene "Dice que la demo dura 3 días" "$TMP/pag.html" "La demo dura <strong>3 días</strong>"
CM="amano$RANDOM@interesada.ec"
r=$(post_desde "r=admin_nuevo" "r=admin_nuevo" --data-urlencode "negocio=Quinta A Mano $RANDOM" --data-urlencode "nombre=Mario Mano" --data-urlencode "telefono=0991112233" \
     --data-urlencode "email=$CM" --data-urlencode "ciudad=Quito" --data-urlencode "plan=quinta")
NM116=$(sql "SELECT negocio_id FROM usuarios WHERE email='$CM'")
check "Demo creada a mano: dura 3 días" "prueba|$(date -d '+3 days' +%F)" "$(sql "SELECT estado||'|'||prueba_hasta FROM negocios WHERE id=${NM116:-0}")"
get "r=admin_planes" > /dev/null
contiene "Planes y precios: «Días de demo» en 3" "$TMP/pag.html" 'name="dias_prueba" value="3"'
post_desde "r=admin_negocio&id=$NM116" "r=admin_negocio&id=$NM116&accion=prueba" --data-urlencode "dias=7" > /dev/null
check "Alargar sigue disponible (3 + 7 días)" "$(date -d '+10 days' +%F)" "$(sql "SELECT prueba_hasta FROM negocios WHERE id=${NM116:-0}")"
rm -f "$JAR"
# Los pedidos de esta prueba no cuentan para el límite por IP de las pruebas que siguen
sql "UPDATE pedidos_demo SET ip = NULL WHERE email LIKE '%@interesada.ec'" > /dev/null
