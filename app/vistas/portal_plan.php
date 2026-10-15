<?php
use TuPlanner\{Cotizaciones, Planificacion};
/**
 * Planificación del evento para el cliente.
 * @var array $ev @var array $negocio @var string $token @var bool $activo @var array $grupos @var array $avanceCliente @var array $avancePlanner
 * @var array $partidas @var array $resumen @var array $pagosPor @var array $reuniones @var string $primerNombre
 */
$logo = $negocio['logo'] ? url('imagen_publica', ['tipo' => 'logo', 's' => $negocio['slug']]) : null;
$hoy = date('Y-m-d');
$num = fn(float $v) => number_format($v, 2, ',', '');
$visibles = array_values(array_filter($partidas, fn($p) => $p['estado'] !== 'cancelado'));
?>
<header class="publico-cabeza cb-portal-cabeza">
    <?php if ($logo): ?><img class="pub-logo cb-logo" src="<?= e($logo) ?>" alt="Logo de <?= e($negocio['nombre']) ?>">
    <?php else: ?><span class="marca-sello" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($negocio['nombre'], 0, 1))) ?></span><?php endif; ?>
    <div><h1><?= e($negocio['nombre']) ?></h1><p>Portal de tu evento</p></div>
</header>

<p class="pt-volver"><a href="<?= e(url('portal', ['t' => $token])) ?>">← Volver a mi evento</a></p>

<section class="pub-hero pt-hero">
    <p class="pt-hero-saludo">Hola, <?= e($primerNombre) ?></p>
    <h2 class="pt-hero-titulo">Tu planificación</h2>
    <p class="pt-hero-texto"><?= e($ev['nombre']) ?> · <?= e(fecha_corta($ev['fecha'])) ?></p>
</section>

<?php if ($reuniones): ?>
<section class="bloque" id="reuniones">
    <h2>Próximas reuniones</h2>
    <ul class="filas">
        <?php foreach ($reuniones as $r): ?>
            <li><span><strong><?= e(ucfirst(fecha_larga(substr($r['programada_para'], 0, 10)))) ?></strong> a las <?= e(substr($r['programada_para'], 11, 5)) ?><br><small><?= e($r['texto']) ?></small></span></li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<section class="bloque" id="tareas">
    <h2>Tus tareas</h2>
    <?php if (!$avanceCliente['total']): ?>
        <p class="vacio">Tu organizador todavía no te asignó tareas.</p>
    <?php else: ?>
        <div class="barra" role="progressbar" aria-valuenow="<?= (int) $avanceCliente['pct'] ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Tus tareas hechas"><span style="width:<?= (int) $avanceCliente['pct'] ?>%"></span></div>
        <p class="suave ayuda-chica"><?= (int) $avanceCliente['hechas'] ?> de <?= (int) $avanceCliente['total'] ?> hechas. Toca el cuadro cuando termines una.</p>
        <?php foreach ($grupos as $titulo => $lista): ?>
            <h3 class="pn-mes"><?= e($titulo) ?></h3>
            <ul class="pn-tareas">
                <?php foreach ($lista as $t): $atrasada = !$t['hecha'] && $t['vence'] && $t['vence'] < $hoy; ?>
                <li class="<?= $t['hecha'] ? 'pn-hecha' : '' ?>">
                    <?php if ($activo): ?>
                    <form method="post" action="<?= e(url('portal_plan', ['t' => $token])) ?>" class="pn-marcar">
                        <?= campo_csrf() ?><input type="hidden" name="accion" value="tarea"><input type="hidden" name="tarea_id" value="<?= (int) $t['id'] ?>">
                        <input type="hidden" name="hecha" value="<?= $t['hecha'] ? '0' : '1' ?>">
                        <button type="submit" class="pn-caja" aria-label="<?= $t['hecha'] ? 'Marcar como pendiente' : 'Marcar como hecha' ?>: <?= e($t['titulo']) ?>"><?= $t['hecha'] ? '✓' : '' ?></button>
                    </form>
                    <?php else: ?><span class="pn-caja" aria-hidden="true"><?= $t['hecha'] ? '✓' : '' ?></span><?php endif; ?>
                    <div class="pn-tarea-txt">
                        <span><?= e($t['titulo']) ?></span>
                        <?php if ($t['vence']): ?><small class="<?= $atrasada ? 'pn-rojo' : '' ?>">Hasta el <?= e(fecha_corta($t['vence'])) ?><?= $atrasada ? ' (atrasada)' : '' ?></small><?php endif; ?>
                        <?php if ($t['notas']): ?><small><?= e($t['notas']) ?></small><?php endif; ?>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php if ($avancePlanner['total']): ?>
        <p class="pn-planner-avance">Lo que hace tu organizador: <strong><?= (int) $avancePlanner['hechas'] ?> de <?= (int) $avancePlanner['total'] ?></strong> tareas listas.</p>
    <?php endif; ?>
</section>

