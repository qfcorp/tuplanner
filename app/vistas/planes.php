<?php
use TuPlanner\{Modulos, PanelTukan, Planes};
/** Mi plan (Anexo B). @var array $estadoMod @var array $catalogo @var array $planesMod @var array $precios @var string $estadoCuenta
 *  @var array $pagos @var array $pedidos @var array $pedidosPendientes @var array $uso @var array $listaPlanes
 *  @var ?array $mejora pasar al Completo pagando el prorrateo (PagoPlan::mejora; null = no aplica)
 *  @var ?array $sugerencia «te sugerimos el Completo» (PagoPlan::sugerenciaCompleto) @var array $sugerenciaModulo
 *  @var ?array $tarjeta pago con tarjeta por el Panel (null = apagado, el Panel no contesta o app de Google Play: no se muestra nada) */
$me = $estadoMod;
$nombres = ['mensual' => 'Cada mes', 'semestral' => 'Cada 6 meses', 'anual' => 'Cada año'];
$wa = fn(string $texto) => 'https://wa.me/' . whatsapp_ventas() . '?text=' . rawurlencode($texto . ' (' . $u['negocio'] . ', ' . $u['email'] . ')');
$estadoTxt = PanelTukan::ESTADOS[$estadoCuenta] ?? $estadoCuenta;
$hasta = $u['estado'] === 'prueba' ? $u['prueba_hasta'] : $u['activo_hasta'];
$porGrupo = [];
foreach ($catalogo as $m) $porGrupo[$m['grupo_nombre']][] = $m;
$ahorro = function (array $c, array $mensual): int {
    $normal = $mensual['monto'] * $c['meses_recibidos'];
    return $normal > 0 ? (int) round(($normal - $c['monto']) * 100 / $normal) : 0;
};
$encendidos = count($me['pagados']);
// App de Google Play (docs/GOOGLE_PLAY.md): sin precios del software, sin «Pedir cotización», sin «Pagar» ni WhatsApp de ventas.
// Se ve el plan, su estado, los módulos encendidos y se pueden elegir los módulos incluidos en el plan (no cuesta nada más).
$enApp = en_app_android();
// «Te sugerimos el Completo» (10 nov 2026, tercera parte, Dimitry: «queremos que más gente vaya al Completo»). Nunca en la app.
$avisoCompleto = function (string $donde) use ($sugerencia, $tarjeta, $u, $mejora): string {
    if (!$sugerencia || en_app_android()) return '';
    $s = $sugerencia;
    $pagas = ($u['estado'] === 'prueba' ? 'pagarías ' : 'pagas ') . dinero($s['actual']) . ' + IVA al mes';
    $h = $s['tipo'] === 'mayor'
        ? '<div class="aviso aviso-alerta mp-sugerencia" data-sugerencia="mayor"><strong>Con tus módulos ' . $pagas . '.</strong> El plan Completo cuesta ' . dinero($s['completo'])
          . ' + IVA y trae <strong>todos</strong> los módulos y el Asistente IA. Te sugerimos pasarte al Completo.'
        : '<div class="aviso aviso-suave mp-sugerencia" data-sugerencia="cerca">Con tus módulos ' . $pagas . '. <strong>Por ' . dinero($s['diferencia'])
          . ' más tienes el plan Completo</strong> con todos los módulos y el Asistente IA.';
    // El botón: pagar el Completo con tarjeta · pasarse ya pagando (suscripción pagada) · sin cobro con tarjeta NO cambia: WhatsApp de Tukán
    $sus = $tarjeta['suscripcion'] ?? null;
    if ($mejora) {
        // Cliente con el período pagado vigente y sin suscripción: paga solo la diferencia (bloque «Pasar al Completo», más abajo)
        $h .= ' <a class="boton boton-chico" href="#pasar-completo">Pasarme al Completo</a>';
    } elseif ($tarjeta && !$sus && !empty($tarjeta['opciones'][$s['plan']])) {
        $h .= ' <a class="boton boton-chico" href="' . e(url('planes', ['pagar' => $s['plan']])) . '#pagar-' . e($s['plan']) . '">Pasarme al Completo</a>';
    } elseif (!$tarjeta && $u['estado'] === 'prueba') {
        // Demo sin cobro con tarjeta: sin botón (la demo ya tiene todo encendido; el plan se elige al contratar)
    } elseif (!$tarjeta) {
        // Sin cobro con tarjeta disponible: el plan no cambia sin pagar. Se explica y se da el WhatsApp de Tukán (sin pedido de cambio de plan)
        $h .= '<br><span class="mp-sin-tarjeta">' . e(TuPlanner\PagoPlan::SIN_TARJETA) . '</span> <a class="boton boton-wa boton-chico" href="'
            . e('https://wa.me/' . whatsapp_ventas() . '?text=' . rawurlencode('Hola, quiero pasarme al plan Completo de TuPlanner (' . $u['negocio'] . ', ' . $u['email'] . ')'))
            . '" target="_blank" rel="noopener">Escribir por WhatsApp</a>';
    } elseif ($sus && in_array($sus['estado'] ?? '', ['activa', 'en_gracia'], true)) {
        // Con suscripción con tarjeta pagada: se pasa ya; el Panel cobra el prorrateo aparte si sube, o el valor nuevo desde el próximo cobro si baja
        $h .= ' <form method="post" action="' . e(url('planes')) . '" style="display:inline" onsubmit="return confirm('
            . e(json_encode('¿Pasarte al plan Completo? Tienes todos los módulos desde ahora. Si el Completo cuesta más, hoy se cobra aparte lo proporcional de los días que faltan; si cuesta menos, pagas el valor nuevo desde el próximo cobro.', JSON_UNESCAPED_UNICODE)) . ')">'
            . campo_csrf() . '<input type="hidden" name="accion" value="pasar_completo"><button class="boton boton-chico" type="submit">Pasarme al Completo</button></form>';
    } else {
        // Tiene un pago con tarjeta sin terminar: primero lo termina o lo cancela (abajo, en «Pagar con tarjeta»)
        $h .= ' <a class="boton boton-chico" href="#pagar-tarjeta">Pasarme al Completo</a>';
    }
    return $h . '</div>';
};
// ¿Ese plan se puede pagar solo con tarjeta ahora? (Panel prendido, sin suscripción viva)
$conTarjeta = fn(string $cod) => !empty($tarjeta) && !$tarjeta['suscripcion'] && !empty($tarjeta['opciones'][$cod]);
?>
<div class="cabecera">
    <div><h1>Mi plan</h1><p>Tus módulos de TuPlanner para <?= e($u['negocio']) ?><?= $enApp ? '.' : ': lo que tienes, lo que pagas y lo que puedes agregar.' ?></p></div>
