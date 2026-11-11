<?php
use TuPlanner\{Eventos, Pagos, WhatsApp};
/** Inicio (dashboard) con la identidad gráfica común Tukán (docs/LINEA_GRAFICA.md), en este orden:
 *  saludo · aviso importante · primeros pasos · cifras · tarjetas del día · accesos rápidos.
 *  @var array $negocio @var array $cifras @var array $noLeidos @var array $proximos @var array $seguimientos @var array $tareas
 *  @var array $cuotas @var array $retenidas @var array $consultas @var array $pasos @var bool $comercial @var bool $editar
 *  @var DateTimeImmutable $hoy @var bool $claveTemporal */
$mesesCortos = ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
$nombre = explode(' ', trim($u['nombre']))[0];
$hora = (int) date('G');
[$saludo, $emojiHora] = $hora < 12 ? ['Buenos días', '☀️'] : ($hora < 19 ? ['Buenas tardes', '🌤️'] : ['Buenas noches', '🌙']);
$hoyTxt = $hoy->format('Y-m-d');
$cuando = function (string $fecha) use ($hoy): string {
    $d = (int) $hoy->diff(new DateTimeImmutable($fecha))->format('%r%a');
    return match (true) { $d === 0 => 'hoy', $d === 1 => 'mañana', $d === -1 => 'ayer', $d < 0 => 'hace ' . -$d . ' días', default => "en $d días" };
};
$hechos = count(array_filter($pasos, fn($p) => $p['hecho']));
$coordinador = $u['rol'] === 'coordinador';
?>
<?php /* ===== a) Saludo ===== */ ?>
<div class="tk-saludo g-saludo">
    <h1><?= $emojiHora ?> <?= e($saludo) ?>, <?= e($nombre) ?> 👋</h1>
    <p><?= $coordinador ? 'Tus eventos y lo que tienes que hacer hoy' : 'Tus eventos, cobros y lo que tienes que hacer hoy' ?> · <?= e($negocio['nombre']) ?></p>
</div>

<?php /* ===== b) Avisos importantes: primero los del diseño general (demo con sus días, plan por vencer, términos), que en el Inicio van aquí ===== */ ?>
<!--tk-tiras-->
<?php if ($claveTemporal): ?>
    <div class="tk-tira"><span>🔑 Entraste con una contraseña temporal. Cámbiala por una tuya en Mi cuenta.</span>
        <a class="tk-boton chico" href="<?= e(url('mi_cuenta')) ?>#clave">Cambiar contraseña</a></div>
<?php endif; ?>
<?php if ($cifras['comprobantes'] > 0 && $comercial): ?>
    <div class="tk-tira"><span>💳 <?= $cifras['comprobantes'] ?> <?= $cifras['comprobantes'] === 1 ? 'comprobante de pago subido por un cliente espera' : 'comprobantes de pago subidos por clientes esperan' ?> tu revisión.</span>
        <a class="tk-boton chico" href="<?= e(url('cobros')) ?>">Ver ahora</a></div>
<?php endif; ?>
<?php if (($u['estado'] ?? '') === 'prueba'): /* (10 nov 2026) tarjeta de la demo: hasta cuándo dura y dónde bajar el manual y los primeros pasos */ ?>
    <section class="tk-tira" id="tarjeta-demo" aria-label="Tu demo">
        <span>📘 <strong>Tu demo dura hasta el <?= e(!empty($u['prueba_hasta']) ? (new DateTimeImmutable($u['prueba_hasta']))->format('d/m') : '—') ?>.</strong> Descarga el manual y la guía de primeros pasos.</span>
        <div class="tk-acciones">
            <a class="tk-boton secundario chico" href="/manual.pdf" download="Manual_TuPlanner.pdf">Descargar el manual</a>
            <a class="tk-boton secundario chico" href="/primeros_pasos.pdf" download="TuPlanner_Primeros_Pasos.pdf">Descargar primeros pasos</a>
        </div>
    </section>
