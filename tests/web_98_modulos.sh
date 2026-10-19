# Programa modular (Anexo B) en pantalla: Mi plan, pedir un módulo, página de módulo apagado, menús y pestañas,
# portales públicos "no disponible", PDF, y en el Panel Tukán: interruptores por negocio, aprobar el pedido, catálogo y pedidos de demo.
DBENV="TUPLANNER_DB_NAME=$DB TUPLANNER_DB_USER=tuplanner TUPLANNER_DB_PASS=$PGPASSWORD"

echo "1. Demo: todo encendido; Mi plan lo explica"
registrar quinta "Quinta Modular $RANDOM"; NM=$NID; EM=$EMAIL
check "Mi plan abre" "200" "$(get "r=planes")"
contiene "Dice que está en demo con todo" "$TMP/pag.html" "tienes todos los módulos encendidos"
contiene "Lista los módulos" "$TMP/pag.html" "Invitados con QR"
contiene "La base siempre incluida" "$TMP/pag.html" "Siempre incluido"
no_contiene "Mi plan no ofrece nada gratis" "$TMP/pag.html" "gratis"
check "Día del evento en demo: abre" "200" "$(get "r=mobiliario")"

echo "2. Plan Salón pagado: lo de Quinta apagado (menús, páginas, portales, PDF)"
pagar_plan salon
get "r=planes" > /dev/null
contiene "Precio del plan" "$TMP/pag.html" "\$39,00"
contiene "Mobiliario: no está en tu plan" "$TMP/pag.html" "No está en tu plan"
contiene "Botón para pedirlo" "$TMP/pag.html" "Pedir este módulo"
contiene "Precio del módulo" "$TMP/pag.html" "+\$5,00 al mes"
get "r=mas" > /dev/null
no_contiene "Más: sin Mobiliario" "$TMP/pag.html" "r=mobiliario"
no_contiene "Más: sin Planos" "$TMP/pag.html" "r=plano\""
contiene "Más: con Proveedores (está en Salón)" "$TMP/pag.html" "r=proveedores"
check "Mobiliario apagado: 403" "403" "$(get "r=mobiliario")"
contiene "Página amable" "$TMP/pag.html" "Este módulo no está en tu plan"
contiene "… con el precio" "$TMP/pag.html" "\$5,00"
contiene "… y el enlace a Mi plan" "$TMP/pag.html" "r=planes#mod-mobiliario"
contiene "… dice que los datos se guardan" "$TMP/pag.html" "tus datos siguen guardados"
r=$(post_desde "r=inicio" "r=mobiliario" --data-urlencode "accion=guardar" --data-urlencode "nombre=Sillas")
check "Tampoco se puede guardar por POST" "403" "${r%% *}"
EVM=$(crear_evento_sql "$(date -d '+20 days' +%F)" separado)
TOKM=$(sql "SELECT token_cliente FROM eventos WHERE id=$EVM")
check "Portal del cliente (está en Salón): abre" "200" "$(get "r=portal&t=$TOKM")"
no_contiene "… sin invitados (módulo apagado)" "$TMP/pag.html" "Tus invitados"
check "Portal de invitados público: no disponible" "403" "$(get "r=portal_invitados&t=$TOKM")"
contiene "… No disponible" "$TMP/pag.html" "No disponible"
TVM=$(sql "SELECT token_vivo FROM eventos WHERE id=$EVM")
check "Cronograma en vivo público: no disponible" "403" "$(get "r=vivo&t=$TVM")"
check "PDF de invitados: no sale" "0" "$(curl -s -b "$JAR" "$BASE/?r=pdf&tipo=invitados&id=$EVM" | head -c 4 | grep -c '%PDF')"
check "PDF que no existe: 404" "404" "$(get "r=pdf&tipo=nada&id=$EVM")"