</div>

<section class="bloque mp-resumen" aria-labelledby="mp-t-plan">
    <div>
        <p style="margin:0" id="mp-t-plan">Plan <strong><?= e($u['plan_nombre'] ?? $u['plan_codigo']) ?></strong>
            <span class="etq est-<?= e($estadoCuenta) ?>"><?= e($estadoTxt) ?></span></p>
        <?php if (!$enApp): ?>
        <p class="mp-precio"><?= dinero($me['mensual']) ?> <small>+ IVA al mes</small></p>
        <ul class="mp-desglose">
            <li>Plan <?= dinero($me['precio_plan']) ?></li>
            <?php if ($me['precio_extra'] > 0): ?><li>módulos extra +<?= dinero($me['precio_extra']) ?></li><?php endif; ?>
            <?php if ($me['descuento'] > 0): ?><li>módulos quitados −<?= dinero($me['descuento']) ?></li><?php endif; ?>
            <?php if ($me['precio_acordado'] !== null): ?><li>precio acordado con Tukán</li><?php endif; ?>
        </ul>
        <?php endif; ?>
        <p class="suave" style="margin:6px 0 0">
            <?php if ($hasta): ?><?= $u['estado'] === 'prueba' ? 'Demo hasta el ' : 'Pagado hasta el ' ?><strong><?= e((new DateTimeImmutable($hasta))->format('d/m/Y')) ?></strong> · <?php endif; ?>
            <?= $encendidos ?> de <?= count($catalogo) ?> módulos · <?= (int) $uso['espacios'] ?> espacios y <?= (int) $uso['usuarios'] ?> usuarios activos <small>(los administradores no cuentan)</small></p>
        <?php if (!$me['demo'] && ($me['modulos_elegir'] ?? 0) > 0): /* planes de $20 y $45 (migración 112) */ ?>
            <p class="mp-elegir" style="margin:6px 0 0" data-por-elegir="<?= (int) $me['por_elegir'] ?>">Tu plan trae <strong><?= (int) $me['modulos_elegir'] ?> módulos a tu elección</strong><?= $me['por_elegir'] > 0
                ? ': te ' . ($me['por_elegir'] === 1 ? 'queda 1 por elegir' : 'quedan ' . (int) $me['por_elegir'] . ' por elegir') . '. Elígelo abajo con «Elegir este módulo».' : '.' ?></p>
        <?php endif; ?>
    </div>
    <?php if (!$enApp): ?><a class="boton boton-wa" href="<?= e($wa('Hola, tengo una pregunta sobre mi plan de TuPlanner')) ?>" target="_blank" rel="noopener">Preguntar por WhatsApp</a><?php endif; ?>
