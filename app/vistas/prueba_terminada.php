<?php /** @var string $estadoCuenta @var array $guardado */
$hasta = !empty($u['activo_hasta']) ? (new DateTimeImmutable($u['activo_hasta']))->format('d/m/Y') : '—';
$textos = [
    'prueba_vencida' => ['Tu prueba gratis terminó', 'Elige un plan para seguir usando TuPlanner justo donde lo dejaste.', 'terminó mi prueba'],
    'vencido'        => ['Tu plan venció', "Tu plan estaba pagado hasta el $hasta. Renuévalo y sigues donde lo dejaste.", 'venció mi plan'],
    'suspendido'     => ['Tu cuenta está pausada', 'Escríbenos para reactivarla.', 'mi cuenta está pausada'],
    'cancelado'      => ['Tu cuenta está cerrada', 'Si quieres volver, escríbenos y la reactivamos con todos tus datos.', 'quiero reactivar mi cuenta'],
];
[$titulo, $texto, $motivo] = $textos[$estadoCuenta] ?? $textos['prueba_vencida'];
$wa = 'https://wa.me/' . whatsapp_ventas() . '?text=' . rawurlencode("Hola, $motivo de TuPlanner (" . $u['negocio'] . ', ' . $u['email'] . ') y quiero contratar un plan.');
?>
<section class="bloque" style="max-width:600px">
    <h1><?= e($titulo) ?></h1>
    <p><?= e($texto) ?></p>
    <div class="g-ayuda">
        <strong>Todo está guardado:</strong>
        <?= (int) $guardado['eventos'] ?> eventos (<?= (int) $guardado['proximos'] ?> próximos), <?= (int) $guardado['clientes'] ?> clientes, cotizaciones, contratos y pagos.
        Nada se borra.
    </div>
    <?php if (es_dueno($u)): ?>
        <div class="acciones">
            <a class="boton" href="<?= e(url('planes')) ?>">Ver planes y precios</a>
            <a class="boton boton-wa" href="<?= e($wa) ?>" target="_blank" rel="noopener">Escribir por WhatsApp</a>
        </div>
    <?php else: ?>
        <p>Pídele al dueño del negocio que renueve el plan.</p>
    <?php endif; ?>
    <p style="margin-top:14px"><a href="<?= e(url('salir')) ?>">Cerrar sesión</a></p>
</section>
