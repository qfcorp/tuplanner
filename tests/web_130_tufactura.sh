#!/usr/bin/env bash
# Factura electrónica con TuFactura (módulo facturacion, 9 nov 2026), de punta a punta por HTTP con el TuFactura SIMULADO:
# apagado se ve la pantalla para pedirlo · encendido: conectar la clave · facturar un pago del evento · PDF · no se repite.
# Lo carga tests/run_web.sh (con web_base.sh).
puerto_libre() { php -r '$s = stream_socket_server("tcp://127.0.0.1:0"); $n = stream_socket_get_name($s, false); echo substr($n, strrpos($n, ":") + 1);'; }
PS=$(puerto_libre)
mkdir -p "$TMP/tfsim"
TF_SIM_DIR="$TMP/tfsim" php -S "127.0.0.1:$PS" tests/tufactura_simulado.php > /dev/null 2>&1 &
TFSIM=$!
for _ in $(seq 1 50); do curl -s -o /dev/null "http://127.0.0.1:$PS/" && break; sleep 0.1; done

echo "1. Factura electrónica apagada: se puede pedir"
registrar quinta "Hacienda Factura $RANDOM"
pagar_plan quinta
get "r=mas" > /dev/null
contiene "Más tiene «Factura electrónica»" "$TMP/pag.html" "r=tufactura"
check "Apagado: la pantalla abre" "200" "$(get 'r=tufactura')"
contiene "Apagado: explica TuFactura y deja pedirlo" "$TMP/pag.html" "Quiero facturar con TuFactura"
contiene "… el pedido va a Mi plan" "$TMP/pag.html" "modulo=facturacion"

echo "2. Encendido: conectar y facturar un pago"
sql "UPDATE negocios SET modulos_extra = CASE WHEN COALESCE(modulos_extra, '') = '' THEN 'facturacion' ELSE modulos_extra || ',facturacion' END WHERE id = $NID" > /dev/null
get "r=tufactura" > /dev/null
contiene "Pide conectar la cuenta" "$TMP/pag.html" "Conectar y probar"
post "r=tufactura" -d tf_accion=conectar -d "clave=tf_$(printf 'dead%.0s' $(seq 10))" -d "url=http://127.0.0.1:$PS" > /dev/null
get "r=tufactura" > /dev/null
contiene "Clave desactivada: aviso" "$TMP/pag.html" "no válida o desactivada"
post "r=tufactura" -d tf_accion=conectar -d "clave=tf_$(printf 'ab12%.0s' $(seq 10))" -d "url=http://127.0.0.1:$PS" > /dev/null
get "r=tufactura" > /dev/null
contiene "Conectado" "$TMP/pag.html" "Conectado con TuFactura"
EV=$(crear_evento_sql "$(date -d '+70 days' +%F)" separado)
sql "UPDATE clientes SET identificacion = '1710034065', email = 'cliente@ejemplo.com' WHERE id = (SELECT cliente_id FROM eventos WHERE id = $EV)" > /dev/null
PID=$(sql "INSERT INTO pagos (negocio_id, evento_id, tipo, monto, metodo, estado) VALUES ($NID, $EV, 'evento', 450, 'efectivo', 'confirmado') RETURNING id" | head -1)
get "r=tufactura" > /dev/null
contiene "El pago sale para facturar" "$TMP/pag.html" "name=\"origen_id\" value=\"$PID\""
post "r=tufactura" -d tf_accion=facturar_origen -d "origen_id=$PID" > /dev/null
get "r=tufactura" > /dev/null
contiene "Factura autorizada" "$TMP/pag.html" "001-002-000000001"
DOC=$(sql "SELECT id FROM tukan_factura_documentos WHERE negocio_id = $NID AND origen_id = '$PID'")
check "PDF de la factura" "application/pdf" "$(curl -s -b "$JAR" -o "$TMP/f.pdf" -w '%{content_type}' "$BASE/?r=tufactura&pdf=$DOC")"
post "r=tufactura" -d tf_accion=facturar_origen -d "origen_id=$PID" > /dev/null
check "No se factura dos veces" "1" "$(sql "SELECT count(*) FROM tukan_factura_documentos WHERE negocio_id = $NID")"
check "A nombre del cliente" "1710034065" "$(sql "SELECT envio::json->'cliente'->>'identificacion' FROM tukan_factura_documentos WHERE id = $DOC")"
kill $TFSIM 2>/dev/null