</section>
<?= $avisoCompleto('arriba') ?>
<?php if ($mejora && !$enApp): /* (10 nov 2026, 18:26) subir al Completo con el período pagado: el cálculo siempre a la vista y dos formas de pagar */
    $mjSus = $tarjeta['suscripcion'] ?? null; $mjTarjeta = $tarjeta && !$mjSus; ?>
<section class="bloque" id="pasar-completo" aria-labelledby="mp-t-mejora">
    <h2 id="mp-t-mejora">Pasar al Completo</h2>
    <p id="mejora-calculo"><strong>Pasar al Completo:</strong> te faltan <?= (int) $mejora['dias'] ?> <?= $mejora['dias'] === 1 ? 'día' : 'días' ?> (hasta el <?= e(date('d/m/Y', strtotime($mejora['hasta']))) ?>).
        Diferencia: <?= dinero($mejora['subtotal']) ?> + IVA = <strong><?= dinero($mejora['total']) ?></strong>.</p>
    <p class="g-nota">Pagas solo la diferencia por los días que te faltan. Tu fecha de vencimiento no cambia; en la renovación pagas el plan Completo.<?= $mejora['subtotal'] < 1 ? ' <strong>La diferencia es menor a $1: no se cobra.</strong>' : '' ?></p>
    <div class="acciones">
        <?php if ($mejora['subtotal'] < 1): /* (19:12) menos de $1 (o $0): pasa directo, con o sin cobro con tarjeta */ ?>
            <form method="post" action="<?= e(url('planes')) ?>" style="display:inline"><?= campo_csrf() ?><input type="hidden" name="accion" value="pasar_completo">
                <button class="boton" type="submit">Pasarme ahora (no se cobra)</button></form>
        <?php else: ?>
            <?php if ($mjTarjeta): ?>
                <form method="post" action="<?= e(url('planes')) ?>" style="display:inline"><?= campo_csrf() ?><input type="hidden" name="accion" value="pasar_completo">
                    <button class="boton" type="submit">Pagar <?= dinero($mejora['total']) ?> con tarjeta</button></form>
            <?php endif; ?>
            <a class="boton boton-wa" href="<?= e('https://wa.me/' . whatsapp_ventas() . '?text=' . rawurlencode(TuPlanner\PagoPlan::textoWhatsAppMejora($mejora, (string) $u['negocio']))) ?>" target="_blank" rel="noopener">Enviar por WhatsApp</a>
        <?php endif; ?>
    </div>
    <?php if ($mejora['subtotal'] >= 1): ?>
    <p class="g-nota" style="margin-top:8px"><?= $mjTarjeta ? 'Con tarjeta, tu plan cambia apenas se aprueba el pago. ' : ($mjSus ? 'Tienes un pago con tarjeta sin terminar: termínalo o cancélalo abajo para pagar este cambio con tarjeta. ' : '') ?>Por WhatsApp pagas por transferencia: tu plan cambia cuando Tukán registra tu pago.</p>
    <?php endif; ?>
