<?php
use TuPlanner\PanelTukan;

/** Planes de TuPlanner y estado de la cuenta (dueño). También se abre con la prueba vencida. */
exigir(es_dueno($u), 'Solo el dueño puede ver y cambiar el plan de TuPlanner.');
$nid = (int) $u['negocio_id'];
$p = planes();
$lista = $p->lista();
$precios = [];
foreach ($lista as $pl) foreach (['mensual', 'semestral', 'anual'] as $per) $precios[$pl['codigo']][$per] = $p->cotizar($pl['codigo'], $per);
$estadoCuenta = $p->estadoCuenta($u);
$pagos = (new PanelTukan(db()))->pagos($nid, 30);
$st = db()->prepare("SELECT (SELECT count(*) FROM espacios WHERE negocio_id = ? AND activo) AS espacios,
                            (SELECT count(*) FROM usuarios WHERE negocio_id = ? AND activo) AS usuarios");
$st->execute([$nid, $nid]);
$uso = $st->fetch();
vista('planes', compact('lista', 'precios', 'estadoCuenta', 'pagos', 'uso'), 'Mi plan · TuPlanner');
