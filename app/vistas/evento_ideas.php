<?php
use TuPlanner\{Cotizaciones, Inspiracion, Planificacion};
/**
 * Pestaña "Ideas" del evento.
 * @var array $ev @var array $ideas @var array $paleta
 */
require RAIZ . '/app/vistas/_evento_tabs.php';
$puede = puede_editar($u);
$colores = array_pad($paleta, Inspiracion::MAX_COLORES, '');
$sugeridos = ['#ffffff', '#f5e6d3', '#d4a857', '#a0526d', '#6b8f71', '#1f3a5f'];
$aprobadas = count(array_filter($ideas, fn($i) => $i['aprobada'] === true));
$delCliente = count(array_filter($ideas, fn($i) => $i['origen'] === 'cliente'));
?>
<section class="bloque" id="estilo" aria-labelledby="id-t-estilo">
    <h2 id="id-t-estilo">Temática y colores</h2>
    <?php if ($ev['tematica'] || $paleta): ?>
        <p class="id-tematica"><?= $ev['tematica'] ? e($ev['tematica']) : '<span class="suave">Sin temática escrita</span>' ?></p>
        <?php if ($paleta): ?><ul class="id-paleta" aria-label="Paleta de colores"><?php foreach ($paleta as $c): ?><li style="background:<?= e($c) ?>" title="<?= e($c) ?>"><span class="sr-only"><?= e($c) ?></span></li><?php endforeach; ?></ul><?php endif; ?>
    <?php else: ?>
        <p class="suave">Escribe la temática (ej. "Rústico con flores blancas y velas") y elige hasta <?= Inspiracion::MAX_COLORES ?> colores. El cliente los ve en su portal.</p>
    <?php endif; ?>
    <?php if ($puede): ?>
    <details class="pn-detalle" <?= !$ev['tematica'] && !$paleta ? 'open' : '' ?>>
        <summary>Cambiar temática y colores</summary>
        <form method="post" action="<?= e(url('evento_ideas', ['id' => $ev['id']])) ?>">
            <?= campo_csrf() ?><input type="hidden" name="accion" value="estilo">
            <div class="campo">
                <label for="id-tem">Temática</label>
                <input type="text" id="id-tem" name="tematica" value="<?= e((string) $ev['tematica']) ?>" maxlength="150" placeholder="Ej. Jardín romántico, tonos rosa antiguo y dorado">
            </div>
            <fieldset class="id-colores">
                <legend>Colores <small>(toca cada cuadro para elegir; deja en blanco los que no uses)</small></legend>
                <?php foreach ($colores as $i => $c): ?>
                    <label class="id-color">
                        <input type="color" name="colores[<?= $i ?>]" value="<?= e($c ?: $sugeridos[$i]) ?>" aria-label="Color <?= $i + 1 ?>">
                        <span><input type="checkbox" name="usar[<?= $i ?>]" value="1" <?= $c ? 'checked' : '' ?> aria-label="Usar el color <?= $i + 1 ?>"> Usar</span>
                    </label>
                <?php endforeach; ?>
            </fieldset>
            <button class="boton" type="submit">Guardar</button>
        </form>
    </details>
    <?php endif; ?>
</section>