</section>
<?php endif; ?>
<?php if ($me['demo']): ?>
    <p class="aviso aviso-alerta">Estás en tu demo: <?= $me['demo_todos'] ? 'tienes todos los módulos encendidos para que veas todo' : 'tienes encendidos los módulos que elegimos contigo' ?>.
        <?= $enApp ? 'Nada de lo que cargues se pierde.' : 'Al contratar te quedas con los módulos de tu plan y los que agregues; nada de lo que cargaste se pierde.' ?></p>
<?php endif; ?>

<?php if (!empty($tarjeta)): /* Pagar el plan solo, con tarjeta (src/PagoPlan.php). Solo si el Panel lo tiene prendido; nunca en la app de Google Play */
    $tFecha = fn(?string $f) => $f && strtotime(substr($f, 0, 10)) ? date('d/m/Y', strtotime(substr($f, 0, 10))) : '';
    ?>
<?php if ($tarjeta['vuelta'] === 'aprobado'): ?>
    <p class="aviso aviso-ok" id="pago-recibido">✅ ¡Pago recibido! Tu plan se actualiza en unos segundos (vuelve a abrir esta página). La factura te llega al correo.</p>
<?php elseif ($tarjeta['vuelta'] !== ''): ?>
    <p class="aviso aviso-alerta" id="pago-no-completado">El pago no se completó. Puedes intentarlo otra vez, con la misma tarjeta o con otra.</p>
