# Organizador (migración 100): pestaña Planificación (tareas, presupuesto, pagos directos, comisión, honorarios),
# pestaña Ideas (fotos, me encanta, temática y colores), Comisiones, Configuración, evento sin lugar,
# portal del cliente (tareas, presupuesto sin costos de reventa, pagos, ideas) y permisos por rol y por negocio.
planes_de_antes   # estas pruebas usan los planes de antes (Esencial $19, Planner $39, Agencia $69); los de $20 y $45: web_112_planes.sh
FOTO100="$TMP/idea_100.jpg"
php -r '$i=imagecreatetruecolor(400,400);imagefill($i,0,0,imagecolorallocate($i,240,200,210));imagestring($i,5,120,190,"Idea",0);imagejpeg($i,$argv[1]);' "$FOTO100"
echo "hola" > "$TMP/no_foto.jpg"
post_portal100() { local ruta=$1; shift; post_desde "$ruta" "$ruta" "$@"; }
HOY=$(date +%F)

echo "1. Cuenta nueva del organizador y evento sin lugar"
registrar quinta "Ana Pérez Wedding Planner $RANDOM"
NIDP=$NID; EMAILP=$EMAIL
check "Inicio" "200" "$(get "r=inicio")"
check "Nuevo evento" "200" "$(get "r=evento_nuevo")"
FUT=$(date -d "+200 days" +%F)
r=$(post "r=evento_nuevo" --data-urlencode "modo_cliente=nuevo" --data-urlencode "cliente_nombre=Carla Mora" --data-urlencode "cliente_telefono=0991231234" \
     --data-urlencode "tipo=boda" --data-urlencode "fecha=$FUT" --data-urlencode "hora_inicio=17:00" --data-urlencode "hora_fin=01:00" --data-urlencode "invitados=120")
EV=$(sql "SELECT id FROM eventos WHERE negocio_id=$NIDP ORDER BY id DESC LIMIT 1")
check "Se creó sin elegir lugar" "302 $BASE/?r=evento&id=$EV" "$r"
check "Lugar por definir" "0" "$(sql "SELECT count(*) FROM evento_espacios WHERE evento_id=$EV")"
get "r=evento&id=$EV" > /dev/null
contiene "Resumen dice lugar por definir" "$TMP/pag.html" "Lugar por definir"
contiene "Pestaña Planificación" "$TMP/pag.html" "r=evento_plan&amp;id=$EV"
contiene "Pestaña Ideas" "$TMP/pag.html" "r=evento_ideas&amp;id=$EV"
check "Tareas de boda copiadas" "28" "$(sql "SELECT count(*) FROM evento_tareas WHERE evento_id=$EV")"

echo "2. Planificación: tareas"
check "Abre Planificación" "200" "$(get "r=evento_plan&id=$EV")"
contiene "Título lista de tareas" "$TMP/pag.html" "Lista de tareas"
contiene "Agrupadas por meses" "$TMP/pag.html" "meses antes"
contiene "Tareas del cliente marcadas" "$TMP/pag.html" "pn-etq-cliente"
contiene "Repartir presupuesto" "$TMP/pag.html" "Repartir con los porcentajes típicos"
r=$(post "r=evento_plan&id=$EV" --data-urlencode "accion=tarea" --data-urlencode "titulo=Prueba de peinado" --data-urlencode "vence=$(date -d '+150 days' +%F)" --data-urlencode "asignado=cliente")
check "Agregar tarea" "302 $BASE/?r=evento_plan&id=$EV#tareas" "$r"
T1=$(sql "SELECT id FROM evento_tareas WHERE evento_id=$EV AND titulo='Prueba de peinado'")
check "Tarea del cliente" "cliente" "$(sql "SELECT asignado FROM evento_tareas WHERE id=$T1")"
r=$(post "r=evento_plan&id=$EV" --data-urlencode "accion=tarea_marcar" --data-urlencode "tarea_id=$T1" --data-urlencode "hecha=1")
check "Marcar hecha" "true|planner" "$(sql "SELECT hecha::text||'|'||hecha_por FROM evento_tareas WHERE id=$T1")"
r=$(post "r=evento_plan&id=$EV" --data-urlencode "accion=tarea_editar" --data-urlencode "tarea_id=$T1" --data-urlencode "titulo=Prueba de peinado y maquillaje" \
     --data-urlencode "vence=" --data-urlencode "asignado=cliente" --data-urlencode "notas=Con Vane")
