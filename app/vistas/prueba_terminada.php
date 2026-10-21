<?php /** @var string $estadoCuenta @var array $guardado */
$hasta = !empty($u['activo_hasta']) ? (new DateTimeImmutable($u['activo_hasta']))->format('d/m/Y') : '—';
$textos = [
    'prueba_vencida' => ['Tu demo terminó — contáctanos para contratar', 'Eliges tu plan y tus módulos y sigues justo donde lo dejaste.', 'terminó mi demo'],
    'vencido'        => ['Tu plan venció', "Tu plan estaba pagado hasta el $hasta. Renuévalo y sigues donde lo dejaste.", 'venció mi plan'],
    'suspendido'     => ['Tu cuenta está pausada', 'Escríbenos para reactivarla.', 'mi cuenta está pausada'],
    'cancelado'      => ['Tu cuenta está cerrada', 'Si quieres volver, escríbenos y la reactivamos con todos tus datos.', 'quiero reactivar mi cuenta'],
];
[$titulo, $texto, $motivo] = $textos[$estadoCuenta] ?? $textos['prueba_vencida'];
$wa = 'https://wa.me/' . whatsapp_ventas() . '?text=' . rawurlencode("Hola, $motivo de TuPlanner (" . $u['negocio'] . ', ' . $u['email'] . ') y quiero contratar un plan.');
$pausada = $estadoCuenta === 'suspendido';
if ($pausada) {   // Anexo A: cuenta pausada por Tukán, con el motivo (genérico en revisión y uso indebido) y el WhatsApp de Tukán
    $texto = \TuPlanner\Papelera::textoPausa($u['pausa_tipo'] ?? null, $u['pausa_motivo'] ?? null);
    $wa = 'https://wa.me/' . whatsapp_ventas() . '?text=' . rawurlencode('Hola, mi cuenta de TuPlanner está pausada (' . $u['negocio'] . ', ' . $u['email'] . '). ¿Me ayudan a reactivarla?');
}
?>
<section class="bloque" style="max-width:600px">
    <h1><?= e($titulo) ?></h1>
    <?php if ($pausada): ?>
        <p class="aviso-pausa"><strong>Motivo:</strong> <?= e($texto) ?></p>
        <p>Mientras está pausada no puedes trabajar en TuPlanner y tus páginas públicas dicen "temporalmente no disponible".</p>
    <?php else: ?>
        <p><?= e($texto) ?></p>
    <?php endif; ?>
    <div class="g-ayuda">
        <strong>Todo está guardado:</strong>
        <?= (int) $guardado['eventos'] ?> eventos (<?= (int) $guardado['proximos'] ?> próximos), <?= (int) $guardado['clientes'] ?> clientes, cotizaciones, contratos y pagos.
        Nada se borra.
    </div>
    <?php if ($pausada): ?>
        <div class="acciones">
            <a class="boton boton-wa" href="<?= e($wa) ?>" target="_blank" rel="noopener">Escribir a Tukán por WhatsApp (+<?= e(whatsapp_ventas()) ?>)</a>
        </div>
    <?php elseif (es_admin_total($u)): ?>
        <div class="acciones">
            <a class="boton boton-wa" href="<?= e($wa) ?>" target="_blank" rel="noopener">Contáctanos para contratar</a>
            <a class="boton boton-claro" href="<?= e(url('planes')) ?>">Ver mi plan y pedir cotización</a>
        </div>
    <?php else: ?>
        <p>Pídele al dueño del negocio que renueve el plan.</p>
    <?php endif; ?>
    <p style="margin-top:14px"><a href="<?= e(url('salir')) ?>">Cerrar sesión</a></p>
</section>