<section class="bloque" id="presupuesto">
    <h2>Tu presupuesto</h2>
    <?php if (!$visibles && $resumen['organizador'] <= 0): ?>
        <p class="vacio">Tu organizador todavía está armando el presupuesto. Aquí verás cada proveedor, cuánto cuesta y qué falta pagar.</p>
    <?php else: ?>
    <div class="cifras pn-cifras">
        <?php if ($resumen['presupuesto_cliente'] !== null): ?><div class="cifra-tarjeta"><small>Tu presupuesto</small><strong><?= dinero($resumen['presupuesto_cliente']) ?></strong></div><?php endif; ?>
        <div class="cifra-tarjeta"><small><?= $resumen['organizador'] > 0 ? 'Costo del evento' : 'Directo a proveedores' ?></small><strong><?= dinero($resumen['proyectado']) ?></strong></div>
        <div class="cifra-tarjeta bien"><small>Ya pagado</small><strong><?= dinero($resumen['pagado']) ?></strong></div>
        <div class="cifra-tarjeta"><small>Falta pagar</small><strong><?= dinero($resumen['falta']) ?></strong></div>
    </div>
    <?php if ($resumen['diferencia'] !== null && $resumen['diferencia'] < 0): ?>
        <p class="aviso aviso-error">Por ahora el evento se pasa <?= dinero(abs($resumen['diferencia'])) ?> de tu presupuesto. Conversa con tu organizador qué ajustar.</p>
    <?php endif; ?>
    <?php if ($resumen['organizador'] > 0): ?>
        <p>Con <?= e($negocio['nombre']) ?> (tu contrato): <?= dinero($resumen['organizador']) ?> · falta <?= dinero($resumen['organizador_saldo']) ?>.
            <a href="<?= e(url('portal', ['t' => $token])) ?>#pagos">Ver mis pagos</a></p>
    <?php endif; ?>
    <ul class="pn-partidas">
        <?php foreach ($visibles as $p): $pid = (int) $p['id']; ?>
        <li class="pn-partida pn-<?= e($p['estado']) ?>" id="partida-<?= $pid ?>">
            <div class="pn-partida-cab">
                <span class="pn-icono" aria-hidden="true"><?= Planificacion::ICONOS[$p['categoria']] ?? '✨' ?></span>
                <div class="pn-partida-txt">
                    <strong><?= e($p['concepto']) ?></strong>
                    <small><?= $p['proveedor_nombre'] ? e($p['proveedor_nombre']) . ' · ' : '' ?><?= e(Planificacion::ESTADOS[$p['estado']]) ?></small>
                    <small><?= $p['paga'] === 'cliente' ? 'Le pagas directo al proveedor' : 'Incluido en tu contrato con ' . e($negocio['nombre']) ?></small>
                </div>
                <div class="pn-partida-num">
                    <?php if ($p['paga'] === 'planner'): ?><small>incluido</small><?php else: ?>
                    <strong><?= dinero($p['valor']) ?></strong>
                    <small><?= $p['contratado'] !== null ? 'contratado' : 'estimado' ?></small><?php endif; ?>
                    <?php if ($p['paga'] === 'cliente' && ($p['estado'] === 'contratado' || $p['pagado'] > 0) && $p['valor'] > 0): ?><small class="<?= $p['saldo'] > 0 ? '' : 'pn-ok' ?>"><?= $p['saldo'] > 0 ? 'falta ' . dinero($p['saldo']) : 'pagado ✓' ?></small><?php endif; ?>
                </div>
            </div>
            <?php if ($p['vence_pago'] && $p['saldo'] > 0): ?><p class="ayuda-chica <?= $p['vence_pago'] < $hoy ? 'pn-rojo' : 'suave' ?>">Pagar hasta el <?= e(fecha_corta($p['vence_pago'])) ?></p><?php endif; ?>
            <?php if ($p['paga'] === 'cliente' && ($p['estado'] !== 'idea' || !empty($pagosPor[$pid]))): ?>
            <details class="pn-detalle">
                <summary>Mis pagos a este proveedor<?= !empty($pagosPor[$pid]) ? ' (' . count($pagosPor[$pid]) . ')' : '' ?></summary>
                <?php if (!empty($pagosPor[$pid])): ?>
                <ul class="filas">
                    <?php foreach ($pagosPor[$pid] as $pg): ?>
                    <li><span><?= e(fecha_corta($pg['fecha'])) ?> · <?= dinero((float) $pg['monto']) ?><?= $pg['nota'] ? '<br><small>' . e($pg['nota']) . '</small>' : '' ?></span>
                        <?php if ($pg['origen'] === 'cliente' && $activo): ?>
                        <form method="post" action="<?= e(url('portal_plan', ['t' => $token])) ?>" class="derecha">
                            <?= campo_csrf() ?><input type="hidden" name="accion" value="pago_borrar"><input type="hidden" name="pago_id" value="<?= (int) $pg['id'] ?>">
                            <button class="boton boton-claro boton-chico" type="submit">Borrar</button>
                        </form>
                        <?php endif; ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
                <?php if ($activo && ($p['saldo'] > 0 || $p['valor'] <= 0)): ?>
                <form method="post" action="<?= e(url('portal_plan', ['t' => $token])) ?>" class="pn-en-linea">
                    <?= campo_csrf() ?><input type="hidden" name="accion" value="pago"><input type="hidden" name="partida_id" value="<?= $pid ?>">
                    <div class="campo"><label for="cp-m-<?= $pid ?>">Le pagué ($)</label><input type="text" inputmode="decimal" id="cp-m-<?= $pid ?>" name="monto" required></div>
                    <div class="campo"><label for="cp-f-<?= $pid ?>">Fecha</label><input type="date" id="cp-f-<?= $pid ?>" name="fecha" value="<?= e($hoy) ?>" max="<?= e($hoy) ?>" required></div>
                    <div class="campo"><label for="cp-me-<?= $pid ?>">Forma</label><select id="cp-me-<?= $pid ?>" name="metodo"><?php foreach (Planificacion::METODOS as $k => $tx): ?><option value="<?= e($k) ?>"><?= e($tx) ?></option><?php endforeach; ?></select></div>
                    <div class="campo"><label for="cp-n-<?= $pid ?>">Nota <small>(opcional)</small></label><input type="text" id="cp-n-<?= $pid ?>" name="nota" maxlength="200" placeholder="Ej. anticipo"></div>
                    <button class="boton boton-claro" type="submit">Anotar mi pago</button>
                </form>
                <?php endif; ?>
            </details>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</section>