echo "3. Pedir un módulo desde Mi plan → solicitud"
r=$(post_desde "r=planes" "r=planes" --data-urlencode "accion=pedir" --data-urlencode "modulo=mobiliario")
check "Pedir: vuelve a Mi plan" "302" "${r%% *}"
check "Pedido guardado pendiente" "pendiente|mobiliario" "$(sql "SELECT estado||'|'||encender FROM modulos_solicitudes WHERE negocio_id=$NM")"
get "r=planes" > /dev/null
contiene "Mi plan: Pedido enviado" "$TMP/pag.html" "Pedido enviado"
contiene "Mi plan: tus pedidos" "$TMP/pag.html" "Tus pedidos de módulos"
post_desde "r=planes" "r=planes" --data-urlencode "accion=pedir" --data-urlencode "modulo=mobiliario" > /dev/null
check "Pedir otra vez: no se repite" "1" "$(sql "SELECT count(*) FROM modulos_solicitudes WHERE negocio_id=$NM")"
get "r=mobiliario" > /dev/null
contiene "La página del módulo dice que ya lo pidió" "$TMP/pag.html" "Ya pediste este módulo"
post_desde "r=planes" "r=planes" --data-urlencode "accion=pedir" --data-urlencode "modulo=contratos" > /dev/null
check "Pedir uno que ya tiene: no crea pedido" "1" "$(sql "SELECT count(*) FROM modulos_solicitudes WHERE negocio_id=$NM")"
# Un vendedor no ve Mi plan
sql "INSERT INTO usuarios (negocio_id, nombre, email, password_hash, rol) VALUES ($NM, 'Vende', 'vende$NM@prueba.ec', '$(php -r 'echo password_hash("claveSegura1", PASSWORD_DEFAULT);')', 'vendedor')" > /dev/null
entrar "vende$NM@prueba.ec" claveSegura1
check "Vendedor: Mi plan no (solo el dueño)" "403" "$(get "r=planes")"
get "r=mobiliario" > /dev/null
contiene "Vendedor: le pide al dueño" "$TMP/pag.html" "Pídele al dueño"

echo "4. Panel Tukán: módulos del negocio, aprobar el pedido, catálogo"
ADM="admmod$RANDOM@tukan.ec"
env $DBENV TUPLANNER_ADMIN_NOMBRE="Dimitry Tukán" TUPLANNER_ADMIN_EMAIL="$ADM" TUPLANNER_ADMIN_CLAVE="ClaveDelPanel123" php bin/crear_admin.php > /dev/null 2>&1
rm -f "$JAR"
post "r=admin_entrar" --data-urlencode "email=$ADM" --data-urlencode "clave=ClaveDelPanel123" > /dev/null
check "Ficha del negocio" "200" "$(get "r=admin_negocio&id=$NM")"
contiene "Sección Módulos" "$TMP/pag.html" 'id="modulos"'
contiene "Interruptores" "$TMP/pag.html" 'class="interruptor"'
contiene "Total al mes" "$TMP/pag.html" "Paga <strong>\$39,00</strong> al mes"
contiene "Pedido desde Mi plan" "$TMP/pag.html" "Pidió desde Mi plan"
SID=$(sql "SELECT id FROM modulos_solicitudes WHERE negocio_id=$NM")
r=$(post_desde "r=admin_negocio&id=$NM" "r=admin_negocio&id=$NM&accion=solicitud_modulos" --data-urlencode "solicitud=$SID" --data-urlencode "resultado=aprobada")
check "Aprobar el pedido" "aprobada|mobiliario" "$(sql "SELECT s.estado||'|'||n.modulos_extra FROM modulos_solicitudes s JOIN negocios n ON n.id=s.negocio_id WHERE s.id=$SID")"
get "r=admin_negocio&id=$NM" > /dev/null
contiene "Ahora paga plan + mobiliario" "$TMP/pag.html" "Paga <strong>\$44,00</strong> al mes"
# Guardar interruptores: todo lo de Salón + mobiliario + invitados, sin metas, con precio acordado
ARGS=()
for m in planificacion presupuesto comisiones ideas paquetes contratos portal_cliente cobros_linea cuestionarios proveedores incidencias telegram exportar mobiliario invitados; do ARGS+=(--data-urlencode "modulo[$m]=1"); done
r=$(post_desde "r=admin_negocio&id=$NM" "r=admin_negocio&id=$NM&accion=modulos" "${ARGS[@]}" --data-urlencode "precio_acordado=")
check "Guardar módulos: extra y quitados" "invitados,mobiliario|metas" "$(sql "SELECT modulos_extra||'|'||modulos_quitados FROM negocios WHERE id=$NM")"
check "Queda en el registro del panel" "1" "$(sql "SELECT count(*) FROM registro_admin WHERE negocio_id=$NM AND accion LIKE 'Módulos: Encendidos: Invitados%'")"
r=$(post_desde "r=admin_negocio&id=$NM" "r=admin_negocio&id=$NM&accion=modulos" "${ARGS[@]}" --data-urlencode "modulo[boda_vivo]=1" --data-urlencode "precio_acordado=50")
check "Encender boda en vivo enciende el día del evento; precio acordado" "dia_evento,invitados,mobiliario,boda_vivo|50.00" \
      "$(sql "SELECT modulos_extra||'|'||precio_acordado FROM negocios WHERE id=$NM")"