<?php endif; ?>
<section class="bloque" id="pagar-tarjeta" aria-labelledby="mp-t-tarjeta">
    <h2 id="mp-t-tarjeta">Pagar con tarjeta</h2>
    <?php if ($tarjeta['pruebas']): ?><p class="aviso aviso-alerta">Pagos con tarjeta en <strong>modo de prueba: no se cobra de verdad</strong>.</p><?php endif; ?>
    <?= $avisoCompleto('tarjeta') ?>
    <?php if ($tarjeta['vivos']): ?>
        <ul class="filas">
        <?php foreach ($tarjeta['vivos'] as $c): $esSus = ($c['tipo'] ?? '') === 'suscripcion'; $sinPagar = in_array($c['estado'], ['pendiente', 'rechazado'], true); ?>
            <li><span><strong><?= e($c['descripcion'] ?? 'Pago con tarjeta') ?></strong>
                <small class="suave" style="display:block"><?= e($c['estado_texto'] ?? $c['estado']) ?> · <?= dinero((float) ($c['total'] ?? 0)) ?> con IVA<?= $esSus && !empty($c['cobra_sola']) ? ' · se cobra sola a tu tarjeta' : '' ?><?= !empty($c['pagado_hasta']) ? ' · pagada hasta el ' . e($tFecha($c['pagado_hasta'])) : '' ?></small></span>
                <span class="derecha">
                    <?php if (!empty($c['url_pago'])): ?><a class="boton boton-chico" href="<?= e($c['url_pago']) ?>">Pagar ahora</a><?php endif; ?>
                    <?php if ($esSus && $sinPagar): ?>
                        <form method="post" style="display:inline"><?= campo_csrf() ?><input type="hidden" name="accion" value="cancelar_tarjeta"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                            <button class="boton boton-claro boton-chico" type="submit">Cancelar este pago</button></form>
                    <?php endif; ?>
                </span></li>
        <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <?php if ($tarjeta['suscripcion']): ?>
        <p class="g-nota" id="ya-suscrito"><?= in_array($tarjeta['suscripcion']['estado'], ['pendiente', 'rechazado'], true)
            ? 'Tienes un pago con tarjeta sin terminar: termínalo con «Pagar ahora» o cancélalo para elegir otro plan u otro período.'
            : 'Ya tienes una suscripción con tarjeta: se cobra sola y no hace falta pagar otra vez. Para dejarla, usa «Cancelar mi plan», al final de esta página.' ?></p>
    <?php elseif ($tarjeta['opciones']): /* (10 nov 2026, Dimitry: «prefiero que vea los dos precios») todos los planes con su precio, de entrada */ ?>
        <form method="post" action="<?= e(url('planes')) ?>"><?= campo_csrf() ?>
            <input type="hidden" name="accion" value="pagar_tarjeta">
            <p style="margin:0 0 8px"><strong>Elige tu plan y cada cuánto pagas</strong> (valores sin IVA):</p>
            <div class="mp-planes">
            <?php $codDef = isset($tarjeta['opciones'][$u['plan_codigo']]) ? (string) $u['plan_codigo'] : (string) array_key_first($tarjeta['opciones']);
                  if (isset($tarjeta['opciones'][$tarjeta['marcar']])) $codDef = $tarjeta['marcar'];   // viene de «Pasarme al Completo»
                  $marcadoDef = $codDef . '|' . array_key_first($tarjeta['opciones'][$codDef]);   // su plan (o el elegido), cada mes
                  foreach ($tarjeta['opciones'] as $cod => $ops): if (!$ops) continue;
                $tPm = $planesMod[$cod] ?? null; $tInc = $tarjeta['incluye'][$cod]; $suyo = $cod === $u['plan_codigo']; ?>
                <fieldset class="mp-plan mp-plan-pagar <?= $suyo ? 'actual' : '' ?>" id="pagar-<?= e($cod) ?>">
                    <legend><strong>Plan <?= e($tarjeta['planes'][$cod]) ?></strong><?= $suyo ? ' <small class="suave">(el tuyo)</small>' : '' ?></legend>
                    <p class="mp-plan-que"><?= $tPm ? ($tPm['todos'] ? 'Todos los módulos' . (in_array('asistente_ia', $tPm['modulos'], true) ? ', con el Asistente IA' : '')
                          : ($tPm['modulos_elegir'] > 0 ? 'La base + ' . (int) $tPm['modulos_elegir'] . ' módulos que tú eliges' : 'La base + ' . count($tPm['modulos']) . ' módulos')) : '' ?>.</p>
                    <p class="mp-plan-cuenta"><?= $tInc['precio_acordado'] !== null ? 'Precio acordado con Tukán: '
                        : 'Plan ' . dinero($tInc['precio_plan']) . ($tInc['precio_extra'] > 0 ? ' + tus módulos extra ' . dinero($tInc['precio_extra']) : '') . ($tInc['descuento'] > 0 ? ' − módulos quitados ' . dinero($tInc['descuento']) : '') . ' = ' ?><strong><?= dinero($tInc['mensual']) ?> + IVA al mes</strong></p>
                    <?php foreach ($ops as $per => $c): $marcar = $cod . '|' . $per === $marcadoDef; ?>
                        <label class="mp-opcion"><input type="radio" name="opcion" value="<?= e($cod . '|' . $per) ?>"<?= $marcar ? ' checked' : '' ?> required>
                            <span class="mp-opcion-texto"><span class="mp-opcion-renglon"><?= e($nombres[$per] ?? $per) ?>: <strong><?= dinero($c['monto']) ?> + IVA</strong></span><?= $per !== 'mensual' ? '<small class="mp-opcion-nota">' . e(planes()->textoPeriodo($per)) . '</small>' : '' ?></span></label>
                    <?php endforeach; ?>
                </fieldset>
            <?php endforeach; ?>
            </div>
            <p style="margin:12px 0 4px"><strong>¿Cómo quieres pagar?</strong></p>
            <label style="display:block;margin:6px 0"><input type="radio" name="forma" value="suscripcion" checked>
                <strong>Suscripción:</strong> se cobra sola a tu tarjeta cada período que elegiste (ese valor + IVA), hasta que la canceles. Cancelas cuando quieras.</label>
            <label style="display:block;margin:6px 0"><input type="radio" name="forma" value="una_vez">
                <strong>Solo esta vez:</strong> pagas este período y no se vuelve a cobrar sola.</label>
            <p style="margin:12px 0 0"><button class="boton" type="submit">Pagar con tarjeta</button></p>
            <p class="g-nota" style="margin-top:8px">Te llevamos a la página de pago segura. Los valores son sin IVA: el IVA se suma al pagar y la factura te llega al correo.<?= $u['estado'] === 'prueba'
                ? ' Al aprobarse el pago, tu demo pasa a ser tu cuenta: nada de lo que cargaste se pierde.' : ' Tu plan se activa solo al aprobarse el pago.' ?></p>
        </form>
    <?php endif; ?>
