# Acceso: registro con prueba gratis, entrar, salir, páginas públicas básicas
echo "1. Registro y acceso"
check "Página de entrar" "200" "$(get "r=login")"
contiene "Dice TuPlanner" "$TMP/pag.html" "TuPlanner"
registrar quinta "Hacienda La Prueba"
check "Se creó el negocio" "1" "$(sql "SELECT count(*) FROM negocios WHERE id=$NID")"
check "Plan quinta" "quinta" "$(sql "SELECT plan_codigo FROM negocios WHERE id=$NID")"
check "Datos de ejemplo: 2 espacios" "2" "$(sql "SELECT count(*) FROM espacios WHERE negocio_id=$NID")"
get "r=salir" > /dev/null
r=$(post "r=registro" --data-urlencode "negocio=Otro" --data-urlencode "nombre=X" --data-urlencode "email=malo" --data-urlencode "clave=1")
contiene "Registro con errores muestra el mensaje" "$TMP/post.html" "El correo no es válido"
check "Sin sesión, una página privada lleva a entrar" "302" "$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" "$BASE/?r=mas")"
r=$(post "r=login" --data-urlencode "email=$EMAIL" --data-urlencode "clave=mala")
contiene "Contraseña mala" "$TMP/post.html" "no coinciden"
r=$(post "r=login" --data-urlencode "email=$EMAIL" --data-urlencode "clave=claveSegura1")
check "Entrar lleva al inicio" "302 $BASE/?r=inicio" "$r"
check "Política de privacidad" "200" "$(get "r=privacidad")"
contiene "Privacidad habla de eventos" "$TMP/pag.html" "quintas"
check "Ruta que no existe: 404" "404" "$(get "r=no_existe")"
check "Menú Más" "200" "$(get "r=mas")"
contiene "Más lista Catálogo" "$TMP/pag.html" "Catálogo y precios"
check "Búsqueda rápida responde JSON" "200" "$(get "r=buscar&q=bo")"
contiene "JSON de búsqueda" "$TMP/pag.html" "resultados"
