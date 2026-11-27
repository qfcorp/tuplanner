<?php
/** Campos País (selector, Ecuador por defecto) y Ciudad (opcional) de los datos del cliente. (27 nov 2026, regla «exterior = sin IVA»)
 *  Variables: $pcSinCiudad (opcional), $pcPais (país actual), $pcCiudad, $pcId (prefijo de los id y name: '' → «pais» y «ciudad»; 'cliente_' → «cliente_pais» y «cliente_ciudad»). */
$pcId = $pcId ?? '';
$pcSinCiudad = $pcSinCiudad ?? false;   // true: solo el país (la ciudad ya tiene su propio campo)
$pcPais = \TuPlanner\Paises::normalizar((string) ($pcPais ?? ''));
$pcLista = \TuPlanner\Paises::LISTA;
if (!in_array($pcPais, $pcLista, true)) $pcLista[] = $pcPais;
?>
<div class="campo tk-campo campo-pais">
    <label for="<?= e($pcId) ?>pais"><strong>País</strong> <small>(Ecuador por defecto)</small></label>
    <select id="<?= e($pcId) ?>pais" name="<?= e($pcId) ?>pais" data-pais>
        <?php foreach ($pcLista as $p): ?><option value="<?= e($p) ?>" <?= $pcPais === $p ? 'selected' : '' ?>><?= e($p) ?></option><?php endforeach; ?>
    </select>
</div>
<?php if (!$pcSinCiudad): ?>
<div class="campo tk-campo">
    <label for="<?= e($pcId) ?>ciudad">Ciudad <small>(opcional)</small></label>
    <input type="text" id="<?= e($pcId) ?>ciudad" name="<?= e($pcId) ?>ciudad" value="<?= e((string) ($pcCiudad ?? '')) ?>" maxlength="80">
</div>
<?php endif; ?>
