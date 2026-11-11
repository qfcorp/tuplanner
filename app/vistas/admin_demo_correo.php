<?php
/** Resultado de «Enviar por correo» de la demo. @var ?array $negocio @var ?array $correoDemo @var ?string $caducado */
?>
<?php if ($caducado): ?>
    <p><a href="<?= e(url('admin_negocios')) ?>">← Todos los negocios</a></p>
    <section class="bloque tk-tarjeta" role="alert"><h1>Envío caducado</h1><p><?= e($caducado) ?></p></section>
<?php else: ?>
    <p><a href="<?= e(url('admin_negocio', ['id' => $negocio['id']])) ?>">← Volver a <?= e($negocio['nombre']) ?></a></p>
    <div class="cabecera tk-titulo-pagina"><div><h1>Demo lista · <?= e($negocio['nombre']) ?></h1>
        <p class="suave">La contraseña se mostró una sola vez en la ficha y no se guarda.</p></div></div>
    <section class="bloque tk-tarjeta"><?php require RAIZ . '/app/vistas/_correo_demo.php'; ?></section>
<?php endif; ?>
