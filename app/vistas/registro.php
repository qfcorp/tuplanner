<?php /** Pedido de demo (no hay registro abierto). @var array $errores @var array $porCampo @var array $d @var bool $listo @var int $diasDemo @var array $catalogo */
use TuPlanner\Modulos;
$waTukan = 'https://wa.me/' . whatsapp_ventas() . '?text=' . rawurlencode('Hola, quiero una demo de TuPlanner' . ($d['nombre_comercial'] !== '' ? ' para ' . $d['nombre_comercial'] : '') . '.');
?>
<div class="acceso-marca">
    <span class="marca-sello" aria-hidden="true">TP</span>
    <div>
        <h1>Solicitar demo</h1>
        <p>Prueba TuPlanner <?= (int) $diasDemo ?> días: la clave te llega al correo</p>
    </div>
</div>
<?php if ($listo): ?>
<section class="bloque" role="status" id="demo-gracias">
    <h2>¡Recibimos tu pedido!</h2>
    <p>¡Listo! Estamos preparando tu demo: toma entre 5 y 10 minutos aproximadamente. Cuando esté lista te llega al correo tu clave, el manual y la guía de primeros pasos. Revisa también la carpeta de spam.</p>
    <p class="suave">Gracias, <?= e(explode(' ', $d['nombre'])[0]) ?>. La demo de <strong><?= e($d['nombre_comercial']) ?></strong> dura <?= (int) $diasDemo ?> días y la clave va a <strong><?= e($d['email']) ?></strong>.</p>
    <div class="acciones"><a class="boton boton-wa" href="<?= e($waTukan) ?>" target="_blank" rel="noopener">¿No te llegó? Escríbenos por WhatsApp</a></div>
</section>
<p><a href="<?= e(url('login')) ?>">Volver a Entrar</a></p>
<?php else:
    // Mensaje junto a cada campo (y aria-invalid) cuando el servidor lo rechaza
    $err = fn(string $k) => isset($porCampo[$k]) ? '<small class="campo-error" id="err-' . $k . '" role="alert" style="color:#7E2A23;display:block">' . e($porCampo[$k]) . '</small>' : '';
    $inv = fn(string $k) => isset($porCampo[$k]) ? ' aria-invalid="true" aria-describedby="err-' . $k . '"' : '';
?>
<form method="post" class="bloque" id="form-demo">
    <?= campo_csrf() ?>
    <?php if ($errores): ?>
        <div class="aviso aviso-error" role="alert"><?php foreach ($errores as $e1): ?><div><?= e($e1) ?></div><?php endforeach; ?></div>
    <?php endif; ?>
    <p class="suave" style="margin-top:0">Llena <strong>todos</strong> los datos y te armamos la demo con datos de ejemplo. <strong>La demo dura <?= (int) $diasDemo ?> días.</strong> Preparar tu demo toma entre 5 y 10 minutos aproximadamente.</p>
    <div class="fila-campos">
        <div class="campo">
            <label for="nombre">Tu nombre y apellido</label>
            <input type="text" id="nombre" name="nombre" value="<?= e($d['nombre']) ?>" minlength="3" maxlength="80" autocomplete="name" required autofocus<?= $inv('nombre') ?>>
            <?= $err('nombre') ?>
        </div>
        <div class="campo">
            <label for="nombre_comercial">Nombre de tu negocio de eventos <small>(ej. Ana Pérez Wedding Planner)</small></label>
            <input type="text" id="nombre_comercial" name="nombre_comercial" value="<?= e($d['nombre_comercial']) ?>" minlength="3" maxlength="120" autocomplete="organization" required<?= $inv('nombre_comercial') ?>>
            <?= $err('nombre_comercial') ?>
        </div>
    </div>
    <div class="fila-campos">
        <div class="campo">
            <label for="celular">Celular / WhatsApp</label>
            <input type="tel" id="celular" name="celular" value="<?= e($d['celular']) ?>" maxlength="20" placeholder="0991234567" pattern="\+?[0-9 \-]{9,20}" autocomplete="mobile tel" required<?= $inv('celular') ?>>
            <?= $err('celular') ?>
        </div>
        <div class="campo">
            <label for="ciudad">Ciudad</label>
            <input type="text" id="ciudad" name="ciudad" value="<?= e($d['ciudad']) ?>" minlength="2" maxlength="80" placeholder="Quito" autocomplete="address-level2" required<?= $inv('ciudad') ?>>
            <?= $err('ciudad') ?>
        </div>
    </div>
    <div class="campo">
        <label for="direccion">Dirección de tu negocio</label>
        <input type="text" id="direccion" name="direccion" value="<?= e($d['direccion']) ?>" minlength="5" maxlength="250" autocomplete="street-address" required<?= $inv('direccion') ?>>
        <?= $err('direccion') ?>
    </div>
    <div class="campo">
        <label for="email">Correo</label>
        <input type="email" id="email" name="email" value="<?= e($d['email']) ?>" maxlength="120" autocomplete="email" required<?= $inv('email') ?>>
        <small>Te enviaremos la clave de tu demo a este correo: escríbelo bien.</small>
        <?= $err('email') ?>
    </div>
    <div class="campo">
        <label for="necesita">¿Qué necesitas? <small>(opcional)</small></label>
        <textarea id="necesita" name="necesita" rows="3" maxlength="1000" placeholder="Ej.: organizamos unas 4 bodas al mes y queremos llevar tareas, presupuesto y proveedores en un solo lugar."><?= e($d['necesita']) ?></textarea>
        <?= $err('necesita') ?>
    </div>
    <fieldset class="campo mod-elegir" style="border:0;padding:0;margin:0 0 16px">
        <legend class="etiqueta" style="margin-bottom:6px">¿Qué módulos te interesan? <small>(opcional)</small></legend>
        <div class="mod-elegir-lista">
            <?php foreach ($catalogo as $m): ?>
                <label class="mod-elegir-item"><input type="checkbox" name="modulos[]" value="<?= e($m['id']) ?>" <?= in_array($m['id'], $d['modulos'], true) ? 'checked' : '' ?>>
                    <span><span aria-hidden="true"><?= e($m['icono']) ?></span> <?= e($m['nombre']) ?></span></label>
            <?php endforeach; ?>
        </div>
        <p class="g-nota" style="margin-top:6px">Todos los planes traen la base: <?= e(mb_strtolower(implode(', ', array_slice(Modulos::BASE, 0, 4)))) ?>…</p>
    </fieldset>
    <div class="trampa" aria-hidden="true"><label>Sitio web <input type="text" name="sitio_web" tabindex="-1" autocomplete="off"></label></div>
    <button class="boton boton-ancho" type="submit">Solicitar demo</button>
</form>
<p>¿Ya tienes cuenta? <a href="<?= e(url('login')) ?>">Entrar</a> · <a href="<?= e($waTukan) ?>" target="_blank" rel="noopener">Pedir demo por WhatsApp</a></p>
<?php endif; ?>
