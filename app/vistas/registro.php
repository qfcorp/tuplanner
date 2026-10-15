<div class="acceso-marca">
    <span class="marca-sello" aria-hidden="true">TP</span>
    <div>
        <h1>Prueba TuPlanner gratis</h1>
        <p><?= (int) planes()->ajuste('dias_prueba') ?> días, sin tarjeta</p>
    </div>
</div>
<form method="post" class="bloque" novalidate>
    <?= campo_csrf() ?>
    <?php if ($errores): ?>
        <div class="aviso aviso-error" role="alert"><?php foreach ($errores as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
    <?php endif; ?>
    <div class="campo">
        <label for="negocio">Nombre de tu negocio de eventos <small>(ej. Ana Pérez Wedding Planner)</small></label>
        <input type="text" id="negocio" name="negocio" value="<?= e($d['negocio']) ?>" required autofocus>
    </div>
    <div class="fila-campos">
        <div class="campo">
            <label for="nombre">Tu nombre</label>
            <input type="text" id="nombre" name="nombre" value="<?= e($d['nombre']) ?>" autocomplete="name" required>
        </div>
        <div class="campo">
            <label for="telefono">Tu celular</label>
            <input type="tel" id="telefono" name="telefono" value="<?= e($d['telefono']) ?>" placeholder="0991234567" autocomplete="tel">
        </div>
    </div>
    <div class="fila-campos">
        <div class="campo">
            <label for="email">Correo</label>
            <input type="email" id="email" name="email" value="<?= e($d['email']) ?>" autocomplete="email" required>
        </div>
        <div class="campo">
            <label for="ciudad">Ciudad</label>
            <input type="text" id="ciudad" name="ciudad" value="<?= e($d['ciudad']) ?>">
        </div>
    </div>
    <div class="campo">
        <label for="clave">Contraseña <small>(mínimo 8 caracteres)</small></label>
        <input type="password" id="clave" name="clave" autocomplete="new-password" minlength="8" required>
    </div>
    <div class="trampa" aria-hidden="true"><label>Sitio web <input type="text" name="sitio_web" tabindex="-1" autocomplete="off"></label></div>
    <fieldset class="campo" style="border:0;padding:0;margin:0 0 16px">
        <legend class="etiqueta" style="margin-bottom:6px">¿Qué plan quieres probar?</legend>
        <div class="opciones">
            <label class="opcion">
                <input type="radio" name="plan" value="quinta" <?= $d['plan'] === 'quinta' ? 'checked' : '' ?>>
                <span><strong>Agencia · <?= precio_plan('quinta') ?> al mes</strong>
                    <small>Todo, para equipos: usuarios sin límite, invitados con QR, día del evento minuto a minuto, plano de mesas y boda en vivo.</small></span>
            </label>
            <label class="opcion">
                <input type="radio" name="plan" value="salon" <?= $d['plan'] === 'salon' ? 'checked' : '' ?>>
                <span><strong>Planner · <?= precio_plan('salon') ?> al mes</strong>
                    <small>Tareas por meses, presupuesto con proveedores, honorarios y comisiones, contratos firmados, cobros y portal del cliente. Hasta 3 usuarios.</small></span>
            </label>
        </div>
    </fieldset>
    <button class="boton boton-ancho" type="submit">Crear mi cuenta</button>
</form>
<p>¿Ya tienes cuenta? <a href="<?= e(url('login')) ?>">Entrar</a></p>