</section>
<?php endif; ?>

<section class="bloque" aria-labelledby="mp-t-mods">
    <h2 id="mp-t-mods">Tus módulos</h2>
    <p class="g-nota"><?= $enApp ? 'Los módulos de tu cuenta los administra Tukán Software.' : 'Enciende solo lo que necesitas y paga solo eso.' ?> Si apagamos un módulo, tus datos se guardan y vuelven al encenderlo.</p>
    <?php foreach ($porGrupo as $grupo => $lista): ?>
        <div class="mp-grupo">
            <h3><?= e($grupo) ?></h3>
            <div class="mp-lista">
            <?php foreach ($lista as $m):
                $id = $m['id'];
                $on = in_array($id, $me['pagados'], true);
                $pedido = isset($pedidosPendientes[$id]);
                $faltan = array_values(array_filter($m['depende_de'], fn($d) => !in_array($d, $me['pagados'], true))); ?>
                <article class="mp-mod <?= $on ? 'on' : '' ?>" id="mod-<?= e($id) ?>">
                    <div class="mp-mod-cab">
                        <h4><span aria-hidden="true"><?= e($m['icono']) ?></span> <?= e($m['nombre']) ?></h4>
                        <span class="mp-estado <?= $on ? 'on' : ($pedido ? 'pedido' : 'off') ?>"><?= $on ? 'Encendido' : ($pedido ? 'Pedido enviado' : 'No está en tu plan') ?></span>
                    </div>
                    <p><?= e($m['descripcion']) ?></p>
                    <?php if ($faltan && !$on): ?><p class="ayuda-chica">Viene junto con: <?= e(implode(', ', array_map([Modulos::class, 'nombre'], $faltan))) ?>.</p><?php endif; ?>
                    <div class="mp-mod-pie">
                        <?php /* Regla del dueño: el precio de un módulo que el cliente no tiene no se muestra; se pide cotización */ ?>
                        <?php $aPedido = Modulos::porCotizacion($m);   // precio 0 en el catálogo: por cotización
                              // Planes de $20 y $45 (migración 112): en Básico puede elegir módulos incluidos; el Asistente IA no cuenta entre ellos
                              $elegible = !$on && ($me['por_elegir'] ?? 0) > 0 && !in_array($id, Modulos::FUERA_DEL_CUPO, true); ?>
                        <?php if ($enApp): /* app de Google Play: sin precios ni cotizaciones */ ?>
                        <span class="mp-mod-precio"><?= !$on ? ($elegible ? 'Puedes elegirlo en tu plan' : 'No está encendido') : (in_array($id, $me['elegidos'] ?? [], true) ? 'Incluido · lo elegiste' : 'Encendido en tu cuenta') ?></span>
                        <?php if ($elegible && !$pedido): ?>
                            <form method="post"><?= campo_csrf() ?><input type="hidden" name="accion" value="pedir"><input type="hidden" name="modulo" value="<?= e($id) ?>">
                                <button class="boton boton-chico" type="submit">Elegir este módulo</button></form>
                        <?php endif; ?>
                        <?php else: ?>
                        <span class="mp-mod-precio"><?= !$on ? ($elegible ? 'Puedes elegirlo en tu plan' : (in_array($id, Modulos::SOLO_A_PEDIDO, true) || $aPedido ? 'Pídalo y le cotizamos' : 'Precio por cotización'))
                            : (in_array($id, $me['del_plan'], true) ? 'Incluido en tu plan'
                               : (in_array($id, $me['elegidos'] ?? [], true) ? 'Incluido · lo elegiste'
                                  : ($aPedido ? 'Agregado a tu plan' : 'Extra · ' . dinero($me['precio_por_extra'][$id] ?? $m['precio_mensual']) . ' + IVA al mes'))) ?></span>
                        <?php if (!$on && !$pedido): $sm = $sugerenciaModulo[$id] ?? null; ?>
                            <?php if ($sm): /* (10 nov 2026) con este módulo igualaría o pasaría al Completo: se avisa antes de pedirlo */ ?>
                                <p class="aviso aviso-alerta mp-sugerencia-mod" style="margin:6px 0">Con este módulo pagarías <?= dinero($sm['actual']) ?> + IVA al mes. El plan Completo cuesta <?= dinero($sm['completo']) ?> + IVA y trae todos los módulos y el Asistente IA: te sugerimos el Completo.</p>
                            <?php endif; ?>
                            <form method="post"<?= $sm ? ' onsubmit="return confirm(' . e(json_encode('Con este módulo pagarías ' . dinero($sm['actual']) . ' + IVA al mes y el plan Completo, con todo, cuesta ' . dinero($sm['completo']) . ' + IVA. ¿Pedir este módulo igual?', JSON_UNESCAPED_UNICODE)) . ')"' : '' ?>><?= campo_csrf() ?><input type="hidden" name="accion" value="pedir"><input type="hidden" name="modulo" value="<?= e($id) ?>">
                                <button class="boton boton-chico" type="submit"><?= $elegible ? 'Elegir este módulo' : 'Pedir cotización' ?></button></form>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</section>