<section class="bloque" id="ideas" aria-labelledby="id-t-ideas">
    <h2 id="id-t-ideas">Tablero de ideas <small class="suave"><?= count($ideas) ?> fotos<?= $aprobadas ? " · $aprobadas aprobadas" : '' ?><?= $delCliente ? " · $delCliente del cliente" : '' ?></small></h2>
    <p class="suave">Fotos de decoración, flores, vestido, pastel… Las ve el cliente en su portal y también puede subir las suyas y marcar ❤️ las que le encantan.</p>
    <?php if ($puede): ?>
    <form method="post" action="<?= e(url('evento_ideas', ['id' => $ev['id']])) ?>" enctype="multipart/form-data" class="pn-en-linea">
        <?= campo_csrf() ?><input type="hidden" name="accion" value="subir">
        <div class="campo"><label for="id-fotos">Fotos <small>(hasta 12 a la vez)</small></label><input type="file" id="id-fotos" name="fotos[]" accept="image/*" multiple required></div>
        <div class="campo"><label for="id-cat">Categoría</label>
            <select id="id-cat" name="categoria"><?php foreach (Cotizaciones::CATEGORIAS as $k => $t): if ($k === 'honorarios') continue; ?><option value="<?= e($k) ?>" <?= $k === 'decoracion' ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select></div>
        <div class="campo"><label for="id-nota">Nota <small>(opcional)</small></label><input type="text" id="id-nota" name="nota" maxlength="300" placeholder="Ej. Centro de mesa así, pero en blanco"></div>
        <button class="boton" type="submit">Subir</button>
    </form>
    <?php endif; ?>
    <?php if (!$ideas): ?>
        <p class="vacio">Todavía no hay fotos.</p>
    <?php else: ?>
    <ul class="id-tablero">
        <?php foreach ($ideas as $i): $iid = (int) $i['id']; ?>
        <li class="id-foto <?= $i['aprobada'] === true ? 'id-si' : ($i['aprobada'] === false ? 'id-no' : '') ?>" id="idea-<?= $iid ?>">
            <a href="<?= e(url('evento_ideas', ['id' => $ev['id'], 'foto' => $iid])) ?>" target="_blank" rel="noopener">
                <img src="<?= e(url('evento_ideas', ['id' => $ev['id'], 'foto' => $iid])) ?>" alt="<?= e($i['nota'] ?: (Cotizaciones::CATEGORIAS[$i['categoria']] ?? 'Idea')) ?>" loading="lazy" width="300" height="300">
            </a>
            <div class="id-pie">
                <small><?= Planificacion::ICONOS[$i['categoria']] ?? '' ?> <?= e(Cotizaciones::CATEGORIAS[$i['categoria']] ?? $i['categoria']) ?> · <?= $i['origen'] === 'cliente' ? 'del cliente' : 'tuya' ?>
                    <?= $i['aprobada'] === true ? ' · ❤️ aprobada' : ($i['aprobada'] === false ? ' · no va' : '') ?></small>
                <?php if ($i['nota']): ?><p><?= e($i['nota']) ?></p><?php endif; ?>
                <?php if ($puede): ?>
                <div class="id-botones">
                    <?php foreach (['si' => '❤️ Va', 'no' => '✕ No va', '' => 'Sin decidir'] as $val => $txt):
                        $actual = $i['aprobada'] === true ? 'si' : ($i['aprobada'] === false ? 'no' : ''); if ($val === $actual) continue; ?>
                    <form method="post" action="<?= e(url('evento_ideas', ['id' => $ev['id']])) ?>">
                        <?= campo_csrf() ?><input type="hidden" name="accion" value="marcar"><input type="hidden" name="idea_id" value="<?= $iid ?>"><input type="hidden" name="valor" value="<?= e($val) ?>">
                        <button class="boton boton-claro boton-chico" type="submit"><?= e($txt) ?></button>
                    </form>
                    <?php endforeach; ?>
                </div>
                <details class="pn-detalle">
                    <summary>Nota o borrar</summary>
                    <form method="post" action="<?= e(url('evento_ideas', ['id' => $ev['id']])) ?>">
                        <?= campo_csrf() ?><input type="hidden" name="accion" value="nota"><input type="hidden" name="idea_id" value="<?= $iid ?>">
                        <div class="campo"><label for="in-<?= $iid ?>">Nota</label><input type="text" id="in-<?= $iid ?>" name="nota" value="<?= e((string) $i['nota']) ?>" maxlength="300"></div>
                        <div class="campo"><label for="ic-<?= $iid ?>">Categoría</label><select id="ic-<?= $iid ?>" name="categoria"><?php foreach (Cotizaciones::CATEGORIAS as $k => $t): if ($k === 'honorarios') continue; ?><option value="<?= e($k) ?>" <?= $i['categoria'] === $k ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select></div>
                        <button class="boton boton-claro boton-chico" type="submit">Guardar</button>
                    </form>
                    <form method="post" action="<?= e(url('evento_ideas', ['id' => $ev['id']])) ?>">
                        <?= campo_csrf() ?><input type="hidden" name="accion" value="borrar"><input type="hidden" name="idea_id" value="<?= $iid ?>">
                        <button class="boton boton-peligro boton-chico" type="submit">Borrar foto</button>
                    </form>
                </details>
                <?php endif; ?>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</section>