check "Editar tarea" "Prueba de peinado y maquillaje||Con Vane" "$(sql "SELECT titulo||'|'||COALESCE(vence::text,'')||'|'||notas FROM evento_tareas WHERE id=$T1")"
r=$(post "r=evento_plan&id=$EV" --data-urlencode "accion=tarea" --data-urlencode "titulo=" --data-urlencode "asignado=planner")
get "r=evento_plan&id=$EV" > /dev/null
contiene "Error claro si falta el título" "$TMP/pag.html" "Escribe la tarea"
r=$(post "r=evento_plan&id=$EV" --data-urlencode "accion=lista_tipica")
get "r=evento_plan&id=$EV" > /dev/null
contiene "Lista típica: ya estaban todas" "$TMP/pag.html" "Ya tenías todas"
r=$(post "r=evento_plan&id=$EV" --data-urlencode "accion=tarea_borrar" --data-urlencode "tarea_id=$T1")
check "Borrar tarea" "0" "$(sql "SELECT count(*) FROM evento_tareas WHERE id=$T1")"

echo "3. Planificación: presupuesto"
r=$(post "r=evento_plan&id=$EV" --data-urlencode "accion=repartir" --data-urlencode "presupuesto=18.000")
check "Repartir" "302 $BASE/?r=evento_plan&id=$EV#presupuesto" "$r"
check "Partidas creadas y suman 18000" "14|18000.00" "$(sql "SELECT count(*)||'|'||sum(estimado) FROM presupuesto_partidas WHERE evento_id=$EV")"
get "r=evento_plan&id=$EV" > /dev/null
contiene "Muestra partidas" "$TMP/pag.html" "Fotografía y video"
contiene "Dinero del evento" "$TMP/pag.html" "Presupuesto del cliente"
PFOTO=$(sql "SELECT id FROM presupuesto_partidas WHERE evento_id=$EV AND categoria='foto_video'")
PBANQ=$(sql "SELECT id FROM presupuesto_partidas WHERE evento_id=$EV AND categoria='menu'")
r=$(post "r=evento_plan&id=$EV" --data-urlencode "accion=partida" --data-urlencode "partida_id=$PFOTO" --data-urlencode "categoria=foto_video" \
     --data-urlencode "concepto=Fotografía y video" --data-urlencode "proveedor_nombre=Foto Luz" --data-urlencode "estado=contratado" --data-urlencode "paga=cliente" \
     --data-urlencode "estimado=1.800" --data-urlencode "contratado=1.600,00" --data-urlencode "comision_pct=10" --data-urlencode "visible_cliente=0" --data-urlencode "visible_cliente=1")
check "Partida contratada" "contratado|1600.00|Foto Luz|true" "$(sql "SELECT estado||'|'||contratado||'|'||proveedor_nombre||'|'||visible_cliente FROM presupuesto_partidas WHERE id=$PFOTO")"
r=$(post "r=evento_plan&id=$EV" --data-urlencode "accion=partida" --data-urlencode "partida_id=$PBANQ" --data-urlencode "categoria=menu" \
     --data-urlencode "concepto=Banquete" --data-urlencode "proveedor_nombre=Catering Fino" --data-urlencode "estado=contratado" --data-urlencode "paga=planner" \
     --data-urlencode "estimado=5000" --data-urlencode "contratado=4.321,00" --data-urlencode "comision_pct=0" --data-urlencode "visible_cliente=0" --data-urlencode "visible_cliente=1")
check "Banquete en el contrato (reventa)" "planner|4321.00" "$(sql "SELECT paga||'|'||contratado FROM presupuesto_partidas WHERE id=$PBANQ")"
r=$(post "r=evento_plan&id=$EV" --data-urlencode "accion=partida" --data-urlencode "categoria=flores" --data-urlencode "concepto=Flores secretas" \
     --data-urlencode "estado=idea" --data-urlencode "paga=cliente" --data-urlencode "estimado=300" --data-urlencode "comision_pct=0" --data-urlencode "visible_cliente=0")
