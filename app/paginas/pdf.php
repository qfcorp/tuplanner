<?php
/**
 * PDF del sistema: /?r=pdf&tipo=<tipo>&id=<id>
 * Cada tipo está en app/pdf/<tipo>.php (cotizacion, contrato, recibo, hoja, invitados, mesas...).
 * El archivo recibe $u (usuario), $negocio (fila de negocios) e $id, y debe terminar con \TuPlanner\Pdf::enviar(...).
 */
$tipo = (string) ($_GET['tipo'] ?? '');
$id = (int) ($_GET['id'] ?? 0);
if (!preg_match('/^[a-z_]+$/', $tipo) || !is_file(RAIZ . "/app/pdf/$tipo.php")) {
    http_response_code(404);
    vista('error', ['mensaje' => 'Ese documento no existe.'], 'PDF · TuPlanner');
    return;
}
$st = db()->prepare('SELECT * FROM negocios WHERE id = ?');
$st->execute([$u['negocio_id']]);
$negocio = $st->fetch();
try {
    require RAIZ . "/app/pdf/$tipo.php";
} catch (RuntimeException $e) {
    vista('error', ['mensaje' => $e->getMessage()], 'PDF · TuPlanner');
}