<?php endif; ?>

<div class="instalar-app" data-instalar-app hidden>
    <img src="/assets/icono-192.png" alt="" width="48" height="48">
    <div style="flex:1"><p><strong>Instala TuPlanner en tu celular</strong></p>
        <p class="suave" style="font-size:.9rem">Ábrelo como una app, con su ícono en la pantalla de inicio.</p></div>
    <button class="tk-boton chico" type="button" data-instalar hidden>Instalar</button>
</div>

<?php /* ===== c) Primeros pasos: hasta completarlos (o hasta «ocultar», que se guarda por negocio) ===== */ ?>
<?php if ($pasos): ?>
<section class="tk-pasos g-pasos" aria-labelledby="t-pasos">
    <div class="tk-tarjeta-cabeza">
        <div><h2 id="t-pasos">🚀 Primeros pasos</h2>
            <p>Completa estos pasos y tu negocio queda listo · <span class="g-avance"><?= $hechos ?> de <?= count($pasos) ?> listos</span></p></div>
        <form method="post" action="<?= e(url('inicio', ['accion' => 'ocultar_pasos'])) ?>">
            <?= campo_csrf() ?><button class="tk-boton secundario chico" type="submit">Ya sé usarlo, ocultar</button>
        </form>
    </div>
    <div class="tk-barra" role="progressbar" aria-valuemin="0" aria-valuemax="<?= count($pasos) ?>" aria-valuenow="<?= $hechos ?>" aria-label="Avance de los primeros pasos">
        <i style="width:<?= (int) round($hechos * 100 / count($pasos)) ?>%"></i></div>
    <ol>
        <?php foreach ($pasos as $i => $p): ?>
            <li class="<?= $p['hecho'] ? 'hecho' : '' ?>">
                <span class="tk-check g-paso-marca" aria-hidden="true"><?= $p['hecho'] ? '✓' : $i + 1 ?></span>
                <span class="te-paso-cuerpo"><span class="tk-paso-texto g-paso-texto"><?= e($p['texto']) ?></span><?= $p['hecho'] ? ' <span class="sr-only">(listo)</span>' : '' ?>
                    <small><?= e($p['ayuda']) ?></small></span>
                <?php if (!$p['hecho']): ?>
                    <span class="tk-acciones">
                        <?php if ($p['clave'] === 'precios'): ?>
                            <form method="post" action="<?= e(url('inicio', ['accion' => 'precios_revisados'])) ?>"><?= campo_csrf() ?>
                                <button class="tk-boton secundario chico" type="submit">Ya lo hice</button></form>
                        <?php endif; ?>
                        <a class="tk-boton chico" href="<?= e($p['enlace']) ?>">Hacerlo</a>
                    </span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</section>
<?php endif; ?>

<?php /* ===== d) Cifras principales (las que el Inicio ya calcula) ===== */ ?>
<div class="tk-cifras g-cifras">
    <a class="tk-cifra programa" href="<?= e(url('calendario')) ?>"><div class="tk-cifra-titulo">Eventos este mes</div><div class="tk-cifra-valor"><?= (int) $cifras['eventos_mes'] ?></div><div class="tk-cifra-detalle">separados, confirmados y realizados</div></a>
    <a class="tk-cifra" href="<?= e(url('eventos', ['estado' => 'vendidos'])) ?>"><div class="tk-cifra-titulo">Eventos en los próximos 30 días</div><div class="tk-cifra-valor"><?= (int) $cifras['proximos_30'] ?></div><div class="tk-cifra-detalle">separados y confirmados</div></a>
    <?php if ($comercial): ?>
        <a class="tk-cifra <?= $cifras['vencido'] > 0 ? 'mal' : 'ok' ?>" href="<?= e(url('cobros')) ?>"><div class="tk-cifra-titulo">Vencido por cobrar</div><div class="tk-cifra-valor"><?= dinero($cifras['vencido']) ?></div><div class="tk-cifra-detalle"><?= $cifras['vencido'] > 0 ? 'cuotas que ya pasaron su fecha' : 'nadie te debe cuotas atrasadas' ?></div></a>
    <?php endif; ?>
    <?php if (!$coordinador): ?>
        <a class="tk-cifra <?= $cifras['consultas_nuevas'] > 0 ? 'te-ambar' : '' ?>" href="<?= e(url('eventos', ['estado' => 'consulta'])) ?>"><div class="tk-cifra-titulo">Consultas nuevas sin atender</div><div class="tk-cifra-valor"><?= (int) $cifras['consultas_nuevas'] ?></div><div class="tk-cifra-detalle">nadie les ha contestado todavía</div></a>
    <?php endif; ?>
    <?php if ($comercial && modulo_activo($u, 'proveedores')) require RAIZ . '/app/vistas/_pv_inicio.php'; /* pagos a proveedores (060, módulo proveedores) */ ?>
