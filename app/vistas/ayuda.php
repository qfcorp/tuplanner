<?php
use TuPlanner\Ia;
/** Ayuda (todos los planes). @var string $pregunta @var ?array $respuesta @var ?string $error */
$waAyuda = 'https://wa.me/593983662181?text=' . rawurlencode('Hola, necesito ayuda con TuPlanner' . ($pregunta !== '' ? ': ' . $pregunta : ''));
?>
<div class="cabecera tk-titulo-pagina">
    <div><h1>Ayuda</h1><p>¿En qué le ayudamos? Pregunte cómo hacer algo en TuPlanner y le respondemos al instante con el manual.</p></div>
</div>

<section class="bloque tk-tarjeta ia-ayuda" aria-labelledby="ia-t-pregunta">
    <form method="post" action="<?= e(url('ayuda')) ?>" id="form-ayuda">
        <?= campo_csrf() ?>
        <div class="campo tk-campo">
            <label for="pregunta" id="ia-t-pregunta"><strong>¿Qué necesita hacer?</strong></label>
            <textarea id="pregunta" name="pregunta" rows="3" maxlength="<?= Ia::MAX_PREGUNTA ?>" required
                      placeholder="¿Cómo registro un pago?"><?= e($pregunta) ?></textarea>
            <small>Escriba su pregunta (máximo <?= Ia::MAX_PREGUNTA ?> letras).</small>
        </div>
        <button data-ico="ayuda" class="boton tk-boton" type="submit">Preguntar</button>
    </form>
</section>

<?php if ($error): ?><p class="aviso aviso-error" role="alert"><?= e($error) ?></p><?php endif; ?>

<?php if ($respuesta): ?>
    <section class="bloque tk-tarjeta ia-respuesta" aria-labelledby="ia-t-respuesta" aria-live="polite">
        <h2 id="ia-t-respuesta">Respuesta</h2>
        <div class="ia-texto"><?= Ia::html((string) $respuesta['respuesta']) ?></div>
        <p class="ia-pie">¿Le sirvió? Si no, <a href="<?= e($waAyuda) ?>" target="_blank" rel="noopener">escríbanos por WhatsApp 098 366 2181</a>.</p>
    </section>
<?php endif; ?>

<section class="bloque tk-tarjeta" id="ayuda-descargas" aria-labelledby="t-descargas">
    <h2 id="t-descargas">Manual y primeros pasos</h2>
    <p class="suave" style="margin:0 0 8px">Descárguelos para leerlos con calma o imprimirlos.</p>
    <div class="acciones tk-acciones">
        <a data-ico="descargar" class="boton tk-boton boton-chico chico" href="/manual.pdf" download="Manual_TuPlanner.pdf">Descargar el manual</a>
        <a data-ico="descargar" class="boton tk-boton boton-claro secundario boton-chico chico" href="/primeros_pasos.pdf" download="TuPlanner_Primeros_Pasos.pdf">Descargar primeros pasos</a>
    </div>
</section>

<section class="bloque tk-tarjeta ia-consejos">
    <h2>Para preguntar mejor</h2>
    <ul>
        <li>Diga qué quiere hacer: «¿Cómo envío la cotización por WhatsApp?», «¿Cómo separo una fecha?».</li>
        <li>Una pregunta a la vez.</li>
        <li>¿Algo urgente o que no funciona? <a href="<?= e($waAyuda) ?>" target="_blank" rel="noopener">Escríbanos por WhatsApp 098 366 2181</a>.</li>
    </ul>
</section>