<section class="bloque" aria-labelledby="mp-t-base">
    <h2 id="mp-t-base">Siempre incluido</h2>
    <p class="g-nota">Todos los planes traen la base de TuPlanner:</p>
    <ul class="mp-base"><?php foreach (Modulos::BASE as $b): ?><li><?= e($b) ?></li><?php endforeach; ?></ul>
</section>

<?php if (!$enApp): /* App de Google Play: sin lo que se paga, ni cómo pagar, ni otros planes, ni pagos hechos */ ?>
<section class="bloque" aria-labelledby="mp-t-pagar">
    <h2 id="mp-t-pagar">Cuánto pagas</h2>
    <p class="suave" style="margin:0 0 8px">Los precios no incluyen el IVA: se suma al pagar y sale en tu factura.</p>
    <?php foreach (['mensual', 'semestral', 'anual'] as $per): $c = $precios[$per]; $ah = $ahorro($c, $precios['mensual']); ?>
        <div class="g-periodo">
            <div><strong><?= e($nombres[$per]) ?></strong>
                <?= $ah > 0 ? '<span class="g-ahorro">ahorras ' . $ah . ' %</span>' : '' ?><br>
                <small class="suave"><?= e(planes()->textoPeriodo($per)) ?><?= $per !== 'mensual' ? ' · sale a ' . dinero($c['precio_por_mes']) . ' + IVA al mes' : '' ?></small></div>
            <strong class="monto"><?= dinero($c['monto']) ?> <small>+ IVA</small></strong>
            <a class="boton boton-claro boton-chico" href="<?= e($wa('Hola, quiero pagar TuPlanner ' . mb_strtolower($nombres[$per]) . ' (' . dinero($c['monto']) . ' + IVA). ¿Cómo hago el pago?')) ?>" target="_blank" rel="noopener">Pagar</a>
        </div>
    <?php endforeach; ?>
    <ol style="padding-left:20px;margin:12px 0 0">
        <li>Toca <strong>Pagar</strong>: se abre WhatsApp con el mensaje listo.</li>
        <li>Te enviamos los datos para pagar por <strong>transferencia, DeUna o tarjeta</strong>.</li>
        <li>Envíanos la foto del comprobante y lo activamos el mismo día. Tus datos se mantienen.</li>
    </ol>
    <p class="g-nota" style="margin-top:8px">El pago anual incluye un dominio propio para tu página (ej. tuquinta.com) a nombre de Tukán mientras tengas el plan.</p>
</section>