</div>
<?php if (modulo_activo($u, 'metas')) require RAIZ . '/app/vistas/_metas_inicio.php'; /* meta de ventas del mes (módulo metas) */ ?>

<?php /* ===== e) Lo más importante para el día ===== */ ?>
<div class="tk-columnas">
    <section class="tk-tarjeta" aria-labelledby="t-proximos">
        <div class="tk-tarjeta-cabeza"><div><h2 id="t-proximos">📅 Próximos 7 días</h2><p>Los eventos vendidos y las fechas retenidas de esta semana</p></div>
            <a class="tk-boton chico" href="<?= e(url('calendario')) ?>">Ver calendario</a></div>
        <?php if (!$proximos): ?>
            <div class="tk-vacio"><b>No hay eventos esta semana.</b>Cuando separes una fecha, aparece aquí.<?php if (es_comercial($u)): ?><br><a class="tk-boton" href="<?= e(url('evento_nuevo')) ?>">＋ Anotar una consulta</a><?php endif; ?></div>
        <?php else: ?>
        <div class="tk-tabla-caja"><table class="tk-tabla tarjetas"><thead><tr><th>Fecha</th><th>Evento</th><th>Estado</th><?php if ($comercial): ?><th class="num">Saldo</th><?php endif; ?></tr></thead><tbody>
        <?php foreach ($proximos as $e):
            $f = new DateTimeImmutable($e['fecha']);
            $saldo = max(0, (float) $e['total'] - (float) $e['pagado']); ?>
            <tr>
                <td data-titulo="Fecha"><strong><?= $f->format('j') ?> <?= $mesesCortos[(int) $f->format('n')] ?></strong><small><?= e(ucfirst($cuando($e['fecha']))) ?> · <?= e(hora_corta($e['hora_inicio'])) ?></small></td>
                <td data-titulo="Evento"><a href="<?= e(url('evento', ['id' => $e['id']])) ?>"><?= e($e['nombre']) ?></a><small><?= (int) $e['invitados'] ?> inv. · <?= e($e['espacios'] ?? '') ?></small></td>
                <td data-titulo="Estado"><span class="ev-estado ev-<?= e($e['estado']) ?>"><?= e($e['estado'] === 'cotizado' ? 'Retenido' : Eventos::ESTADOS[$e['estado']]) ?></span></td>
                <?php if ($comercial): ?><td data-titulo="Saldo" class="num tk-dinero <?= (float) $e['total'] > 0 && $saldo > 0 ? 'mal' : '' ?>"><?= (float) $e['total'] > 0 ? ($saldo > 0 ? 'Debe ' . dinero($saldo) : 'Pagado') : '—' ?></td><?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
        <?php endif; ?>
    </section>

    <?php if ($comercial): ?>
    <section class="tk-tarjeta <?= array_filter($cuotas, fn($c) => $c['vencida']) ? 'mal' : '' ?>" aria-labelledby="t-cuotas">
        <div class="tk-tarjeta-cabeza"><div><h2 id="t-cuotas">🚨 Cuotas vencidas o por vencer</h2><p>Lo que te deben pagar esta semana</p></div>
            <a class="tk-boton chico" href="<?= e(url('mensajes')) ?>">Recordar por WhatsApp</a></div>
        <?php if (!$cuotas): ?>
            <div class="tk-vacio"><b>No hay cuotas que venzan esta semana. 👏</b>Aquí verás a quién cobrarle y cuánto.</div>
        <?php else: ?>
        <div class="tk-tabla-caja"><table class="tk-tabla tarjetas"><thead><tr><th>Evento</th><th>Vence</th><th class="num">Saldo</th><th>Recordar</th></tr></thead><tbody>
        <?php foreach (array_slice($cuotas, 0, 8) as $c):
            $wa = es_comercial($u) && $c['telefono'] ? WhatsApp::enlace($c['telefono'], Pagos::textoRecordatorio((string) $c['cliente'], $negocio['nombre'], $c['concepto'], (float) $c['saldo'], $c['vence'], url_completa('portal', ['t' => $c['token_cliente']]))) : null; ?>
            <tr>
                <td data-titulo="Evento"><a href="<?= e(url('evento_pagos', ['id' => $c['evento_id']])) ?>"><?= e($c['evento']) ?></a><small><?= e($c['concepto']) ?></small></td>
                <td data-titulo="Vence"><span class="<?= $c['vencida'] ? 'g-vencida' : '' ?>"><?= $c['vencida'] ? 'venció ' : 'vence ' ?><?= e($cuando($c['vence'])) ?></span></td>
                <td data-titulo="Saldo" class="num tk-dinero monto <?= $c['vencida'] ? 'mal monto-neg' : '' ?>"><?= dinero($c['saldo']) ?></td>
                <td data-titulo="Recordar"><?php if ($wa): ?><a class="tk-boton ok chico" href="<?= e($wa) ?>" target="_blank" rel="noopener" aria-label="Recordar a <?= e($c['cliente']) ?>">WhatsApp</a><?php else: ?>—<?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if (!$coordinador): ?>
    <section class="tk-tarjeta info" aria-labelledby="t-seg">
        <div class="tk-tarjeta-cabeza"><div><h2 id="t-seg">📞 Seguimientos de hoy</h2><p>Llamadas y visitas que tienes pendientes</p></div>
            <a class="tk-boton chico" href="<?= e(url('seguimiento')) ?>">Ver todos</a></div>
        <?php if (!$seguimientos): ?>
            <div class="tk-vacio"><b>No tienes llamadas ni visitas pendientes para hoy.</b>Agenda un seguimiento desde cada evento.</div>
        <?php else: ?>
        <div class="tk-tabla-caja"><table class="tk-tabla tarjetas"><thead><tr><th>Hora</th><th>Qué hay que hacer</th><th>Acción</th></tr></thead><tbody>
        <?php foreach (array_slice($seguimientos, 0, 8) as $a):
            $prog = new DateTimeImmutable($a['programada_para']);
            $atrasado = $prog->format('Y-m-d') < $hoyTxt;
            $wa = $a['telefono'] ? WhatsApp::enlace($a['telefono'], 'Hola ' . explode(' ', trim((string) $a['cliente']))[0] . ', te saludamos de ' . $negocio['nombre'] . '.') : null; ?>
            <tr>
                <td data-titulo="Hora"><strong class="hora <?= $atrasado ? 'g-vencida' : '' ?>"><?= $atrasado ? e($prog->format('d/m')) : e($prog->format('H:i')) ?></strong><?= $atrasado ? '<small class="g-vencida">atrasado</small>' : '' ?></td>
                <td data-titulo="Qué hay que hacer"><?= e(Eventos::TIPOS_ACTIVIDAD[$a['tipo']] ?? 'Nota') ?>: <?= e(mb_strimwidth($a['texto'], 0, 90, '…')) ?>
                    <small><?php if ($a['evento_id']): ?><a href="<?= e(url('evento', ['id' => $a['evento_id']])) ?>"><?= e($a['evento']) ?></a><?php else: ?><?= e($a['cliente'] ?? '') ?><?php endif; ?><?= $u['rol'] !== 'vendedor' && $a['usuario'] ? ' · ' . e($a['usuario']) : '' ?></small></td>
                <td data-titulo="Acción"><span class="tk-acciones">
                    <?php if ($wa && es_comercial($u)): ?><a class="tk-boton ok chico" href="<?= e($wa) ?>" target="_blank" rel="noopener" aria-label="WhatsApp a <?= e($a['cliente']) ?>">WhatsApp</a><?php endif; ?>
                    <?php if ($editar && es_comercial($u)): ?>
                        <form method="post" action="<?= e(url('inicio', ['accion' => 'seguimiento'])) ?>"><?= campo_csrf() ?><input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                            <button class="tk-boton secundario chico" type="submit">✅ Hecho</button></form>
                    <?php endif; ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <section class="tk-tarjeta ok" aria-labelledby="t-tareas">
        <div class="tk-tarjeta-cabeza"><div><h2 id="t-tareas">✅ Tareas por vencer</h2><p>Lo que hay que dejar listo en los próximos 7 días</p></div>
            <a class="tk-boton chico" href="<?= e(url('eventos')) ?>">Ver eventos</a></div>
        <?php if (!$tareas): ?>
            <div class="tk-vacio"><b>No hay tareas pendientes esta semana.</b>Las tareas salen solas al crear cada evento.</div>
        <?php else: ?>
        <div class="tk-tabla-caja"><table class="tk-tabla tarjetas"><thead><tr><th>Tarea</th><th>Vence</th><?php if ($editar): ?><th>Acción</th><?php endif; ?></tr></thead><tbody>
        <?php foreach (array_slice($tareas, 0, 10) as $t):
            $vencida = $t['vence'] < $hoyTxt; ?>
            <tr>
                <td data-titulo="Tarea"><?= e($t['titulo']) ?><small><a href="<?= e(url('evento', ['id' => $t['evento_id']])) ?>"><?= e($t['evento']) ?></a></small></td>
                <td data-titulo="Vence"><span class="<?= $vencida ? 'g-vencida' : '' ?>"><?= $vencida ? 'venció ' : 'vence ' ?><?= e($cuando($t['vence'])) ?></span></td>
                <?php if ($editar): ?><td data-titulo="Acción">
                    <form method="post" action="<?= e(url('inicio', ['accion' => 'tarea'])) ?>"><?= campo_csrf() ?><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                        <button class="tk-boton secundario chico" type="submit" aria-label="Marcar como hecha: <?= e($t['titulo']) ?>">✅ Hecha</button></form></td><?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
        <?php if (count($tareas) > 10): ?><p class="g-nota">Y <?= count($tareas) - 10 ?> más en cada evento.</p><?php endif; ?>
        <?php endif; ?>
    </section>

    <?php if (!$coordinador && ($retenidas || $consultas)): ?>
    <section class="tk-tarjeta te-ambar" aria-labelledby="t-atender">
        <div class="tk-tarjeta-cabeza"><div><h2 id="t-atender">📥 Para atender</h2><p>Fechas retenidas por liberarse y consultas que nadie ha contestado</p></div>
            <a class="tk-boton chico" href="<?= e(url('eventos', ['estado' => 'consulta'])) ?>">Ver consultas</a></div>
        <div class="tk-tabla-caja"><table class="tk-tabla tarjetas"><thead><tr><th>Qué pasa</th><th>Acción</th></tr></thead><tbody>
        <?php foreach ($retenidas as $r):
            $wa = es_comercial($u) && $r['telefono'] ? WhatsApp::enlace($r['telefono'], 'Hola ' . explode(' ', trim($r['cliente']))[0] . ', te saludamos de ' . $negocio['nombre']
                . '. Te tenemos guardada la fecha ' . fecha_corta($r['fecha']) . ' hasta el ' . (new DateTimeImmutable($r['retenida_hasta']))->format('d/m')
                . '. Con el pago de la reserva queda separada para ti. ¿Te ayudamos?') : null; ?>
            <tr>
                <td data-titulo="Qué pasa">⏳ <a href="<?= e(url('evento', ['id' => $r['id']])) ?>"><?= e($r['nombre']) ?></a>
                    <small>La fecha retenida (<?= e(fecha_corta($r['fecha'])) ?>) se libera <?= e($cuando($r['retenida_hasta'])) ?> si no pagan la reserva</small></td>
                <td data-titulo="Acción"><?php if ($wa): ?><a class="tk-boton ok chico" href="<?= e($wa) ?>" target="_blank" rel="noopener">WhatsApp</a><?php else: ?>—<?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        <?php foreach ($consultas as $c): ?>
            <tr>
                <td data-titulo="Qué pasa">✉️ Consulta nueva: <a href="<?= e(url('evento', ['id' => $c['id']])) ?>"><?= e($c['nombre']) ?></a>
                    <small><?= e(fecha_corta($c['fecha'])) ?> · <?= (int) $c['invitados'] ?> invitados<?= $c['origen'] ? ' · ' . e(Eventos::ORIGENES[$c['origen']] ?? '') : '' ?> · llegó <?= e($cuando(substr((string) $c['creado_en'], 0, 10))) ?></small></td>
                <td data-titulo="Acción"><a class="tk-boton secundario chico" href="<?= e(url('evento', ['id' => $c['id']])) ?>">Atender</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </section>
    <?php endif; ?>

    <section class="tk-tarjeta" aria-labelledby="t-avisos">
        <div class="tk-tarjeta-cabeza"><div><h2 id="t-avisos">🔔 Avisos sin leer</h2><p>Pagos, firmas y consultas nuevas</p></div>
            <a class="tk-boton chico" href="<?= e(url('notificaciones')) ?>">Ver todos</a></div>
        <?php if (!$noLeidos): ?>
            <div class="tk-vacio"><b>Estás al día.</b>Aquí verás pagos, firmas y consultas nuevas.</div>
        <?php else: ?>
        <div class="tk-tabla-caja"><table class="tk-tabla tarjetas"><thead><tr><th>Aviso</th><th>Cuándo</th></tr></thead><tbody>
        <?php foreach ($noLeidos as $n): ?>
            <?php $enlace = $n['evento_id'] ? url('evento', ['id' => $n['evento_id']]) : url('notificaciones'); ?>
            <tr><td data-titulo="Aviso"><a href="<?= e($enlace) ?>" style="font-weight:400;color:inherit"><?= e(mb_strimwidth($n['texto'], 0, 160, '…')) ?></a></td>
                <td data-titulo="Cuándo"><?= e(fecha_corta($n['creada_en'], false)) ?> · <?= e(substr((string) $n['creada_en'], 11, 5)) ?></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
        <?php endif; ?>
    </section>
</div>

<?php /* ===== f) Accesos rápidos a lo más usado ===== */ ?>
<nav class="tk-acciones te-accesos g-accesos" aria-label="Accesos rápidos">
    <?php if (es_comercial($u)): ?><a class="tk-boton grande" href="<?= e(url('evento_nuevo')) ?>">➕ Nuevo evento</a><?php endif; ?>
    <a class="tk-boton secundario grande" href="<?= e(url('calendario')) ?>">📅 Calendario</a>
    <?php if ($comercial): ?><a class="tk-boton secundario grande" href="<?= e(url('cobros')) ?>">💳 Cobros</a><?php endif; ?>
    <a class="tk-boton secundario grande" href="<?= e(url('eventos')) ?>">🎉 Eventos</a>
    <?php if (!$coordinador): ?><a class="tk-boton secundario grande" href="<?= e(url('clientes')) ?>">👥 Clientes</a><?php endif; ?>
</nav>
