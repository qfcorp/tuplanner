#!/usr/bin/env bash
# Funciones comunes de las pruebas de pantallas (se cargan con: source tests/web_base.sh)
# Variables: BASE (sitio de prueba), DB (base de prueba), PGPASSWORD
BASE=${BASE:-http://127.0.0.1:8199}
DB=${DB:-tuplanner_web_prueba}
export PGPASSWORD=${PGPASSWORD:-prueba}
export TZ=America/Guayaquil
export PGTZ=America/Guayaquil   # CURRENT_DATE de psql en la hora de Ecuador (igual que el programa)
TMP=${TMP:-$(mktemp -d)}
JAR="$TMP/cookies_$$"
OK=${OK:-0}; FALLAS=${FALLAS:-0}

sql() { psql -h 127.0.0.1 -U tuplanner -d "$DB" -tAc "$1"; }
check() { # nombre esperado real
  if [ "$2" == "$3" ]; then OK=$((OK+1)); echo "  OK    $1";
  else FALLAS=$((FALLAS+1)); echo "  FALLA $1 — esperado [$2], salió [$3]"; fi
}
contiene() { # nombre archivo texto
  if grep -q -- "$3" "$2"; then OK=$((OK+1)); echo "  OK    $1";
  else FALLAS=$((FALLAS+1)); echo "  FALLA $1 — no aparece [$3]"; fi
}
no_contiene() { # nombre archivo texto
  if grep -q -- "$3" "$2"; then FALLAS=$((FALLAS+1)); echo "  FALLA $1 — aparece [$3]";
  else OK=$((OK+1)); echo "  OK    $1"; fi
}
# GET: get "r=inicio&id=3"  -> imprime el código HTTP; la página queda en $TMP/pag.html
get() { curl -s -b "$JAR" -c "$JAR" -o "$TMP/pag.html" -w "%{http_code}" "$BASE/?$1"; }
csrf() { grep -o 'name="csrf" value="[^"]*"' "$TMP/pag.html" | head -1 | sed 's/.*value="//;s/"$//'; }
# POST: post "r=evento_nuevo" (página donde está el formulario) datos...  -> imprime "codigo destino"; respuesta en $TMP/post.html
post() {
  local ruta=$1; shift
  get "$ruta" > /dev/null
  local t; t=$(csrf)
  if [ -z "$t" ]; then get "r=login" > /dev/null; t=$(csrf); fi   # el token es de la sesión
  curl -s -b "$JAR" -c "$JAR" -o "$TMP/post.html" -w "%{http_code} %{redirect_url}" \
       --data-urlencode "csrf=$t" "$@" "$BASE/?$ruta"
}
# POST a una ruta tomando el token de otra página: post_desde "r=evento&id=1" "r=evento&id=1&accion=x" datos...
post_desde() {
  local pagina=$1 ruta=$2; shift 2
  get "$pagina" > /dev/null
  local t; t=$(csrf)
  curl -s -b "$JAR" -c "$JAR" -o "$TMP/post.html" -w "%{http_code} %{redirect_url}" \
       --data-urlencode "csrf=$t" "$@" "$BASE/?$ruta"
}
# Crea una cuenta nueva EN DEMO (plan quinta por defecto), como la crea Tukán, y deja la sesión iniciada. Deja NID (negocio) y EMAIL.
# (Ya no hay registro abierto con periodo de prueba: /?r=registro es el pedido de demo.)
registrar() { # [plan] [nombre del negocio]
  local plan=${1:-quinta} nombre=${2:-Quinta de Prueba $RANDOM}
  rm -f "$JAR"
  EMAIL="dueno$RANDOM$RANDOM@prueba.ec"
  NID=$(TUPLANNER_DB_NAME=$DB TUPLANNER_DB_USER=tuplanner TUPLANNER_DB_PASS=$PGPASSWORD php tests/crear_cuenta.php "$plan" "$nombre" "$EMAIL")
  entrar "$EMAIL" claveSegura1
}
# Pasa la cuenta NID de demo a pagada con ese plan (para probar los módulos de un plan): pagar_plan salon
pagar_plan() {
  sql "UPDATE negocios SET estado='activo', plan_codigo='${1:-quinta}', activo_hasta=CURRENT_DATE + 30 WHERE id=$NID" > /dev/null
}
# Planes de ANTES de la migración 112 (para las pruebas escritas con ese modelo): Esencial $19 sin módulos a elegir,
# Planner (salon) $39 (lugares sin límite, 3 usuarios, con sus módulos) y Agencia (quinta) $69. Los de $20 y $45: web_112_planes.sh.
planes_de_antes() {
  sql "UPDATE planes SET nombre='Esencial', precio_mensual=19, modulos='', modulos_elegir=0, precio_modulo_extra=NULL, max_espacios=NULL, max_usuarios=2 WHERE codigo='esencial';
       INSERT INTO planes (codigo, nombre, precio_mensual, max_espacios, max_usuarios, descripcion, modulos)
       VALUES ('salon', 'Planner', 39.00, NULL, 3, 'Para el organizador independiente (plan de antes de la migración 112).',
               'planificacion,presupuesto,comisiones,ideas,paquetes,contratos,portal_cliente,cobros_linea,cuestionarios,proveedores,incidencias,metas,telegram,exportar')
       ON CONFLICT (codigo) DO NOTHING;
       UPDATE planes SET nombre='Agencia', precio_mensual=69 WHERE codigo='quinta';" > /dev/null
}
# Los planes de $20 y $45 como los deja la migración 112 (Básico y Completo; sin el plan del medio)
planes_20_45() {
  sql "UPDATE negocios SET plan_codigo='quinta' WHERE plan_codigo='salon'; UPDATE suscripciones SET plan_codigo='quinta' WHERE plan_codigo='salon';
       DELETE FROM planes WHERE codigo='salon';
       UPDATE planes SET nombre='Básico', precio_mensual=20, modulos='', modulos_elegir=3, precio_modulo_extra=5, max_espacios=NULL, max_usuarios=2,
                         descripcion='La base de TuPlanner + 3 módulos que tú eliges.' WHERE codigo='esencial';
       UPDATE planes SET nombre='Completo', precio_mensual=45, modulos='*,asistente_ia', modulos_elegir=0, precio_modulo_extra=NULL, max_espacios=NULL, max_usuarios=NULL
        WHERE codigo='quinta';" > /dev/null
}
entrar() { # email clave
  rm -f "$JAR"
  post "r=login" --data-urlencode "email=$1" --data-urlencode "clave=$2" > /dev/null
}
# Crea un evento por SQL rápido para probar pantallas: crear_evento_sql <fecha> [estado] -> imprime el id
crear_evento_sql() {
  local fecha=$1 estado=${2:-consulta} espacio=${3:-}
  local cli; cli=$(sql "INSERT INTO clientes (negocio_id, nombre, telefono) VALUES ($NID, 'Cliente Web $RANDOM', '0991234567') RETURNING id" | head -1)
  local n; n=$(sql "SELECT count(*)+1 FROM eventos WHERE negocio_id=$NID")
  local ev; ev=$(sql "INSERT INTO eventos (negocio_id, codigo, cliente_id, tipo, nombre, fecha, invitados, estado) VALUES ($NID, 'EV-T-'||lpad('$n',4,'0'), $cli, 'boda', 'Boda de prueba $n', '$fecha', 120, '$estado') RETURNING id" | head -1)
  local esp=$espacio
  [ -z "$esp" ] && esp=$(sql "SELECT id FROM espacios WHERE negocio_id=$NID ORDER BY orden LIMIT 1")
  local bloquea=false; case "$estado" in separado|confirmado|realizado) bloquea=true;; esac
  sql "INSERT INTO evento_espacios (negocio_id, evento_id, espacio_id, periodo, bloquea) VALUES ($NID, $ev, $esp, tsrange('$fecha 15:00','$fecha 23:59'), $bloquea)" > /dev/null
  echo "$ev"
}
resumen_web() {
  echo "----------------------------------------"
  echo "Correctas: $OK  ·  Fallas: $FALLAS"
}