r=$(post_desde "r=admin_negocio&id=$NM" "r=admin_negocio&id=$NM&accion=modulos" "${ARGS[@]}" --data-urlencode "modulo[boda_vivo]=1" --data-urlencode "precio_acordado=")
get "r=admin_negocio&id=$NM" > /dev/null
contiene "No se apaga el día del evento si boda en vivo lo necesita" "$TMP/pag.html" "lo necesita Boda en vivo"
check "Catálogo y planes" "200" "$(get "r=admin_planes")"
contiene "Catálogo de módulos" "$TMP/pag.html" "Catálogo de módulos"
contiene "Qué trae cada plan" "$TMP/pag.html" "Qué módulos trae cada plan"
CARGS=()
while IFS='|' read -r mid precio orden; do CARGS+=(--data-urlencode "precio[$mid]=$precio" --data-urlencode "orden[$mid]=$orden" --data-urlencode "descripcion[$mid]="); done \
  < <(sql "SELECT id||'|'||precio_mensual||'|'||orden FROM modulos")
CARGS+=(--data-urlencode "plan_todos[quinta]=1")
for m in planificacion presupuesto comisiones ideas paquetes contratos portal_cliente cobros_linea cuestionarios proveedores incidencias metas telegram exportar; do CARGS+=(--data-urlencode "plan_mod[salon][$m]=1"); done
r=$(post_desde "r=admin_planes" "r=admin_planes" --data-urlencode "accion=catalogo" "${CARGS[@]/precio\[plano\]=6.00/precio[plano]=7.50}")
check "Guardar catálogo: precio nuevo del plano" "7.50" "$(sql "SELECT precio_mensual FROM modulos WHERE id='plano'")"
check "… Planner sigue con sus 14 módulos y Agencia con todos" "14|*" "$(sql "SELECT (SELECT array_length(string_to_array(modulos, ','), 1) FROM planes WHERE codigo='salon')||'|'||(SELECT modulos FROM planes WHERE codigo='quinta')")"
sql "UPDATE modulos SET precio_mensual = 6 WHERE id='plano'" > /dev/null

echo "5. Panel Tukán: pedidos de demo → crear demo"
rm -f "$JAR"
post "r=registro" --data-urlencode "negocio=Quinta Pedido Panel $RANDOM" --data-urlencode "nombre=Rita Panel" --data-urlencode "whatsapp=0991230000" \
     --data-urlencode "email=rita$RANDOM@panel.ec" --data-urlencode "modulos[]=plano" > /dev/null