check "Partida nueva oculta al cliente" "f" "$(sql "SELECT visible_cliente FROM presupuesto_partidas WHERE evento_id=$EV AND concepto='Flores secretas'")"
r=$(post "r=evento_plan&id=$EV" --data-urlencode "accion=pago" --data-urlencode "partida_id=$PFOTO" --data-urlencode "monto=800" --data-urlencode "fecha=$HOY" \
     --data-urlencode "metodo=transferencia" --data-urlencode "nota=Anticipo")
check "Pago directo anotado" "302 $BASE/?r=evento_plan&id=$EV#partida-$PFOTO" "$r"
check "Pago guardado" "800.00|planner" "$(sql "SELECT monto||'|'||origen FROM presupuesto_pagos WHERE partida_id=$PFOTO")"
r=$(post "r=evento_plan&id=$EV" --data-urlencode "accion=pago" --data-urlencode "partida_id=$PFOTO" --data-urlencode "monto=5000" --data-urlencode "fecha=$HOY")
get "r=evento_plan&id=$EV" > /dev/null
contiene "No deja pagar de más" "$TMP/pag.html" "Es más de lo que falta"
contiene "Muestra lo que falta" "$TMP/pag.html" "falta \$800,00"
r=$(post "r=evento_plan&id=$EV" --data-urlencode "accion=comision" --data-urlencode "partida_id=$PFOTO" --data-urlencode "monto=100" --data-urlencode "fecha=$HOY")
check "Comisión cobrada (parcial)" "100.00" "$(sql "SELECT comision_cobrada FROM presupuesto_partidas WHERE id=$PFOTO")"
r=$(post "r=evento_plan&id=$EV" --data-urlencode "accion=partida_borrar" --data-urlencode "partida_id=$PFOTO")
get "r=evento_plan&id=$EV" > /dev/null
contiene "No borra partida con pagos" "$TMP/pag.html" "Tiene pagos anotados"

echo "4. Honorarios a la cotización"
r=$(post "r=evento_plan&id=$EV" --data-urlencode "accion=honorario" --data-urlencode "modo=porcentaje" --data-urlencode "pct=12")
check "Honorario por %" "porcentaje|12.00" "$(sql "SELECT honorario_modo||'|'||honorario_pct FROM eventos WHERE id=$EV")"
get "r=evento_plan&id=$EV" > /dev/null
contiene "Sin cotización: pide crearla" "$TMP/pag.html" "crea una cotización"
check "Cotización nueva" "200" "$(get "r=cotizacion&id=$EV")"
r=$(post_desde "r=cotizacion&id=$EV" "r=cotizacion&id=$EV&accion=crear" --data-urlencode "modo=vacia")
COT=$(sql "SELECT id FROM cotizaciones WHERE evento_id=$EV ORDER BY id DESC LIMIT 1")
check "Cotización en blanco creada" "302 $BASE/?r=cotizacion&id=$EV&c=$COT" "$r"
get "r=evento_plan&id=$EV" > /dev/null
contiene "Ya ofrece ponerlos en la cotización" "$TMP/pag.html" "Poner en la cotización"
r=$(post "r=evento_plan&id=$EV" --data-urlencode "accion=honorario_cotizacion" --data-urlencode "cotizacion_id=$COT" --data-urlencode "monto=2.400,00")
check "Va a la cotización" "302 $BASE/?r=cotizacion&id=$EV&c=$COT" "$r"
check "Línea de honorarios" "honorarios|2400.00" "$(sql "SELECT categoria||'|'||precio_unitario FROM cotizacion_items WHERE cotizacion_id=$COT AND categoria='honorarios'")"
get "r=cotizacion&id=$EV&c=$COT" > /dev/null
contiene "Se ve en la cotización" "$TMP/pag.html" "Organización y coordinación integral"

