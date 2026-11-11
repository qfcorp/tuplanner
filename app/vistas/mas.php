<?php
/** «Más»: todas las secciones, con los mismos grupos y emojis del menú de la izquierda (app/menu.php). */
$masGrupos = tk_menu($u);
?>
<div class="tk-titulo-pagina cabecera"><div><h1>Más</h1><p>Todas las secciones de TuPlanner</p></div></div>
<?php foreach ($masGrupos as [$masTitulo, $masOpciones]): ?>
<h2><?= e($masTitulo) ?></h2>
<nav class="te-mas lista-mas" aria-label="<?= e($masTitulo) ?>">
    <?php foreach ($masOpciones as [$masRuta, $masTexto, $masEmoji, $masAyuda]): ?>
    <a href="<?= e(url($masRuta)) ?>"><span class="tk-ico" aria-hidden="true"><?= $masEmoji ?></span><span><?= e($masTexto) ?><?php if ($masAyuda !== ''): ?><small><?= e($masAyuda) ?></small><?php endif; ?></span></a>
    <?php endforeach; ?>
</nav>
<?php endforeach; ?>
<nav class="te-mas lista-mas" aria-label="Salir"><a href="<?= e(url('salir')) ?>"><span class="tk-ico" aria-hidden="true">🚪</span><span>Cerrar sesión</span></a></nav>