PDP=$(sql "SELECT id FROM pedidos_demo ORDER BY id DESC LIMIT 1")
post "r=admin_entrar" --data-urlencode "email=$ADM" --data-urlencode "clave=ClaveDelPanel123" > /dev/null
get "r=admin" > /dev/null
contiene "Resumen: pedidos de demo" "$TMP/pag.html" "Pedidos de demo"
contiene "… con el botón Crear demo" "$TMP/pag.html" "r=admin_nuevo&amp;pedido=$PDP"
get "r=admin_nuevo&pedido=$PDP" > /dev/null
contiene "Nueva demo con los datos del pedido" "$TMP/pag.html" "Rita Panel"
r=$(post_desde "r=admin_nuevo&pedido=$PDP" "r=admin_nuevo" --data-urlencode "pedido=$PDP" --data-urlencode "negocio=Quinta Pedido Panel" --data-urlencode "nombre=Rita Panel" \
     --data-urlencode "telefono=0991230000" --data-urlencode "email=rita.demo$RANDOM@panel.ec" --data-urlencode "ciudad=Quito" --data-urlencode "plan=salon")
check "Demo creada: el pedido queda unido" "demo_creada|demo_pedida|prueba" \
      "$(sql "SELECT p.estado||'|'||n.origen||'|'||n.estado FROM pedidos_demo p JOIN negocios n ON n.id=p.negocio_id WHERE p.id=$PDP")"

echo "6. Lo propio del organizador: planificación, presupuesto, comisiones e ideas"
registrar salon "Planner Esencial $RANDOM"; NPE=$NID
pagar_plan esencial
EVP=$(crear_evento_sql "$(date -d '+60 days' +%F)" separado)
TOKP=$(sql "SELECT token_cliente FROM eventos WHERE id=$EVP")
check "Esencial: Planificación apagada (403)" "403" "$(get "r=evento_plan&id=$EVP")"
contiene "… con la cabecera del evento" "$TMP/pag.html" "evento-cabeza"
check "Esencial: Comisiones apagada (403)" "403" "$(get "r=comisiones")"
check "Esencial: Ideas apagada (403)" "403" "$(get "r=evento_ideas&id=$EVP")"
check "Esencial: Tu planificación del cliente: no disponible" "403" "$(get "r=portal_plan&t=$TOKP")"
check "Esencial: Ideas del cliente: no disponible" "403" "$(get "r=portal_ideas&t=$TOKP")"
get "r=evento&id=$EVP" > /dev/null
no_contiene "Esencial: sin pestaña Planificación" "$TMP/pag.html" "r=evento_plan&amp;id=$EVP"
sql "UPDATE negocios SET modulos_extra='planificacion' WHERE id=$NPE" > /dev/null
check "Solo planificación: la pestaña abre" "200" "$(get "r=evento_plan&id=$EVP")"
contiene "… con la lista de tareas" "$TMP/pag.html" "Lista de tareas"
no_contiene "… sin el presupuesto por proveedor" "$TMP/pag.html" "Presupuesto por proveedor"
r=$(post_desde "r=evento_plan&id=$EVP" "r=evento_plan&id=$EVP" --data-urlencode "accion=partida" --data-urlencode "categoria=flores" --data-urlencode "descripcion=Flores")
check "… y no guarda partidas por POST" "0" "$(sql "SELECT count(*) FROM presupuesto_partidas WHERE evento_id=$EVP")"
check "Portal: Tu planificación abre" "200" "$(get "r=portal_plan&t=$TOKP")"
no_contiene "… sin Tu presupuesto" "$TMP/pag.html" "Tu presupuesto"
sql "UPDATE negocios SET modulos_extra='planificacion,presupuesto' WHERE id=$NPE" > /dev/null
get "r=evento_plan&id=$EVP" > /dev/null
contiene "Con presupuesto: sale el presupuesto por proveedor" "$TMP/pag.html" "Presupuesto por proveedor"
check "Con presupuesto pero sin comisiones: Comisiones 403" "403" "$(get "r=comisiones")"
