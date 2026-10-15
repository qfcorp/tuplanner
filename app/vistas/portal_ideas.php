<?php
use TuPlanner\{Cotizaciones, Inspiracion, Planificacion};
/**
 * Tablero de ideas del cliente.
 * @var array $ev @var array $negocio @var string $token @var bool $activo @var array $ideas @var array $paleta @var int $propias @var string $primerNombre
 */
$logo = $negocio['logo'] ? url('imagen_publica', ['tipo' => 'logo', 's' => $negocio['slug']]) : null;
?>
<header class="publico-cabeza cb-portal-cabeza">
    <?php if ($logo): ?><img class="pub-logo cb-logo" src="<?= e($logo) ?>" alt="Logo de <?= e($negocio['nombre']) ?>">
    <?php else: ?><span class="marca-sello" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($negocio['nombre'], 0, 1))) ?></span><?php endif; ?>
    <div><h1><?= e($negocio['nombre']) ?></h1><p>Portal de tu evento</p></div>
</header>

<p class="pt-volver"><a href="<?= e(url('portal', ['t' => $token])) ?>">← Volver a mi evento</a></p>

<section class="pub-hero pt-hero">
    <p class="pt-hero-saludo">Hola, <?= e($primerNombre) ?></p>
    <h2 class="pt-hero-titulo">Ideas para tu evento</h2>
    <?php if ($ev['tematica']): ?><p class="pt-hero-texto">Temática: <strong><?= e($ev['tematica']) ?></strong></p><?php endif; ?>
    <?php if ($paleta): ?><ul class="id-paleta id-paleta-hero" aria-label="Colores de tu evento"><?php foreach ($paleta as $c): ?><li style="background:<?= e($c) ?>" title="<?= e($c) ?>"><span class="sr-only"><?= e($c) ?></span></li><?php endforeach; ?></ul><?php endif; ?>
</section>

<?php if ($activo): ?>
<section class="bloque" id="subir">
    <h2>Sube lo que te gusta</h2>
    <p class="suave">Fotos de Pinterest, Instagram o tomadas por ti: decoración, flores, vestido, pastel, invitaciones… (puedes subir hasta <?= Inspiracion::MAX_FOTOS_CLIENTE ?>; llevas <?= (int) $propias ?>).</p>
    <form method="post" action="<?= e(url('portal_ideas', ['t' => $token])) ?>" enctype="multipart/form-data">
        <?= campo_csrf() ?><input type="hidden" name="accion" value="subir">
        <div class="campo"><label for="pi-fotos">Fotos</label><input type="file" id="pi-fotos" name="fotos[]" accept="image/*" multiple required></div>
        <div class="campo"><label for="pi-cat">¿De qué es?</label>
            <select id="pi-cat" name="categoria"><?php foreach (Cotizaciones::CATEGORIAS as $k => $t): if (in_array($k, ['honorarios', 'personal', 'transmision'], true)) continue; ?><option value="<?= e($k) ?>" <?= $k === 'decoracion' ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select></div>
        <div class="campo"><label for="pi-nota">¿Qué te gusta de esta foto? <small>(opcional)</small></label><input type="text" id="pi-nota" name="nota" maxlength="300" placeholder="Ej. los colores de las flores"></div>
        <button class="boton boton-ancho" type="submit">Subir fotos</button>
    </form>
</section>
<?php endif; ?>

<section class="bloque" id="ideas">
    <h2>Tablero <small class="suave">(<?= count($ideas) ?>)</small></h2>
    <?php if (!$ideas): ?>
        <p class="vacio">Todavía no hay fotos. ¡Sube la primera!</p>
    <?php else: ?>
    <ul class="id-tablero">
        <?php foreach ($ideas as $i): $iid = (int) $i['id']; $actual = $i['aprobada'] === true ? 'si' : ($i['aprobada'] === false ? 'no' : ''); ?>
        <li class="id-foto <?= $actual === 'si' ? 'id-si' : ($actual === 'no' ? 'id-no' : '') ?>" id="idea-<?= $iid ?>">
            <a href="<?= e(url('portal_ideas', ['t' => $token, 'foto' => $iid])) ?>" target="_blank" rel="noopener">
                <img src="<?= e(url('portal_ideas', ['t' => $token, 'foto' => $iid])) ?>" alt="<?= e($i['nota'] ?: (Cotizaciones::CATEGORIAS[$i['categoria']] ?? 'Idea')) ?>" loading="lazy" width="300" height="300">
            </a>
            <div class="id-pie">
                <small><?= Planificacion::ICONOS[$i['categoria']] ?? '' ?> <?= e(Cotizaciones::CATEGORIAS[$i['categoria']] ?? $i['categoria']) ?> · <?= $i['origen'] === 'cliente' ? 'tuya' : 'de ' . e($negocio['nombre']) ?>
                    <?= $actual === 'si' ? ' · ❤️ me encanta' : ($actual === 'no' ? ' · no va' : '') ?></small>
                <?php if ($i['nota']): ?><p><?= e($i['nota']) ?></p><?php endif; ?>
                <?php if ($activo): ?>
                <div class="id-botones">
                    <?php foreach (['si' => '❤️ Me encanta', 'no' => '✕ No va', '' => 'Sin decidir'] as $val => $txt): if ($val === $actual) continue; ?>
                    <form method="post" action="<?= e(url('portal_ideas', ['t' => $token])) ?>">
                        <?= campo_csrf() ?><input type="hidden" name="accion" value="marcar"><input type="hidden" name="idea_id" value="<?= $iid ?>"><input type="hidden" name="valor" value="<?= e($val) ?>">
                        <button class="boton boton-claro boton-chico" type="submit"><?= e($txt) ?></button>
                    </form>
                    <?php endforeach; ?>
                    <?php if ($i['origen'] === 'cliente'): ?>
                    <form method="post" action="<?= e(url('portal_ideas', ['t' => $token])) ?>">
                        <?= campo_csrf() ?><input type="hidden" name="accion" value="borrar"><input type="hidden" name="idea_id" value="<?= $iid ?>">
                        <button class="boton boton-claro boton-chico" type="submit">Borrar</button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</section>
