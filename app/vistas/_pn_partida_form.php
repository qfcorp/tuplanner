<?php
use TuPlanner\{Cotizaciones, Planificacion};
/**
 * Formulario de una partida del presupuesto (nueva o para editar).
 * @var array $ev @var array $proveedores @var float $comisionDefecto @var ?array $pf (partida a editar o null) @var string $pfId (prefijo de ids)
 */
$num = fn($v) => $v === null || $v === '' ? '' : number_format((float) $v, 2, ',', '');
$val = fn(string $k, $def = '') => $pf[$k] ?? $def;
?>
<form method="post" action="<?= e(url('evento_plan', ['id' => $ev['id']])) ?>" class="pn-form" novalidate>
    <?= campo_csrf() ?>
    <input type="hidden" name="accion" value="partida">
    <?php if ($pf): ?><input type="hidden" name="partida_id" value="<?= (int) $pf['id'] ?>"><?php endif; ?>
    <div class="rejilla-form">
        <div class="campo">
            <label for="<?= $pfId ?>-cat">Categoría</label>
            <select id="<?= $pfId ?>-cat" name="categoria">
                <?php foreach (Cotizaciones::CATEGORIAS as $k => $t): if ($k === 'honorarios') continue; ?>
                    <option value="<?= e($k) ?>" <?= $val('categoria', 'otros') === $k ? 'selected' : '' ?>><?= Planificacion::ICONOS[$k] ?? '' ?> <?= e($t) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo">
            <label for="<?= $pfId ?>-con">¿Qué es?</label>
            <input type="text" id="<?= $pfId ?>-con" name="concepto" value="<?= e($val('concepto')) ?>" maxlength="150" required placeholder="Ej. Fotografía y video">
        </div>
        <div class="campo">
            <label for="<?= $pfId ?>-prov">Proveedor <small>(de tu lista)</small></label>
            <select id="<?= $pfId ?>-prov" name="proveedor_id">
                <option value="">— Sin elegir o escrito abajo —</option>
                <?php foreach ($proveedores as $pr): ?>
                    <option value="<?= (int) $pr['id'] ?>" <?= (int) $val('proveedor_id', 0) === (int) $pr['id'] ? 'selected' : '' ?>><?= e($pr['nombre']) ?> · <?= e(Cotizaciones::CATEGORIAS[$pr['servicio']] ?? $pr['servicio']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo">
            <label for="<?= $pfId ?>-provn">…o escribe el proveedor</label>
            <input type="text" id="<?= $pfId ?>-provn" name="proveedor_nombre" value="<?= e($val('proveedor_id') ? '' : $val('proveedor_nombre')) ?>" maxlength="150" placeholder="Ej. Foto Estudio Luz">
        </div>
        <div class="campo">
            <label for="<?= $pfId ?>-est">Estado</label>
            <select id="<?= $pfId ?>-est" name="estado">
                <?php foreach (Planificacion::ESTADOS as $k => $t): ?><option value="<?= e($k) ?>" <?= $val('estado', 'idea') === $k ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="campo">
            <label for="<?= $pfId ?>-paga">¿Quién le paga al proveedor?</label>
            <select id="<?= $pfId ?>-paga" name="paga">
                <?php foreach (Planificacion::PAGA as $k => $t): ?><option value="<?= e($k) ?>" <?= $val('paga', 'cliente') === $k ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
            </select>
            <small>"Va en nuestro contrato" = tú lo revendes y lo cobras en tu cotización.</small>
        </div>
        <div class="campo">
            <label for="<?= $pfId ?>-estim">Estimado ($)</label>
            <input type="text" inputmode="decimal" id="<?= $pfId ?>-estim" name="estimado" value="<?= e($num($val('estimado', ''))) ?>" placeholder="0,00">
        </div>
        <div class="campo">
            <label for="<?= $pfId ?>-contr">Precio contratado ($) <small>(cuando ya cerraste)</small></label>
            <input type="text" inputmode="decimal" id="<?= $pfId ?>-contr" name="contratado" value="<?= e($num($val('contratado', ''))) ?>" placeholder="Vacío si todavía no">
        </div>
        <div class="campo">
            <label for="<?= $pfId ?>-vence">Pagarle hasta <small>(opcional)</small></label>
            <input type="date" id="<?= $pfId ?>-vence" name="vence_pago" value="<?= e($val('vence_pago', '')) ?>">
        </div>
        <div class="campo">
            <label for="<?= $pfId ?>-cpct">Tu comisión (%) <small>(la paga el proveedor)</small></label>
            <input type="text" inputmode="decimal" id="<?= $pfId ?>-cpct" name="comision_pct" value="<?= e($num($val('comision_pct', $pf ? 0 : $comisionDefecto))) ?>">
        </div>
        <div class="campo">
            <label for="<?= $pfId ?>-cmon">…o comisión fija ($)</label>
            <input type="text" inputmode="decimal" id="<?= $pfId ?>-cmon" name="comision_monto" value="<?= e($num($val('comision_monto', ''))) ?>" placeholder="Vacío = se calcula con el %">
        </div>
        <div class="campo ancho">
            <label for="<?= $pfId ?>-notas">Notas <small>(opcional)</small></label>
            <textarea id="<?= $pfId ?>-notas" name="notas" rows="2" maxlength="500" placeholder="Qué incluye, horas, condiciones…"><?= e($val('notas', '')) ?></textarea>
        </div>
    </div>
    <input type="hidden" name="visible_cliente" value="0">
    <label class="pn-check"><input type="checkbox" name="visible_cliente" value="1" <?= !$pf || $pf['visible_cliente'] ? 'checked' : '' ?>> El cliente la ve en su portal</label>
    <button class="boton" type="submit"><?= $pf ? 'Guardar cambios' : 'Agregar al presupuesto' ?></button>
</form>
