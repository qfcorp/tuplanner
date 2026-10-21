<?php
use TuPlanner\{Eventos, Ia};

/**
 * Leer la cotización de un proveedor (módulo «Asistente IA» + presupuesto): /?r=ia_proveedor&id=<evento>
 * El organizador sube la foto (o el PDF) de la proforma que le mandó el proveedor y la IA propone las partidas del
 * presupuesto (JSON validado en Ia::validarLectura). Se muestra como BORRADOR: marca las que sirven y las agrega
 * («Pidiendo precios», «El cliente le paga directo»). Si no se puede leer (lo que sea): tukan_ia_mensaje_neutro('foto', …)
 * con el enlace para agregar la partida a mano. El archivo no se guarda.
 *   POST accion=leer (archivo, nota) · POST accion=guardar (partidas marcadas)
 */
exigir(es_comercial($u) && puede_editar($u), 'Solo el dueño o un vendedor pueden cambiar el presupuesto.');
exigir(modulo_activo($u, 'presupuesto'), 'El presupuesto por proveedor no está en tu plan. Agrégalo en Mi plan.');
$nid = (int) $u['negocio_id'];
$id = (int) ($_GET['id'] ?? 0);
$ev = (new Eventos(db()))->obtener($nid, $id);
if (!$ev) {
    http_response_code(404);
    vista('error', ['mensaje' => 'No encontramos ese evento. Puede que el enlace esté mal o que sea de otro negocio.'], 'Cotización de proveedor · TuPlanner');
    return;
}
$aMano = url_completa('evento_plan', ['id' => $id]) . '#presupuesto';
$lectura = null;
$aviso = null;
$error = null;
$nota = '';
if (es_post()) {
    verificar_csrf();
    $accion = (string) ($_POST['accion'] ?? '');
    $nota = mb_substr(trim((string) ($_POST['nota'] ?? '')), 0, 500);
    try {
        if ($accion === 'leer') {
            $f = $_FILES['archivo'] ?? null;
            if (!is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) throw new RuntimeException('Elija la foto o el PDF de la cotización del proveedor.');
            if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) throw new RuntimeException('El archivo pesa más de 5 MB. Tome la foto otra vez o mándela más liviana.');
            if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) throw new RuntimeException('No se pudo subir el archivo. Intente otra vez.');
            $r = ia()->leerCotizacionProveedor($nid, (string) file_get_contents($f['tmp_name']), $nota, $aMano);
            if ($r['ok']) {
                $lectura = $r['lectura'];
                if (!$lectura['partidas']) $error = 'No encontramos valores en ese documento. Revise que sea la cotización del proveedor o agregue la partida a mano.';
            } else {
                $aviso = $r['aviso'];
                error_log('TuPlanner IA cotizacion_proveedor (negocio ' . $nid . '): ' . ($r['motivo'] ?? ''));
            }
        } elseif ($accion === 'guardar') {
            $n = ia()->guardarLectura($nid, $id, $_POST, (int) $u['id'], (string) $u['nombre']);
            aviso($n === 1 ? 'Partida agregada al presupuesto («Pidiendo precios»).' : "$n partidas agregadas al presupuesto («Pidiendo precios»).");
            header('Location: ' . url('evento_plan', ['id' => $id]) . '#presupuesto');
            exit;
        } else {
            throw new RuntimeException('Acción no válida.');
        }
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
        if (($accion ?? '') === 'guardar') {
            $lectura = ['proveedor' => campo('proveedor_nombre'), 'proveedor_id' => (int) campo('proveedor_id') ?: null, 'ruc' => '', 'telefono' => '', 'notas' => '',
                        'partidas' => array_values(array_filter(array_map(fn($p) => is_array($p) ? ['categoria' => (string) ($p['categoria'] ?? 'otros'),
                            'concepto' => mb_substr((string) ($p['concepto'] ?? ''), 0, 150), 'estimado' => (string) ($p['estimado'] ?? ''), 'usar' => !empty($p['usar'])] : null,
                            (array) ($_POST['partida'] ?? []))))];
        }
    }
}
vista('ia_proveedor', ['ev' => $ev, 'lectura' => $lectura, 'aviso' => $aviso, 'error' => $error, 'aMano' => $aMano, 'nota' => $nota,
                       'proveedores' => ia()->proveedores($nid)], 'Cotización de proveedor · TuPlanner');