echo "5. Ideas"
check "Abre Ideas" "200" "$(get "r=evento_ideas&id=$EV")"
get "r=evento_ideas&id=$EV" > /dev/null; T=$(csrf)
r=$(curl -s -b "$JAR" -c "$JAR" -o "$TMP/post.html" -w "%{http_code} %{redirect_url}" -F "csrf=$T" -F "accion=subir" -F "categoria=flores" -F "nota=Ramo así" \
     -F "fotos[]=@$FOTO100;type=image/jpeg" -F "fotos[]=@$FOTO100;type=image/jpeg" "$BASE/?r=evento_ideas&id=$EV")
check "Subir dos fotos" "302 $BASE/?r=evento_ideas&id=$EV#ideas" "$r"
check "Dos ideas del organizador" "2" "$(sql "SELECT count(*) FROM evento_ideas WHERE evento_id=$EV AND origen='planner'")"
IDEA=$(sql "SELECT id FROM evento_ideas WHERE evento_id=$EV ORDER BY id LIMIT 1")
check "Ver la foto" "200 image/jpeg" "$(curl -s -b "$JAR" -o /dev/null -w '%{http_code} %{content_type}' "$BASE/?r=evento_ideas&id=$EV&foto=$IDEA")"
get "r=evento_ideas&id=$EV" > /dev/null; T=$(csrf)
r=$(curl -s -b "$JAR" -c "$JAR" -o "$TMP/post.html" -w "%{http_code}" -F "csrf=$T" -F "accion=subir" -F "fotos[]=@$TMP/no_foto.jpg;type=image/jpeg" "$BASE/?r=evento_ideas&id=$EV")
get "r=evento_ideas&id=$EV" > /dev/null
contiene "Archivo que no es foto: error claro" "$TMP/pag.html" "debe ser una foto"
r=$(post "r=evento_ideas&id=$EV" --data-urlencode "accion=marcar" --data-urlencode "idea_id=$IDEA" --data-urlencode "valor=si")
check "Me encanta" "t" "$(sql "SELECT aprobada FROM evento_ideas WHERE id=$IDEA")"
r=$(post "r=evento_ideas&id=$EV" --data-urlencode "accion=estilo" --data-urlencode "tematica=Boho en tonos tierra" \
     --data-urlencode "colores[0]=#c08552" --data-urlencode "usar[0]=1" --data-urlencode "colores[1]=#f3e9dc" --data-urlencode "usar[1]=1" --data-urlencode "colores[2]=#000000")
check "Temática y colores (solo los marcados)" "Boho en tonos tierra|#c08552,#f3e9dc" "$(sql "SELECT tematica||'|'||paleta FROM eventos WHERE id=$EV")"
get "r=evento_ideas&id=$EV" > /dev/null
contiene "Muestra la paleta" "$TMP/pag.html" "background:#c08552"

