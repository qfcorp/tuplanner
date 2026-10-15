<?php
use TuPlanner\{Planificacion, Reportes, WhatsApp};

/**
 * Comisiones de proveedores (migración 100): /?r=comisiones[&ver=cobradas&periodo=mes|anio|...]
 *  - Por cobrar: de todos los eventos separados, confirmados o realizados, lo que cada proveedor te debe de comisión.
 *  - Cobradas: lo que ya te pagaron en el periodo.
 * POST accion=cobrar (partida_id, monto, fecha). Solo dueño y vendedor.
 */
exigir(es_comercial($u), 'Solo el dueño o un vendedor ven las comisiones.');
$nid = (int) $u['negocio_id'];
$plan = new Planificacion(db());

if (es_post()) {
    verificar_csrf();
    exigir(puede_editar($u), 'Tu usuario solo puede mirar.');
    try {
        if (($_POST['accion'] ?? '') !== 'cobrar') throw new RuntimeException('Acción no válida.');
        $plan->cobrarComision($nid, (int) ($_POST['partida_id'] ?? 0), $_POST['monto'] ?? '', (string) ($_POST['fecha'] ?? '') ?: null,
                              (int) $u['id'], (string) $u['nombre']);
        aviso('Comisión cobrada anotada.');
    } catch (RuntimeException $e) {
        if ($e instanceof PDOException) throw $e;
        aviso($e->getMessage(), 'error');
    }
    redirigir('comisiones');
}

$ver = ($_GET['ver'] ?? '') === 'cobradas' ? 'cobradas' : 'por_cobrar';
$periodo = Reportes::periodo((string) ($_GET['periodo'] ?? 'anio'));
$lista = $ver === 'cobradas'
    ? $plan->comisiones($nid, 'cobradas', $periodo['desde'], $periodo['hasta'])
    : $plan->comisiones($nid, 'por_cobrar');
$total = array_sum(array_map(fn($r) => $ver === 'cobradas' ? (float) $r['comision_cobrada'] : $r['comision_por_cobrar'], $lista));
// Agrupar por proveedor para cobrar de una sola vez
$porProveedor = [];
foreach ($lista as $r) {
    $k = $r['proveedor_id'] ? 'p' . $r['proveedor_id'] : 'n' . mb_strtolower((string) $r['proveedor_nombre']);
    $porProveedor[$k]['nombre'] = $r['proveedor_nombre'] ?: 'Sin proveedor';
    $porProveedor[$k]['telefono'] = $r['proveedor_telefono'] ?? null;
    $porProveedor[$k]['filas'][] = $r;
    $porProveedor[$k]['total'] = ($porProveedor[$k]['total'] ?? 0) + ($ver === 'cobradas' ? (float) $r['comision_cobrada'] : $r['comision_por_cobrar']);
}
uasort($porProveedor, fn($a, $b) => $b['total'] <=> $a['total']);
vista('comisiones', compact('ver', 'periodo', 'porProveedor', 'total'), 'Comisiones · TuPlanner');
