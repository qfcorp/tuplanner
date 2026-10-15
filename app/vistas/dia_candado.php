<?php /** Pestaña del plan Quinta vista desde el plan Salón: cabecera del evento + candado. @var string $cab @var string $que @var string $explicacion */ ?>
<?= $cab ?>
<section class="bloque dia-candado">
    <p class="candado" style="font-size:1rem;margin-bottom:10px"><?= e($que) ?> es del plan Quinta.</p>
    <p><?= e($explicacion) ?></p>
    <?php if (es_dueno($u)): ?>
        <a class="boton" href="<?= e(url('planes')) ?>">Ver el plan Quinta</a>
    <?php else: ?>
        <p class="suave"><small>Pídele al dueño de la cuenta que cambie al plan Quinta.</small></p>
    <?php endif; ?>
</section>