echo "6. Portal del cliente"
TOKEN=$(sql "SELECT token_cliente FROM eventos WHERE id=$EV")
sql "UPDATE evento_tareas SET hecha = false WHERE evento_id=$EV" > /dev/null
rm -f "$JAR"
check "Portal abre" "200" "$(get "r=portal&t=$TOKEN")"
contiene "Tarjeta de planificación" "$TMP/pag.html" "Tu planificación"
contiene "Tarjeta de ideas" "$TMP/pag.html" "Ideas para tu evento"
contiene "Temática en la tarjeta" "$TMP/pag.html" "Boho en tonos tierra"
contiene "Lugar por definir en el portal" "$TMP/pag.html" "Por definir"
check "Mi planificación" "200" "$(get "r=portal_plan&t=$TOKEN")"
contiene "Sus tareas" "$TMP/pag.html" "Tus tareas"
contiene "Su presupuesto" "$TMP/pag.html" "Tu presupuesto"
contiene "Ve la partida de foto" "$TMP/pag.html" "Fotografía y video"
no_contiene "No ve la partida oculta" "$TMP/pag.html" "Flores secretas"
no_contiene "No ve el costo de reventa del banquete" "$TMP/pag.html" "4.321"
contiene "El banquete dice incluido" "$TMP/pag.html" "Incluido en tu contrato"
no_contiene "No ve comisiones" "$TMP/pag.html" "comisi"
TC=$(sql "SELECT id FROM evento_tareas WHERE evento_id=$EV AND asignado='cliente' ORDER BY vence LIMIT 1")
TP=$(sql "SELECT id FROM evento_tareas WHERE evento_id=$EV AND asignado='planner' ORDER BY vence LIMIT 1")
r=$(post_portal100 "r=portal_plan&t=$TOKEN" --data-urlencode "accion=tarea" --data-urlencode "tarea_id=$TC" --data-urlencode "hecha=1")
check "El cliente marca su tarea" "true|cliente" "$(sql "SELECT hecha::text||'|'||hecha_por FROM evento_tareas WHERE id=$TC")"
r=$(post_portal100 "r=portal_plan&t=$TOKEN" --data-urlencode "accion=tarea" --data-urlencode "tarea_id=$TP" --data-urlencode "hecha=1")
check "No marca la del organizador" "false" "$(sql "SELECT hecha::text FROM evento_tareas WHERE id=$TP")"
r=$(post_portal100 "r=portal_plan&t=$TOKEN" --data-urlencode "accion=pago" --data-urlencode "partida_id=$PFOTO" --data-urlencode "monto=300" --data-urlencode "fecha=$HOY" --data-urlencode "metodo=deuna")
check "El cliente anota un pago" "1" "$(sql "SELECT count(*) FROM presupuesto_pagos WHERE partida_id=$PFOTO AND origen='cliente'")"
PPC=$(sql "SELECT id FROM presupuesto_pagos WHERE partida_id=$PFOTO AND origen='cliente'")
PPP=$(sql "SELECT id FROM presupuesto_pagos WHERE partida_id=$PFOTO AND origen='planner'")
r=$(post_portal100 "r=portal_plan&t=$TOKEN" --data-urlencode "accion=pago_borrar" --data-urlencode "pago_id=$PPP")
check "No borra el pago del organizador" "1" "$(sql "SELECT count(*) FROM presupuesto_pagos WHERE id=$PPP")"
r=$(post_portal100 "r=portal_plan&t=$TOKEN" --data-urlencode "accion=pago_borrar" --data-urlencode "pago_id=$PPC")
check "Borra el suyo" "0" "$(sql "SELECT count(*) FROM presupuesto_pagos WHERE id=$PPC")"
PFLOR=$(sql "SELECT id FROM presupuesto_partidas WHERE evento_id=$EV AND concepto='Flores secretas'")
r=$(post_portal100 "r=portal_plan&t=$TOKEN" --data-urlencode "accion=pago" --data-urlencode "partida_id=$PFLOR" --data-urlencode "monto=10" --data-urlencode "fecha=$HOY")
check "No paga partidas ocultas" "0" "$(sql "SELECT count(*) FROM presupuesto_pagos WHERE partida_id=$PFLOR")"
check "Ideas del cliente" "200" "$(get "r=portal_ideas&t=$TOKEN")"
contiene "Ve las fotos del organizador" "$TMP/pag.html" "r=portal_ideas&amp;t=$TOKEN&amp;foto=$IDEA"
check "Ve la imagen con su enlace" "200 image/jpeg" "$(curl -s -o /dev/null -w '%{http_code} %{content_type}' "$BASE/?r=portal_ideas&t=$TOKEN&foto=$IDEA")"
get "r=portal_ideas&t=$TOKEN" > /dev/null; T=$(csrf)
r=$(curl -s -b "$JAR" -c "$JAR" -o "$TMP/post.html" -w "%{http_code} %{redirect_url}" -F "csrf=$T" -F "accion=subir" -F "categoria=vestuario" \
     -F "fotos[]=@$FOTO100;type=image/jpeg" "$BASE/?r=portal_ideas&t=$TOKEN")
