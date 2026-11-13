<?php
use TuPlanner\FacturaTukan;

/**
 * Factura electrónica con TuFactura (módulo «facturacion», apagado de fábrica y solo a pedido).
 * Apagado: explica qué es y deja pedirlo (Mi plan). Encendido: conectar la cuenta de TuFactura, facturar pagos y ver las facturas.
 * ?r=tufactura&pdf=ID baja el PDF de una factura autorizada (con la clave del negocio, que nunca sale al navegador).
 */
$nid = (int) $u['negocio_id'];
$activo = FacturaTukan::activo($nid);
$admin = es_admin_total($u);
$titulo = 'Factura electrónica · ' . FacturaTukan::PROGRAMA;
if (!$activo) {
    vista('tufactura', ['html' => (new \TukanFactura(db(), 'solo-para-mostrar-la-pagina', FacturaTukan::PROGRAMA))->pantalla($nid, ['modulo' => false, 'programa' => FacturaTukan::PROGRAMA, 'sin_compras' => en_app_tienda(),
        'pedir' => $admin && !en_app_tienda() ? url('planes', ['modulo' => \TukanFactura::MODULO]) : null])], $titulo);
    return;
}
$tf = FacturaTukan::enlace();
if (isset($_GET['pdf'])) {
    try {
        $pdf = $tf->pdf($nid, (int) $_GET['pdf']);
        $d = $tf->documento($nid, (int) $_GET['pdf']);
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="factura-' . preg_replace('/[^0-9-]/', '', (string) $d['numero']) . '.pdf"');
        header('Cache-Control: private, no-store');
        echo $pdf;
    } catch (RuntimeException $e) {
        aviso($e->getMessage(), 'error');
        redirigir('tufactura');
    }
    return;
}
if (es_post()) {
    verificar_csrf();
    $acc = (string) ($_POST['tf_accion'] ?? '');
    if (!$admin && in_array($acc, ['conectar', 'opciones', 'desconectar'], true)) {
        aviso('Solo el dueño o un administrador conecta TuFactura.', 'error');
    } elseif ($r = $tf->atender($nid, $_POST, fn(string $id) => FacturaTukan::datos($nid, $id), FacturaTukan::ORIGEN)) {
        aviso($r[1], $r[0]);
    }
    redirigir('tufactura');
}
vista('tufactura', ['html' => $tf->pantalla($nid, ['modulo' => true, 'csrf' => campo_csrf(), 'accion' => url('tufactura'), 'es_admin' => $admin,
    'pdf' => fn(int $id) => url('tufactura', ['pdf' => $id]), 'cobros' => FacturaTukan::cobros($nid), 'origen' => FacturaTukan::ORIGEN,
    'programa' => FacturaTukan::PROGRAMA])], $titulo);
