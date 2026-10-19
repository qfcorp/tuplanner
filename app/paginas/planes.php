<?php
use TuPlanner\{Modulos, PanelTukan};

/**
 * Mi plan (dueño): sus módulos encendidos, los que puede agregar con su precio, lo que paga al mes, los planes y sus pagos.
 * "Pedir este módulo" crea un pedido (solicitud cambio_modulos) que Tukán aprueba desde el Panel Tukán Central.
 * También se abre con la demo terminada o el plan vencido.
 */
exigir(es_dueno($u), 'Solo el dueño puede ver y cambiar el plan de TuPlanner.');
$nid = (int) $u['negocio_id'];
if (es_post()) {
    verificar_csrf();
    try {
        if (($_POST['accion'] ?? '') !== 'pedir') throw new RuntimeException('Acción no válida.');
        $mid = (string) ($_POST['modulo'] ?? '');
        if (!Modulos::existe($mid)) throw new RuntimeException('Ese módulo no existe.');
        modulos()->pedir($nid, (int) $u['id'], [$mid], campo('nota'));
        aviso('Listo: pediste ' . Modulos::nombre($mid) . '. Tukán te escribe para activarlo; tus datos no cambian.');
    } catch (RuntimeException $e) {
        aviso($e->getMessage(), 'error');
    }
    header('Location: ' . url('planes') . '#mod-' . rawurlencode((string) ($_POST['modulo'] ?? '')));
    exit;
}
$p = planes();
$mods = modulos();
$estadoMod = $mods->estado($nid);
$catalogo = $mods->catalogo();
$planesMod = $mods->planes();
$precios = [];
foreach (['mensual', 'semestral', 'anual'] as $per) $precios[$per] = $p->cotizarNegocio($nid, (string) $u['plan_codigo'], $per);
$estadoCuenta = $p->estadoCuenta($u);
$pagos = (new PanelTukan(db()))->pagos($nid, 30);
$pedidos = $mods->solicitudesDe($nid);
$pedidosPendientes = [];
foreach ($pedidos as $s) if ($s['estado'] === 'pendiente') foreach (Modulos::parse($s['encender']) as $m) $pedidosPendientes[$m] = true;
$st = db()->prepare("SELECT (SELECT count(*) FROM espacios WHERE negocio_id = ? AND activo) AS espacios,
                            (SELECT count(*) FROM usuarios WHERE negocio_id = ? AND activo) AS usuarios");
$st->execute([$nid, $nid]);
$uso = $st->fetch();
$listaPlanes = $p->lista();
vista('planes', compact('estadoMod', 'catalogo', 'planesMod', 'precios', 'estadoCuenta', 'pagos', 'pedidos', 'pedidosPendientes', 'uso', 'listaPlanes'),
      'Mi plan · TuPlanner');