check "El cliente sube una foto" "1" "$(sql "SELECT count(*) FROM evento_ideas WHERE evento_id=$EV AND origen='cliente'")"
ICLI=$(sql "SELECT id FROM evento_ideas WHERE evento_id=$EV AND origen='cliente'")
r=$(post_portal100 "r=portal_ideas&t=$TOKEN" --data-urlencode "accion=borrar" --data-urlencode "idea_id=$IDEA")
check "No borra la foto del organizador" "1" "$(sql "SELECT count(*) FROM evento_ideas WHERE id=$IDEA")"
r=$(post_portal100 "r=portal_ideas&t=$TOKEN" --data-urlencode "accion=marcar" --data-urlencode "idea_id=$IDEA" --data-urlencode "valor=no")
check "Marca 'no va'" "f" "$(sql "SELECT aprobada FROM evento_ideas WHERE id=$IDEA")"
r=$(post_portal100 "r=portal_ideas&t=$TOKEN" --data-urlencode "accion=borrar" --data-urlencode "idea_id=$ICLI")
check "Borra la suya" "0" "$(sql "SELECT count(*) FROM evento_ideas WHERE id=$ICLI")"
check "Enlace falso" "404" "$(get "r=portal_plan&t=0000000000000000000000000000000000000000000000ff")"
check "Foto con enlace falso" "404" "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/?r=portal_ideas&t=0000000000000000000000000000000000000000000000ff&foto=$IDEA")"
check "Sin sesión no entra a Planificación" "302" "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/?r=evento_plan&id=$EV")"

echo "7. Comisiones y configuración"
entrar "$EMAILP" "claveSegura1"
sql "UPDATE eventos SET estado='separado' WHERE id=$EV" > /dev/null
check "Comisiones" "200" "$(get "r=comisiones")"
contiene "Por cobrar al fotógrafo" "$TMP/pag.html" "Foto Luz"
contiene "Botón Ya me pagó" "$TMP/pag.html" "Ya me pagó"
contiene "Falta cobrar 60" "$TMP/pag.html" "\$60,00"
r=$(post "r=comisiones" --data-urlencode "accion=cobrar" --data-urlencode "partida_id=$PFOTO" --data-urlencode "monto=0,00" --data-urlencode "fecha=$HOY")
get "r=comisiones" > /dev/null
contiene "Monto 0: error claro" "$TMP/pag.html" "Escribe cuánto te pagó"
check "Comisiones cobradas" "200" "$(get "r=comisiones&ver=cobradas&periodo=anio")"
contiene "Lista lo cobrado" "$TMP/pag.html" "Foto Luz"
check "Menú Más tiene Comisiones" "200" "$(get "r=mas")"
contiene "Enlace a comisiones" "$TMP/pag.html" "r=comisiones"
check "Configuración → Reglas" "200" "$(get "r=configuracion&tab=reglas")"
contiene "Campo máximo de eventos por día" "$TMP/pag.html" 'name="max_eventos_dia"'
contiene "Campo comisión de proveedores" "$TMP/pag.html" 'name="comision_pct"'
check "Configuración → Tareas" "200" "$(get "r=configuracion&tab=tareas")"
contiene "Para quién en la plantilla" "$TMP/pag.html" 'name="asignado"'

echo "8. Página pública: cupo del día"
SLUGP=$(sql "SELECT slug FROM negocios WHERE id=$NIDP")
check "Página pública" "200" "$(get "r=sitio&s=$SLUGP")"
contiene "Texto de organizador" "$TMP/pag.html" "si podemos organizar tu evento"
get "r=disponible&s=$SLUGP&fecha=$FUT" > /dev/null
contiene "Un evento separado de 2: pocos cupos" "$TMP/pag.html" '"estado":"consultar"'
sql "UPDATE negocios SET max_eventos_dia=1 WHERE id=$NIDP" > /dev/null
get "r=disponible&s=$SLUGP&fecha=$FUT" > /dev/null
contiene "Máximo 1: sin cupo" "$TMP/pag.html" '"estado":"ocupado"'
get "r=evento_plan&id=$EV" > /dev/null
no_contiene "El evento no se cuenta a sí mismo" "$TMP/pag.html" "pn-carga"

