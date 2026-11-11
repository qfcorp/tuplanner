<?php
/** Diseño general del sistema. @var string $contenido @var string $titulo @var ?array $u */
$rutaActual = (string) ($_GET['r'] ?? 'inicio');
// Menú (identidad gráfica común Tukán, docs/LINEA_GRAFICA.md): una sola definición en app/menu.php
$menuTk = $u ? tk_menu($u) : [];
$menuActual = tk_menu_actual($rutaActual, !empty($_GET['id']));
$campana = 0;
if ($u) {
    $campana = (new \TuPlanner\Notificaciones(db()))->sinLeer((int) $u['id']);
}
$avisos = tomar_avisos();
$diasPrueba = $u ? dias_prueba($u) : null;
$estadoCuenta = $u ? planes()->estadoCuenta($u) : null;
$diasPlan = ($estadoCuenta === 'activo' && !empty($u['activo_hasta']))
    ? (int) (new DateTimeImmutable('today'))->diff(new DateTimeImmutable($u['activo_hasta']))->days : null;
$adminViendo = !empty($_SESSION['admin_viendo']) && superadmin();
// (7 nov 2026) El dueño acepta los términos y la privacidad de Tukán (src/TukanLegal.php); se acepta desde Mi plan
require_once dirname(__DIR__, 2) . '/src/TukanLegal.php';
$pideTerminos = $u && es_admin_total($u) && !$adminViendo && !TukanLegal::aceptado(db(), (string) $u['negocio_id']);
$enApp = en_app_android();   // app de Google Play: sin «Renovar» ni enlaces para pagar el software (docs/GOOGLE_PLAY.md)
$modulosCss = glob(RAIZ . '/public/assets/mod_*.css') ?: [];
$modulosJs = glob(RAIZ . '/public/assets/mod_*.js') ?: [];
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#4A1E2A">
<link rel="manifest" href="/manifest.json">
<link rel="apple-touch-icon" href="/assets/apple-touch-icon.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="TuPlanner">
<title><?= e($titulo) ?></title>
<link rel="stylesheet" href="/assets/app.css?v=<?= filemtime(RAIZ . '/public/assets/app.css') ?>">
<?php foreach ($modulosCss as $css): ?>
<link rel="stylesheet" href="/assets/<?= e(basename($css)) ?>?v=<?= filemtime($css) ?>">
<?php endforeach; ?>
<?php /* Identidad gráfica común Tukán: el kit va AL FINAL (idéntico en todos los programas) y en la línea de abajo, lo único propio: el color */ ?>
<link rel="stylesheet" href="/assets/tukan-ui.css?v=<?= filemtime(RAIZ . '/public/assets/tukan-ui.css') ?>">
<style>:root{ --tk-color:#8A3F5C; --tk-color-claro:#F0B3CB; --tk-color-texto:#ffffff; }</style>
</head>
<body class="tk <?= ($u ? 'con-sesion' : 'sin-sesion') . ($enApp ? ' en-app' : '') ?>">
<header class="tk-arriba no-imprimir">
    <div class="te-arriba-izq">
        <?php if ($u): ?><button class="tk-boton-menu" type="button" aria-label="Abrir el menú" aria-expanded="false" aria-controls="tk-menu">☰</button><?php endif; ?>
        <a class="tk-marca" href="<?= e(url($u ? 'inicio' : 'login')) ?>">
            <?php if ($u && !empty($u['logo'])): ?><img src="<?= e(url('archivo', ['tipo' => 'logo', 'id' => $u['negocio_id'], 'v' => substr((string) $u['logo'], 0, 6)])) ?>" alt="">
            <?php else: ?><span class="tk-logo" aria-hidden="true">TP</span><?php endif; ?>
            <?php if ($u): ?><span><b><?= e($u['negocio']) ?></b><small>TuPlanner · Plan <?= e($u['plan_nombre'] ?? $u['plan_codigo']) ?></small></span>
            <?php else: ?><span><b>TuPlanner</b><small>Bodas, quince años y eventos</small></span><?php endif; ?>
        </a>
    </div>
    <?php if ($u): ?>
    <div class="tk-usuario">
        <a class="tk-nombre" href="<?= e(url('mi_cuenta')) ?>" title="Mi cuenta">👤 <?= e($u['nombre']) ?></a>
        <span class="tk-rol"><?= e(\TuPlanner\Usuarios::ROLES[$u['rol']] ?? $u['rol']) ?></span>
        <a class="tk-salir" href="<?= e(url('salir')) ?>" title="Cerrar sesión">Salir</a>
    </div>
    <?php endif; ?>
</header>
<div class="tk-cuerpo">
<?php if ($u): ?>
    <nav class="tk-menu no-imprimir" id="tk-menu" aria-label="Menú principal">
        <div class="te-buscar">
            <input type="search" id="buscar-rapido" placeholder="🔍 Buscar evento o cliente…" autocomplete="off" aria-label="Buscar evento o cliente">
            <div id="buscar-resultados" class="buscar-resultados" hidden></div>
        </div>
        <?php foreach ($menuTk as [$tituloGrupo, $opciones]): ?>
        <div class="tk-menu-titulo"><?= e($tituloGrupo) ?></div>
        <?php foreach ($opciones as [$r, $texto, $emoji]): ?>
        <a href="<?= e(url($r)) ?>"<?= $menuActual === $r ? ' class="activo" aria-current="page"' : '' ?>><span class="tk-ico" aria-hidden="true"><?= $emoji ?></span> <?= e($texto) ?><?php
            if ($r === 'notificaciones' && $campana > 0): ?> <span class="tk-globito" aria-label="<?= $campana ?> avisos sin leer"><?= $campana > 99 ? '99+' : $campana ?></span><?php endif; ?></a>
        <?php endforeach; ?>
        <?php endforeach; ?>
        <p class="te-pie-legal"><?= TukanLegal::enlaces(url('privacidad')) ?></p>
    </nav>
    <div class="tk-fondo-menu"></div>
<?php endif; ?>
<main class="tk-contenido" id="contenido">
    <?php ob_start(); /* avisos de arriba: en el Inicio van debajo del saludo (donde la vista pone <!--tk-tiras-->); en las demás pantallas, arriba */ ?>
    <?php if ($diasPrueba !== null && $diasPrueba >= 0): ?>
        <div class="tk-tira franja-prueba no-imprimir"><span>⏳ <b>Demo:</b> <?= $diasPrueba === 0 ? 'termina hoy' : "te quedan $diasPrueba " . ($diasPrueba === 1 ? 'día' : 'días') ?>.</span>
            <?php if (es_admin_total($u)): ?><a class="tk-boton chico" href="<?= e(url('planes')) ?>">Ver Mi plan</a><?php endif; ?></div>
    <?php endif; ?>
    <?php if ($adminViendo): ?>
        <div class="tk-tira franja-admin no-imprimir"><span>🛠️ Estás viendo <strong><?= e($u['negocio']) ?></strong> como administrador de Tukán.</span>
            <a class="tk-boton oscuro chico" href="<?= e(url('admin_volver')) ?>">Volver al panel</a></div>
    <?php endif; ?>
    <?php if ($estadoCuenta === 'por_vencer'): ?>
        <div class="tk-tira franja-prueba no-imprimir"><span>⚠️ Tu plan venció el <?= (new DateTimeImmutable($u['activo_hasta']))->format('d/m') ?>.<?php if (!$enApp): ?> Renueva en los próximos días para no perder el acceso.<?php endif; ?></span>
            <?php if (!$enApp): ?><a class="tk-boton chico" href="<?= e(url('planes')) ?>">Renovar</a><?php endif; ?></div>
    <?php elseif ($diasPlan !== null && $diasPlan <= 5): ?>
        <div class="tk-tira franja-prueba no-imprimir"><span>⏰ Tu plan vence <?= $diasPlan === 0 ? 'hoy' : "en $diasPlan " . ($diasPlan === 1 ? 'día' : 'días') ?>.</span>
            <?php if (!$enApp): ?><a class="tk-boton chico" href="<?= e(url('planes')) ?>">Renovar</a><?php endif; ?></div>
    <?php endif; ?>
    <?php if ($pideTerminos): ?><?= TukanLegal::aviso(campo_csrf(), url('planes'), url('privacidad')) ?><?php endif; ?>
    <?php foreach ($avisos as $a): ?>
        <p class="tk-aviso <?= $a['tipo'] === 'error' ? 'mal' : ($a['tipo'] === 'ok' ? 'ok' : '') ?> te-aviso aviso-<?= e($a['tipo']) ?>" role="status"><?= e($a['texto']) ?></p>
    <?php endforeach; ?>
    <?php $tirasArriba = (string) ob_get_clean();
    if (str_contains($contenido, '<!--tk-tiras-->')) echo str_replace('<!--tk-tiras-->', $tirasArriba, $contenido);
    else echo $tirasArriba . $contenido; ?>
</main>
</div>
<script src="/assets/tukan-ui.js?v=<?= filemtime(RAIZ . '/public/assets/tukan-ui.js') ?>" defer></script>
<script src="/assets/app.js?v=<?= filemtime(RAIZ . '/public/assets/app.js') ?>" defer></script>
<?php foreach ($modulosJs as $js): ?>
<script src="/assets/<?= e(basename($js)) ?>?v=<?= filemtime($js) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
