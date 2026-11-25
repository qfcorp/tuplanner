#!/usr/bin/env bash
# Pantallas: el super usuario entra por el LOGIN NORMAL (/?r=login, el que abre la app de iPhone) con su clave y código de 2 pasos
# del Panel Tukán. El Panel Central es de mentira (tests/panel_super_simulado.php): clave «ClaveBuena1», código 123456.
# Lo corre tests/run_web.sh (variables: BASE, DB, PGPASSWORD, TMP, TUKAN_CENTRAL_ID/_SECRET de run_web.sh).
S95_PIDS=""
s95_puerto() { php -r '$s = stream_socket_server("tcp://127.0.0.1:0"); $n = stream_socket_get_name($s, false); echo substr($n, strrpos($n, ":") + 1);'; }
S95_SIM="$TMP/panel_super_sim"; mkdir -p "$S95_SIM"; rm -f "$S95_SIM"/*
S95_PP=$(s95_puerto)
env PANEL_SIM_DIR="$S95_SIM" TUKAN_CENTRAL_ID="$TUKAN_CENTRAL_ID" TUKAN_CENTRAL_SECRET="$TUKAN_CENTRAL_SECRET" php -S "127.0.0.1:$S95_PP" tests/panel_super_simulado.php > "$TMP/panel_super.log" 2>&1 &
S95_PIDS="$!"
S95_AP=$(s95_puerto)
env TUPLANNER_DB_NAME=$DB TUPLANNER_DB_USER=tuplanner TUPLANNER_DB_PASS=$PGPASSWORD TUPLANNER_URL="http://127.0.0.1:$S95_AP" \
    TUKAN_CENTRAL_ID="$TUKAN_CENTRAL_ID" TUKAN_CENTRAL_SECRET="$TUKAN_CENTRAL_SECRET" TUKAN_CENTRAL_URL="http://127.0.0.1:$S95_PP" TUKAN_SOLO_CENTRAL=0 \
    php -S "127.0.0.1:$S95_AP" -t public tests/router.php > "$TMP/app_super.log" 2>&1 &
S95_PIDS="$S95_PIDS $!"
trap 'kill $SERVIDOR ${C95_PIDS:-} $S95_PIDS 2>/dev/null' EXIT
for _ in $(seq 1 50); do curl -s -o /dev/null "http://127.0.0.1:$S95_AP/?r=login" && curl -s -o /dev/null "http://127.0.0.1:$S95_PP/" && break; sleep 0.1; done
BASE_ORIG=$BASE
s95_login() { # email clave [codigo] -> "http redirect"; respuesta en $TMP/post.html (usa el sitio con panel de mentira)
  BASE="http://127.0.0.1:$S95_AP"
  local r; r=$(post "r=login" --data-urlencode "email=$1" --data-urlencode "clave=$2" --data-urlencode "codigo=${3:-}")
  BASE=$BASE_ORIG
  echo "$r"
}
s95_llamadas() { [ -f "$S95_SIM/super.log" ] && wc -l < "$S95_SIM/super.log" | tr -d ' ' || echo 0; }

echo "1. Un cliente normal que entra bien NO toca el panel"
registrar quinta "Quinta Super Login $RANDOM"; EC=$EMAIL
sql "UPDATE usuarios SET password_hash='$(php -r 'echo password_hash("claveSegura1", PASSWORD_DEFAULT);')' WHERE email='$EC'" > /dev/null
rm -f "$JAR"
r=$(s95_login "$EC" claveSegura1)
check "Cliente con su clave: entra a inicio" "302 http://127.0.0.1:$S95_AP/?r=inicio" "$r"
check "El panel central no recibió ningún pedido" "0" "$(s95_llamadas)"

echo "2. Súper usuario: clave bien, falta el código"
rm -f "$JAR"
r=$(s95_login dimitry-super@prueba.ec ClaveBuena1)
check "Vuelve al login (200)" "200 " "$r"
contiene "Pide el código de 2 pasos" "$TMP/post.html" 'name="codigo"'
contiene "Con el aviso del panel" "$TMP/post.html" 'Escribe también el código de 2 pasos'
contiene "El campo del código es de un solo uso para el celular" "$TMP/post.html" 'autocomplete="one-time-code"'
check "Todavía no hay súper usuario en el programa" "0" "$(sql "SELECT count(*) FROM superadmins WHERE email='dimitry-super@prueba.ec'")"
for i in 1 2 3 4 5 6; do s95_login dimitry-super@prueba.ec ClaveBuena1 > /dev/null; done
r=$(s95_login dimitry-super@prueba.ec ClaveBuena1 123456)
check "Pedir el código muchas veces NO cuenta como intento fallido: entra al panel" "302 http://127.0.0.1:$S95_AP/?r=admin" "$r"

echo "3. Con clave y código entra al panel de TuPlanner"
check "Se creó el súper usuario con el correo y nombre del panel" "Dimitry Panel|true" "$(sql "SELECT nombre||'|'||creado_por_central FROM superadmins WHERE email='dimitry-super@prueba.ec'")"
BASE="http://127.0.0.1:$S95_AP"; c=$(get "r=admin"); BASE=$BASE_ORIG
check "El panel de administración abre (200)" "200" "$c"
contiene "Es el Panel Tukán" "$TMP/pag.html" 'Panel Tukán'
check "Quedó anotado en el registro" "1" "$(sql "SELECT count(*) FROM registro_admin WHERE accion LIKE 'Entró escribiendo su clave y código de 2 pasos%'")"
check "El panel recibió la IP de quien escribe y el código" "123456" "$(tail -1 "$S95_SIM/super.log" | php -r 'echo json_decode(stream_get_contents(STDIN), true)["codigo"];')"
r=$(s95_login dimitry-super@prueba.ec ClaveBuena1 123456)
check "Entrar otra vez reutiliza el mismo súper usuario" "1" "$(sql "SELECT count(*) FROM superadmins WHERE email='dimitry-super@prueba.ec'")"

echo "4. Fallas: mensajes sin dar pistas"
rm -f "$JAR"
r=$(s95_login dimitry-super@prueba.ec mala)
check "Clave mala: 200" "200 " "$r"
contiene "Mensaje genérico de siempre" "$TMP/post.html" 'El correo o la contraseña no coinciden.'
no_contiene "No pide código" "$TMP/post.html" 'name="codigo"'
rm -f "$JAR"; r=$(s95_login dimitry-super@prueba.ec ClaveBuena1 000000)
contiene "Código malo: vuelve a pedir el código con el texto del panel" "$TMP/post.html" 'El código de 2 pasos no es correcto.'
contiene "…y el campo del código sigue ahí" "$TMP/post.html" 'name="codigo"'
rm -f "$JAR"; r=$(s95_login bloqueado@prueba.ec ClaveBuena1 123456)
contiene "Bloqueado: muestra el texto del panel" "$TMP/post.html" 'espera 15 minutos'
rm -f "$JAR"; r=$(s95_login sin2@prueba.ec ClaveBuena1 123456)
contiene "Sin 2 pasos: dice que lo active en el panel" "$TMP/post.html" 'Primero activa la verificación en 2 pasos'
check "Ninguna de esas entró: sin sesión de súper usuario" "0" "$(sql "SELECT count(*) FROM superadmins WHERE email IN ('bloqueado@prueba.ec','sin2@prueba.ec')")"
rm -f "$JAR"; r=$(s95_login "$EC" claveMala)
contiene "Cliente con clave mala: mensaje de siempre" "$TMP/post.html" 'El correo o la contraseña no coinciden.'

echo "5. Panel central caído"
touch "$S95_SIM/caido"
rm -f "$JAR"; r=$(s95_login dimitry-super@prueba.ec ClaveBuena1 123456)
check "Panel en mantenimiento: no entra (200)" "200 " "$r"
contiene "Mensaje de siempre" "$TMP/post.html" 'El correo o la contraseña no coinciden.'
rm -f "$S95_SIM/caido"
# El cliente normal sigue entrando aunque el panel esté caído
touch "$S95_SIM/caido"; rm -f "$JAR"; r=$(s95_login "$EC" claveSegura1); rm -f "$S95_SIM/caido"
check "Con el panel caído el cliente normal entra igual" "302 http://127.0.0.1:$S95_AP/?r=inicio" "$r"

echo "6. El freno local de intentos sigue y no llama al panel"
rm -f "$JAR"
for i in 1 2 3 4 5; do s95_login otro@prueba.ec mala > /dev/null; done
N0=$(s95_llamadas)
r=$(s95_login dimitry-super@prueba.ec ClaveBuena1 123456)
contiene "Después de 5 fallos: espera unos minutos" "$TMP/post.html" 'Demasiados intentos'
check "Con el freno puesto no se llama al panel" "$N0" "$(s95_llamadas)"
kill $S95_PIDS 2>/dev/null
