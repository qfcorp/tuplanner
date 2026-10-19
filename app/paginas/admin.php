<?php
$panel = new TuPlanner\PanelTukan(db());
$pedidosDemo = new TuPlanner\PedidosDemo(db());
if (es_post()) {   // pedido de demo: contactado o descartado
    verificar_csrf();
    try {
        $pid = (int) ($_POST['pedido'] ?? 0);
        $estado = ($_POST['estado'] ?? '') === 'descartada' ? 'descartada' : 'contactada';
        $pedidosDemo->marcar($pid, $estado);
        registrar_admin('Pedido de demo N.º ' . $pid . ': ' . TuPlanner\PedidosDemo::ESTADOS[$estado]);
        aviso('Pedido de demo marcado como ' . mb_strtolower(TuPlanner\PedidosDemo::ESTADOS[$estado]) . '.');
    } catch (RuntimeException $e) {
        aviso($e->getMessage(), 'error');
    }
    redirigir('admin');
}
$r = $panel->resumen();
$registro = $panel->registro(null, 12);
$pedidos = $pedidosDemo->abiertos();
vista_admin('admin', compact('r', 'registro', 'pedidos'), 'Resumen · Panel Tukán');
