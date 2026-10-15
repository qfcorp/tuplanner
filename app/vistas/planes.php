<?php
use TuPlanner\{PanelTukan, Planes};
/** @var array $lista @var array $precios @var string $estadoCuenta @var array $pagos @var array $uso */
$nombres = ['mensual' => 'Cada mes', 'semestral' => 'Cada 6 meses', 'anual' => 'Cada año'];
$wa = fn(string $plan, string $periodo, float $monto) => 'https://wa.me/' . whatsapp_ventas() . '?text=' . rawurlencode(
    "Hola, quiero contratar TuPlanner plan $plan ($periodo, " . dinero($monto) . ") para \"{$u['negocio']}\" ({$u['email']}). ¿Cómo hago el pago?");
$incluye = [
    'salon' => ['Calendario sin doble reserva (lo impide el sistema)', 'Cotizaciones con paquetes, IVA y descuento', 'Contrato que el cliente firma en su celular',
                'Plan de pagos, cuotas y recordatorios por WhatsApp', 'Página pública para recibir consultas', 'Reportes de ventas, cobros y ganancia', 'Avisos por Telegram'],
    'quinta' => ['Todo lo del plan Salón, sin límite de espacios ni usuarios', 'Portal del cliente: paga y sube comprobantes', 'Invitados con QR y control de ingreso en la puerta',
                 'Día del evento: cronograma, personal, proveedores', 'Mobiliario y menaje (lo que sale y lo que se daña)', 'Boda en vivo con QF Producciones'],
];
$estadoTxt = PanelTukan::ESTADOS[$estadoCuenta] ?? $estadoCuenta;
$hasta = $u['estado'] === 'prueba' ? $u['prueba_hasta'] : $u['activo_hasta'];
$ahorro = function (array $c, array $mensual): int {
    $normal = $mensual['monto'] * $c['meses_recibidos'];
    return $normal > 0 ? (int) round(($normal - $c['monto']) * 100 / $normal) : 0;
};
?>
<div class="cabecera">
    <div><h1>Mi plan</h1><p>Planes de TuPlanner para <?= e($u['negocio']) ?>.</p></div>
</div>

<section class="bloque g-estado-cuenta">
    <div>
        <p style="margin:0">Tu plan: <strong><?= $u['plan_codigo'] === 'quinta' ? 'Quinta' : 'Salón' ?></strong>
            <span class="etq est-<?= e($estadoCuenta) ?>"><?= e($estadoTxt) ?></span></p>
        <p class="suave" style="margin:4px 0 0">
            <?php if ($hasta): ?><?= $u['estado'] === 'prueba' ? 'Prueba gratis hasta el ' : 'Pagado hasta el ' ?><strong><?= e((new DateTimeImmutable($hasta))->format('d/m/Y')) ?></strong> · <?php endif; ?>
            <?= (int) $uso['espacios'] ?> espacios y <?= (int) $uso['usuarios'] ?> usuarios activos</p>
    </div>
    <a class="boton boton-wa" href="https://wa.me/<?= e(whatsapp_ventas()) ?>?text=<?= e(rawurlencode('Hola, tengo una pregunta sobre mi plan de TuPlanner (' . $u['negocio'] . ').')) ?>" target="_blank" rel="noopener">Preguntar por WhatsApp</a>
</section>

<div class="g-planes">
    <?php foreach ($lista as $pl):
        $cod = $pl['codigo'];
        $m = $precios[$cod]['mensual']; ?>
        <section class="bloque g-plan <?= $cod === 'quinta' ? 'recomendado' : '' ?>">
            <?php if ($cod === 'quinta'): ?><span class="g-plan-etq">El más completo</span><?php endif; ?>
            <h2>Plan <?= e($pl['nombre']) ?><?= $u['plan_codigo'] === $cod ? ' <small class="suave">(tu plan)</small>' : '' ?></h2>
            <p class="g-plan-precio"><?= dinero($m['monto']) ?> <small>al mes</small></p>
            <p class="suave"><?= e($pl['descripcion'] ?? '') ?></p>
            <p class="g-nota"><?= $pl['max_espacios'] ? 'Hasta ' . (int) $pl['max_espacios'] . ' espacios' : 'Espacios sin límite' ?> ·
                <?= $pl['max_usuarios'] ? 'hasta ' . (int) $pl['max_usuarios'] . ' usuarios' : 'usuarios sin límite' ?></p>
            <ul class="g-incluye"><?php foreach ($incluye[$cod] ?? [] as $i): ?><li><?= e($i) ?></li><?php endforeach; ?></ul>
            <?php foreach (['mensual', 'semestral', 'anual'] as $per): $c = $precios[$cod][$per]; $ah = $ahorro($c, $m); ?>
                <div class="g-periodo">
                    <div><strong><?= e($nombres[$per]) ?></strong>
                        <?= $ah > 0 ? '<span class="g-ahorro">ahorras ' . $ah . ' %</span>' : '' ?><br>
                        <small class="suave"><?= e(planes()->textoPeriodo($per)) ?><?= $per !== 'mensual' ? ' · sale a ' . dinero($c['precio_por_mes']) . ' al mes' : '' ?></small></div>
                    <strong class="monto"><?= dinero($c['monto']) ?></strong>
                    <a class="boton <?= $cod === 'quinta' ? '' : 'boton-claro' ?> boton-chico" href="<?= e($wa($pl['nombre'], strtolower($nombres[$per]), (float) $c['monto'])) ?>" target="_blank" rel="noopener">Contratar</a>
                </div>
            <?php endforeach; ?>
        </section>
    <?php endforeach; ?>
</div>

<section class="bloque" style="margin-top:16px">
    <h2>Cómo pagar</h2>
    <ol style="padding-left:20px;margin:0">
        <li>Toca <strong>Contratar</strong> en el plan y periodo que quieras: se abre WhatsApp con el mensaje listo.</li>
        <li>Te enviamos los datos para pagar por <strong>transferencia, DeUna o tarjeta</strong>.</li>
        <li>Envíanos la foto del comprobante y te activamos el plan el mismo día. Tus datos se mantienen.</li>
    </ol>
    <p class="g-nota" style="margin-top:8px">El plan anual incluye un dominio propio para tu página (ej. tuquinta.com) a nombre de Tukán mientras tengas el plan.</p>
</section>

<section class="bloque">
    <h2>Pagos hechos</h2>
    <?php if (!$pagos): ?><p class="vacio">Todavía no hay pagos registrados.</p><?php else: ?>
        <div class="tabla-scroll"><table class="tabla" style="min-width:480px">
            <thead><tr><th>Pagado</th><th>Plan</th><th>Cubre</th><th class="num">Valor</th></tr></thead>
            <tbody><?php foreach ($pagos as $p): ?>
                <tr style="<?= $p['anulada'] ? 'opacity:.5;text-decoration:line-through' : '' ?>">
                    <td><?= e((new DateTimeImmutable($p['pagado_en']))->format('d/m/Y')) ?></td>
                    <td><?= e($p['plan_nombre']) ?> · <?= e(Planes::PERIODOS_NOMBRE[$p['periodo']] ?? $p['periodo']) ?><?= $p['dominio'] ? '<br><small>' . e($p['dominio']) . '</small>' : '' ?></td>
                    <td><?= e((new DateTimeImmutable($p['inicio']))->format('d/m/Y')) ?> al <?= e((new DateTimeImmutable($p['fin']))->format('d/m/Y')) ?></td>
                    <td class="num"><?= dinero($p['monto']) ?></td></tr>
            <?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>
</section>