echo "9. Roles y otros negocios"
sql "INSERT INTO usuarios (negocio_id, nombre, email, password_hash, rol) VALUES ($NIDP, 'Coordi', 'coordi$NIDP@prueba.ec', '$(php -r 'echo password_hash("claveSegura1", PASSWORD_DEFAULT);')', 'coordinador')" > /dev/null
entrar "coordi$NIDP@prueba.ec" "claveSegura1"
check "Coordinador abre Planificación" "200" "$(get "r=evento_plan&id=$EV")"
no_contiene "Coordinador no ve el dinero" "$TMP/pag.html" "Presupuesto del cliente"
contiene "Coordinador ve las tareas" "$TMP/pag.html" "Lista de tareas"
TX=$(sql "SELECT id FROM evento_tareas WHERE evento_id=$EV ORDER BY id LIMIT 1")
r=$(post "r=evento_plan&id=$EV" --data-urlencode "accion=tarea_marcar" --data-urlencode "tarea_id=$TX" --data-urlencode "hecha=1")
check "Coordinador marca tareas" "true" "$(sql "SELECT hecha::text FROM evento_tareas WHERE id=$TX")"
r=$(post "r=evento_plan&id=$EV" --data-urlencode "accion=repartir" --data-urlencode "presupuesto=1000")
check "Coordinador no toca el presupuesto" "403" "${r%% *}"
check "Coordinador no ve comisiones" "302" "$(get "r=comisiones")"
check "Coordinador abre Ideas" "200" "$(get "r=evento_ideas&id=$EV")"
registrar salon "Otro Planner $RANDOM"
check "Otro negocio: evento ajeno" "404" "$(get "r=evento_plan&id=$EV")"
check "Otro negocio: ideas ajenas" "404" "$(get "r=evento_ideas&id=$EV")"
check "Otro negocio: foto ajena" "404" "$(curl -s -b "$JAR" -o /dev/null -w '%{http_code}' "$BASE/?r=evento_ideas&id=$EV&foto=$IDEA")"
r=$(post "r=comisiones" --data-urlencode "accion=cobrar" --data-urlencode "partida_id=$PFOTO" --data-urlencode "monto=1" --data-urlencode "fecha=$HOY")
check "Otro negocio no cobra comisiones ajenas" "100.00" "$(sql "SELECT comision_cobrada FROM presupuesto_partidas WHERE id=$PFOTO")"
check "Plan Planner: Planificación sin candado" "200" "$(get "r=comisiones")"

echo "10. Lugares con dirección y mapa"
entrar "$EMAILP" "claveSegura1"
r=$(post_desde "r=espacios&nuevo=1" "r=espacios&accion=guardar" --data-urlencode "nombre=Iglesia de Guápulo" --data-urlencode "tipo_lugar=ceremonia" --data-urlencode "direccion=Plaza de Guápulo" \
     --data-urlencode "mapa_url=https://maps.app.goo.gl/abc123" --data-urlencode "contacto=Padre Luis" --data-urlencode "telefono=0991112222" --data-urlencode "color=#3E7C59")
check "Lugar con dirección, mapa y contacto" "ceremonia|Plaza de Guápulo|https://maps.app.goo.gl/abc123|Padre Luis" \
      "$(sql "SELECT tipo_lugar||'|'||direccion||'|'||mapa_url||'|'||contacto FROM espacios WHERE negocio_id=$NIDP AND nombre='Iglesia de Guápulo'")"
r=$(post_desde "r=espacios&nuevo=1" "r=espacios&accion=guardar" --data-urlencode "nombre=Lugar Raro" --data-urlencode "tipo_lugar=recepcion" --data-urlencode "direccion=x" --data-urlencode "mapa_url=javascript:alert(1)")
contiene "Mapa con enlace peligroso: error" "$TMP/post.html" "https://"
check "No se guardó" "0" "$(sql "SELECT count(*) FROM espacios WHERE negocio_id=$NIDP AND nombre='Lugar Raro'")"
LG=$(sql "SELECT id FROM espacios WHERE negocio_id=$NIDP AND nombre='Iglesia de Guápulo'")
sql "INSERT INTO evento_espacios (negocio_id, evento_id, espacio_id, periodo) VALUES ($NIDP, $EV, $LG, tsrange('$FUT 15:00','$FUT 23:00'))" > /dev/null
get "r=portal&t=$TOKEN" > /dev/null
contiene "Portal: dirección del lugar" "$TMP/pag.html" "Plaza de Guápulo"
contiene "Portal: enlace al mapa" "$TMP/pag.html" "maps.app.goo.gl/abc123"
