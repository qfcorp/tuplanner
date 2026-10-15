<div class="cabecera"><h1><?= e($que) ?></h1></div>
<section class="bloque" style="max-width:640px">
    <p class="candado" style="font-size:1rem;margin-bottom:10px"><?= e($que) ?> es del plan Quinta.</p>
    <p><?= e($explicacion) ?></p>
    <?php if ($u && es_dueno($u)): ?><a class="boton" href="<?= e(url('planes')) ?>">Ver el plan Quinta</a><?php endif; ?>
</section>
