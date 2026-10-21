<?php
use TuPlanner\Cotizaciones;
/** Leer la cotización de un proveedor (Asistente IA). @var array $ev @var ?array $lectura @var ?string $aviso @var ?string $error @var string $aMano
 *  @var string $nota @var array $proveedores */
$enlazar = fn(string $t) => str_replace(e($aMano), '<a href="' . e($aMano) . '">' . e($aMano) . '</a>', e($t));
?>
<p><a href="<?= e(url('evento_plan', ['id' => $ev['id']])) ?>#presupuesto">← Presupuesto de <?= e($ev['nombre']) ?></a></p>
<div class="cabecera">
    <div><h1>✨ Leer cotización de proveedor</h1>
        <p>Suba la foto (o el PDF) de la cotización que le mandó el proveedor y le proponemos las partidas del presupuesto. Usted revisa y agrega.</p></div>
</div>

<?php if ($error): ?><p class="aviso aviso-error" role="alert"><?= e($error) ?></p><?php endif; ?>
<?php if ($aviso): ?><p class="aviso aviso-alerta" role="status" id="ia-aviso"><?= $enlazar($aviso) ?></p><?php endif; ?>

<?php if (!$lectura || !$lectura['partidas']): ?>
<section class="bloque">
    <form method="post" action="<?= e(url('ia_proveedor', ['id' => $ev['id']])) ?>" enctype="multipart/form-data" id="form-ia-proveedor">
        <?= campo_csrf() ?><input type="hidden" name="accion" value="leer">
        <div class="campo"><label for="ia-archivo"><strong>Foto o PDF de la cotización</strong></label>
            <input id="ia-archivo" name="archivo" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" required>
            <small>Hasta 5 MB. Si es un PDF de varias hojas, mejor una foto de la hoja con los valores.</small></div>
        <div class="campo"><label for="ia-nota">Nota (opcional)</label>
            <input id="ia-nota" name="nota" maxlength="500" value="<?= e($nota) ?>" placeholder="Ej.: es del florista, solo la decoración de la iglesia"></div>
        <button class="boton" type="submit">Leer la cotización</button>
        <a class="boton boton-claro" href="<?= e($aMano) ?>">Agregar a mano</a>
    </form>
</section>
<?php else: ?>
<form method="post" action="<?= e(url('ia_proveedor', ['id' => $ev['id']])) ?>" id="form-ia-partidas">
    <?= campo_csrf() ?><input type="hidden" name="accion" value="guardar">
    <p class="aviso aviso-alerta" role="status">Borrador: revise los valores antes de agregarlos. Quedan como «Pidiendo precios».</p>
    <section class="bloque">
        <div class="fila-campos">
            <div class="campo"><label for="ia-prov">Proveedor</label>
                <input id="ia-prov" name="proveedor_nombre" maxlength="150" value="<?= e($lectura['proveedor']) ?>"></div>
            <div class="campo"><label for="ia-prov-id">De tu lista</label>
                <select id="ia-prov-id" name="proveedor_id"><option value="">— No está en mi lista —</option>
                    <?php foreach ($proveedores as $pid => $pn): ?><option value="<?= (int) $pid ?>" <?= (int) ($lectura['proveedor_id'] ?? 0) === (int) $pid ? 'selected' : '' ?>><?= e($pn) ?></option><?php endforeach; ?>
                </select></div>
        </div>
        <?php if (!empty($lectura['notas'])): ?><p class="suave"><?= e($lectura['notas']) ?></p><?php endif; ?>
        <div class="tabla-scroll"><table class="tabla" id="ia-partidas">
            <thead><tr><th scope="col">Agregar</th><th scope="col">Categoría</th><th scope="col">Qué es</th><th scope="col" class="der">Estimado ($)</th></tr></thead>
            <tbody>
            <?php foreach ($lectura['partidas'] as $i => $p): ?>
                <tr>
                    <td><input type="checkbox" name="partida[<?= $i ?>][usar]" value="1" <?= ($p['usar'] ?? true) ? 'checked' : '' ?> aria-label="Agregar <?= e($p['concepto']) ?>"></td>
                    <td><select name="partida[<?= $i ?>][categoria]" aria-label="Categoría"><?php foreach (Cotizaciones::CATEGORIAS as $k => $t): ?><option value="<?= e($k) ?>" <?= $p['categoria'] === $k ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select></td>
                    <td><input name="partida[<?= $i ?>][concepto]" maxlength="150" value="<?= e($p['concepto']) ?>" aria-label="Qué es"></td>
                    <td class="der"><input name="partida[<?= $i ?>][estimado]" inputmode="decimal" size="9" value="<?= e(is_float($p['estimado']) ? number_format($p['estimado'], 2, '.', '') : $p['estimado']) ?>" aria-label="Estimado"></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </section>
    <div class="acciones">
        <button class="boton" type="submit">Agregar al presupuesto</button>
        <a class="boton boton-claro" href="<?= e(url('ia_proveedor', ['id' => $ev['id']])) ?>">Leer otra</a>
    </div>
</form>
<?php endif; ?>
