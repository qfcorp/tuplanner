<?php
use TuPlanner\PanelTukan;

$filtro = (string) ($_GET['f'] ?? '');
if ($filtro !== '' && !isset(PanelTukan::ESTADOS[$filtro])) $filtro = '';
$plan = (string) ($_GET['plan'] ?? '');
$listaPlanes = planes()->lista();
if ($plan !== '' && !in_array($plan, array_column($listaPlanes, 'codigo'), true)) $plan = '';
$buscar = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
$negocios = (new PanelTukan(db()))->negocios($filtro, $buscar, null, $plan);
vista_admin('admin_negocios', compact('negocios', 'filtro', 'buscar', 'plan', 'listaPlanes'), 'Negocios · Panel Tukán');
