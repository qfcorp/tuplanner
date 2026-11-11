<div class="acceso-marca">
    <div>
        <h1>TuPlanner</h1>
        <p>Bodas, quince años y eventos: tareas, presupuesto, proveedores y cobros</p>
    </div>
</div>
<form method="post" class="bloque tk-tarjeta" novalidate>
    <?= campo_csrf() ?>
    <h2>Entrar</h2>
    <?php if ($error): ?><p class="aviso aviso-error" role="alert"><?= e($error) ?></p><?php endif; ?>
    <div class="campo tk-campo">
        <label for="email">Correo</label>
        <input type="email" id="email" name="email" value="<?= e($email) ?>" autocomplete="email" required autofocus>
    </div>
    <div class="campo tk-campo">
        <label for="clave">Contraseña</label>
        <input type="password" id="clave" name="clave" autocomplete="current-password" required>
    </div>
    <button data-ico="flecha" class="boton tk-boton boton-ancho" type="submit">Entrar</button>
</form>
<p>¿Todavía no tienes cuenta? <a href="<?= e(url('registro')) ?>">Solicitar demo</a></p>
<p><small><a href="https://tukansoftware.com/terminos.html" target="_blank" rel="noopener">Términos y condiciones</a> · <a href="<?= e(url('privacidad')) ?>">Política de privacidad</a></small></p>