<section class="bloque" aria-labelledby="mp-t-planes">
    <h2 id="mp-t-planes">Planes</h2>
    <p class="g-nota">Cada plan trae la base y un grupo de módulos. Puedes cambiar de plan o agregar módulos sueltos: pide la cotización y Tukán te escribe con el valor.</p>
    <div class="mp-planes">
        <?php foreach ($listaPlanes as $pl): $pm = $planesMod[$pl['codigo']] ?? null; if (!$pm) continue; ?>
            <div class="mp-plan <?= $pl['codigo'] === $u['plan_codigo'] ? 'actual' : '' ?>">
                <h3>Plan <?= e($pl['nombre']) ?><?= $pl['codigo'] === $u['plan_codigo'] ? ' <small class="suave">(el tuyo)</small>' : '' ?></h3>
                <p><?= $pm['todos'] ? 'Todos los módulos' . (in_array('asistente_ia', $pm['modulos'], true) ? ', con el Asistente IA' : '')
                       : ($pm['modulos_elegir'] > 0 ? 'La base + ' . ($pm['modulos'] ? count($pm['modulos']) . ' módulos + ' : '') . $pm['modulos_elegir'] . ' módulos que tú eliges' : count($pm['modulos']) . ' módulos') ?><?= $pl['codigo'] === $u['plan_codigo'] || $conTarjeta($pl['codigo']) ? '' : ' · precio por cotización' ?></p>
                <p><?= $pl['max_espacios'] ? 'Hasta ' . (int) $pl['max_espacios'] . ' espacios' : 'Espacios sin límite' ?> · <?= $pl['max_usuarios'] ? 'hasta ' . (int) $pl['max_usuarios'] . ' usuarios' : 'usuarios sin límite' ?></p>
                <p><?= e($pl['descripcion'] ?? '') ?></p>
                <?php if ($mejora && $pl['codigo'] === $mejora['plan']): ?>
                    <p><a class="boton boton-chico" href="#pasar-completo">Pasar a este plan</a></p>
                <?php endif; ?>
                <?php if ($pl['codigo'] !== $u['plan_codigo'] && $conTarjeta($pl['codigo'])): ?>
                    <p><a class="boton boton-chico" href="#pagar-<?= e($pl['codigo']) ?>">Elegir este plan y pagar con tarjeta</a></p>
                <?php endif; ?>
                <?php if ($pl['codigo'] !== $u['plan_codigo']): ?>
                    <?php $faltanPlan = array_diff($pm['todos'] ? array_keys($catalogo) : $pm['modulos'], $me['pagados']); ?>
                    <?php if ($faltanPlan): ?>
                        <form method="post"><?= campo_csrf() ?><input type="hidden" name="accion" value="pedir_plan"><input type="hidden" name="plan" value="<?= e($pl['codigo']) ?>">
                            <button class="boton boton-claro boton-chico" type="submit">Pedir cotización</button></form>
                    <?php else: ?>
                        <p><a href="<?= e($wa('Hola, quiero cotizar el cambio de mi TuPlanner al plan ' . $pl['nombre'])) ?>" target="_blank" rel="noopener">Pedir cotización</a></p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php endif; ?>

<?php if ($pedidos): ?>
<section class="bloque" aria-labelledby="mp-t-pedidos">
    <h2 id="mp-t-pedidos">Tus pedidos de módulos</h2>
    <ul class="filas">
        <?php foreach ($pedidos as $s): ?>
            <li><span><?= e(implode(', ', array_map([Modulos::class, 'nombre'], Modulos::parse($s['encender'])))) ?>
                <small class="suave" style="display:block"><?= e((new DateTimeImmutable($s['creado']))->format('d/m/Y')) ?><?= $s['respuesta'] && !$enApp ? ' · ' . e($s['respuesta']) : '' ?></small></span>
                <span class="mp-estado <?= $s['estado'] === 'aprobada' ? 'on' : ($s['estado'] === 'pendiente' ? 'pedido' : 'off') ?>"><?= ['pendiente' => 'Pendiente', 'aprobada' => 'Aprobado', 'rechazada' => 'No aprobado'][$s['estado']] ?></span></li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<?php if (!$enApp): ?>
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
<?php endif; ?>
<?php if ($u['estado'] !== 'prueba'): /* «Cancelar mi plan» (src/TukanCancelarPlan.php) */ ?>
<?= TukanCancelarPlan::html($cancelacion ?? null, campo_csrf(), url('planes'), $u['activo_hasta'] ?? null, en_app_android()) ?>
<?php endif; ?>
