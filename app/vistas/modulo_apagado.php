<?php
/** Página de un módulo que el negocio no tiene encendido (Anexo B). @var string $modulo */
$cat = modulos()->catalogo();
$m = $cat[$modulo] ?? ['nombre' => $modulo, 'icono' => '🔒', 'descripcion' => '', 'precio_mensual' => 0];
$pedido = false;
foreach (modulos()->solicitudesDe((int) $u['negocio_id']) as $s) {
    if ($s['estado'] === 'pendiente' && in_array($modulo, \TuPlanner\Modulos::parse($s['encender']), true)) $pedido = true;
}
// Pantallas de un evento (día del evento, invitados, plano, formulario…): se deja la cabecera y las pestañas del evento
$tab = (string) ($_GET['r'] ?? '');
$ev = in_array($tab, ['evento_dia', 'invitados', 'plano', 'evento_formulario', 'contrato', 'evento_plan', 'evento_ideas'], true) && !empty($_GET['id'])
    ? (new \TuPlanner\Eventos(db()))->obtener((int) $u['negocio_id'], (int) $_GET['id']) : null;
if ($ev) require RAIZ . '/app/vistas/_evento_tabs.php';
?>
<div class="cabecera"><h1><span aria-hidden="true"><?= e($m['icono']) ?></span> <?= e($m['nombre']) ?></h1></div>
<section class="bloque mod-apagado" style="max-width:640px">
    <p class="mod-apagado-etq">🔒 Este módulo no está en tu plan</p>
    <p><?= e($m['descripcion']) ?></p>
    <p class="mod-apagado-precio">Agrégalo por <strong><?= dinero($m['precio_mensual']) ?></strong> al mes.</p>
    <p class="suave ayuda-chica">Si ya lo usaste antes, tus datos siguen guardados: al encenderlo, todo vuelve como estaba.</p>
    <?php if ($pedido): ?>
        <p class="aviso aviso-ok" role="status">Ya pediste este módulo. Tukán te escribe para activarlo.</p>
    <?php endif; ?>
    <?php if (es_dueno($u)): ?>
        <div class="acciones"><a class="boton" href="<?= e(url('planes')) ?>#mod-<?= e($modulo) ?>">Ver Mi plan</a></div>
    <?php else: ?>
        <p>Pídele al dueño del negocio que lo agregue en <strong>Mi plan</strong>.</p>
    <?php endif; ?>
</section>
