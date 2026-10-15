<?php
use TuPlanner\{Cotizaciones, Planificacion, WhatsApp};
/**
 * Pestaña "Planificación" del evento.
 * @var array $ev @var array $tareas @var array $grupos @var array $avance @var array $avanceCliente @var array $partidas @var array $pagosDirectos
 * @var ?array $resumen @var ?array $sugerido @var array $cotEditables @var array $proveedores @var array $carga @var bool $comercial
 * @var bool $verDinero @var int $editar @var float $comisionDefecto
 */
require RAIZ . '/app/vistas/_evento_tabs.php';
$hoy = date('Y-m-d');
$puede = puede_editar($u);
$num = fn(float $v) => number_format($v, 2, ',', '');
$pagosPor = [];
foreach ($pagosDirectos as $pg) $pagosPor[(int) $pg['partida_id']][] = $pg;
$activo = !in_array($ev['estado'], ['perdido', 'cancelado'], true);
?>
<?php if ($carga['cuantos'] > 0): ?>
    <p class="aviso <?= $carga['lleno'] ? 'aviso-error' : '' ?> pn-carga">
        Ese día tu equipo tiene <?= $carga['cuantos'] === 1 ? 'otro evento' : $carga['cuantos'] . ' eventos más' ?>:
        <?php foreach ($carga['eventos'] as $i => $o): ?><?= $i ? ', ' : '' ?><a href="<?= e(url('evento', ['id' => $o['id']])) ?>"><?= e($o['nombre']) ?></a> (<?= e(hora_corta($o['hora_inicio'])) ?>)<?php endforeach; ?>.
        <?= $carga['lleno'] ? 'Ya llegaste a tu máximo de ' . (int) $carga['maximo'] . ' por día (Configuración → Reglas).' : '' ?>
    </p>
<?php endif; ?>

<?php if ($verDinero && $resumen): ?>
<section class="bloque" id="dinero" aria-labelledby="pn-t-dinero">
    <h2 id="pn-t-dinero">Dinero del evento</h2>
    <div class="cifras pn-cifras">
        <div class="cifra-tarjeta"><small>Presupuesto del cliente</small><strong><?= $resumen['presupuesto_cliente'] !== null ? dinero($resumen['presupuesto_cliente']) : '—' ?></strong></div>
        <div class="cifra-tarjeta"><small><?= $resumen['organizador'] > 0 ? 'Costo total del evento' : 'Costo proyectado' ?></small><strong><?= dinero($resumen['proyectado']) ?></strong></div>
        <?php if ($resumen['diferencia'] !== null): ?>
            <div class="cifra-tarjeta <?= $resumen['diferencia'] < 0 ? 'alerta' : 'bien' ?>"><small><?= $resumen['diferencia'] < 0 ? 'Se pasa del presupuesto' : 'Queda del presupuesto' ?></small><strong><?= dinero(abs($resumen['diferencia'])) ?></strong></div>
        <?php endif; ?>
        <div class="cifra-tarjeta bien"><small>Ya pagado (todo)</small><strong><?= dinero($resumen['pagado']) ?></strong></div>
    </div>
    <div class="pn-reparto">
        <div>
            <h3>Con tu negocio (contrato)</h3>
            <p><?= dinero($resumen['organizador']) ?> · pagado <?= dinero($resumen['organizador_pagado']) ?> · falta <strong><?= dinero($resumen['organizador_saldo']) ?></strong></p>
            <?php if ($resumen['organizador'] <= 0): ?><p class="suave ayuda-chica">Aparece cuando el cliente acepta la cotización<?= $resumen['en_contrato'] > 0 ? ' (partidas que van en tu contrato: ' . dinero($resumen['en_contrato']) . ')' : '' ?>.</p><?php endif; ?>
        </div>
        <div>
            <h3>Directo a proveedores</h3>
            <p><?= dinero($resumen['directo']) ?> · pagado <?= dinero($resumen['directo_pagado']) ?> · falta <strong><?= dinero($resumen['directo_saldo']) ?></strong></p>
        </div>
        <?php if ($resumen['comision'] > 0): ?>
        <div>
            <h3>Tus comisiones</h3>
            <p><?= dinero($resumen['comision']) ?> · cobrado <?= dinero($resumen['comision_cobrada']) ?> · por cobrar <strong><?= dinero($resumen['comision_por_cobrar']) ?></strong></p>
        </div>
        <?php endif; ?>
    </div>
    <?php if ($resumen['total'] > 0): ?>
        <div class="barra" role="progressbar" aria-valuenow="<?= (int) $resumen['pct_pagado'] ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Avance de pagos de todo el evento"><span style="width:<?= (int) $resumen['pct_pagado'] ?>%"></span></div>
        <p class="suave ayuda-chica"><?= (int) $resumen['pct_pagado'] ?> % de todo el evento pagado. Falta <?= dinero($resumen['falta']) ?>.</p>
    <?php endif; ?>
</section>

<section class="bloque" id="honorarios" aria-labelledby="pn-t-hon">
    <h2 id="pn-t-hon">Tus honorarios</h2>
    <?php if ($comercial && $puede): ?>
    <form method="post" action="<?= e(url('evento_plan', ['id' => $ev['id']])) ?>" class="pn-en-linea">
        <?= campo_csrf() ?><input type="hidden" name="accion" value="honorario">
        <div class="campo">
            <label for="pn-modo">¿Cómo cobras en este evento?</label>
            <select id="pn-modo" name="modo">
                <?php foreach (Planificacion::MODOS_HONORARIO as $k => $t): ?><option value="<?= e($k) ?>" <?= $ev['honorario_modo'] === $k ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="campo">
            <label for="pn-pct">Porcentaje (%)</label>
            <input type="text" inputmode="decimal" id="pn-pct" name="pct" value="<?= e($num((float) $ev['honorario_pct'])) ?>">
        </div>
        <button class="boton boton-claro" type="submit">Guardar</button>
    </form>
    <?php endif; ?>
    <?php if ($ev['honorario_modo'] === 'porcentaje'): ?>
        <p><?= e(rtrim(rtrim($num((float) $sugerido['pct']), '0'), ',')) ?> % de <?= dinero($sugerido['base']) ?> (lo que suman las partidas) = <strong><?= dinero($sugerido['monto']) ?></strong> + IVA.</p>
    <?php else: ?>
        <p class="suave">Tarifa fija: escribe tu valor abajo y lo ponemos en la cotización. También puedes cargarlo como producto "Organización" en tu catálogo.</p>
    <?php endif; ?>
    <?php if ($comercial && $puede): ?>
        <?php if ($cotEditables): ?>
        <form method="post" action="<?= e(url('evento_plan', ['id' => $ev['id']])) ?>" class="pn-en-linea">
            <?= campo_csrf() ?><input type="hidden" name="accion" value="honorario_cotizacion">
            <div class="campo">
                <label for="pn-hmonto">Honorarios ($, sin IVA)</label>
                <input type="text" inputmode="decimal" id="pn-hmonto" name="monto" value="<?= e($ev['honorario_modo'] === 'porcentaje' ? $num($sugerido['monto']) : '') ?>" required>
            </div>
            <div class="campo">
                <label for="pn-hcot">En la cotización</label>
                <select id="pn-hcot" name="cotizacion_id">
                    <?php foreach ($cotEditables as $c): ?><option value="<?= (int) $c['id'] ?>">N.º <?= (int) $c['numero'] ?> versión <?= (int) $c['version'] ?> (<?= e(mb_strtolower(Cotizaciones::ESTADOS[$c['estado']])) ?>)</option><?php endforeach; ?>
                </select>
            </div>
            <button class="boton" type="submit">Poner en la cotización</button>
        </form>
        <p class="suave ayuda-chica">Si la cotización ya tiene una línea de honorarios, se reemplaza por la nueva.</p>
        <?php else: ?>
            <p class="suave ayuda-chica">Para poner los honorarios, <a href="<?= e(url('cotizacion', ['id' => $ev['id']])) ?>">crea una cotización</a> (o una versión nueva si ya fue aceptada).</p>
        <?php endif; ?>
    <?php endif; ?>
</section>

<section class="bloque" id="presupuesto" aria-labelledby="pn-t-pres">
    <h2 id="pn-t-pres">Presupuesto por proveedor <small class="suave">(<?= count(array_filter($partidas, fn($p) => $p['estado'] !== 'cancelado')) ?> partidas)</small></h2>
    <?php if (!$partidas): ?>
        <p class="vacio">Todavía no hay partidas. Anota cada cosa que hay que contratar (lugar, banquete, fotos, flores…) y quién le paga al proveedor.</p>
        <?php if ($comercial && $puede): ?>
        <form method="post" action="<?= e(url('evento_plan', ['id' => $ev['id']])) ?>" class="pn-en-linea pn-repartir">
            <?= campo_csrf() ?><input type="hidden" name="accion" value="repartir">
            <div class="campo">
                <label for="pn-pres">Presupuesto total del cliente ($)</label>
                <input type="text" inputmode="decimal" id="pn-pres" name="presupuesto" value="<?= e($ev['presupuesto_cliente'] !== null ? $num((float) $ev['presupuesto_cliente']) : '') ?>" required placeholder="Ej. 15.000">
            </div>
            <button class="boton" type="submit">Repartir con los porcentajes típicos</button>
        </form>
        <p class="suave ayuda-chica">Arma las partidas típicas de <?= e(mb_strtolower(\TuPlanner\Eventos::TIPOS[$ev['tipo']])) ?> (lugar, banquete, foto y video, decoración…) con un estimado. Después cambias lo que quieras.</p>
        <?php endif; ?>
    <?php else: ?>
        <ul class="pn-partidas">
        <?php foreach ($partidas as $p): $pid = (int) $p['id']; ?>
            <li class="pn-partida pn-<?= e($p['estado']) ?>" id="partida-<?= $pid ?>">
                <div class="pn-partida-cab">
                    <span class="pn-icono" aria-hidden="true"><?= Planificacion::ICONOS[$p['categoria']] ?? '✨' ?></span>
                    <div class="pn-partida-txt">
                        <strong><?= e($p['concepto']) ?></strong>
                        <small><?= e(Cotizaciones::CATEGORIAS[$p['categoria']] ?? $p['categoria']) ?><?= $p['proveedor_nombre'] ? ' · ' . e($p['proveedor_nombre']) : '' ?></small>
                        <span class="pn-etiquetas">
                            <span class="pn-etq pn-etq-<?= e($p['estado']) ?>"><?= e(Planificacion::ESTADOS[$p['estado']]) ?></span>
                            <span class="pn-etq"><?= $p['paga'] === 'cliente' ? 'Paga el cliente' : 'En tu contrato' ?></span>
                            <?php if (!$p['visible_cliente']): ?><span class="pn-etq">Oculta al cliente</span><?php endif; ?>
                        </span>
                    </div>
                    <div class="pn-partida-num">
                        <strong><?= dinero($p['valor']) ?></strong>
                        <small><?= $p['contratado'] !== null && $p['estado'] !== 'cancelado' ? 'contratado' : 'estimado' ?></small>
                        <?php if ($p['paga'] === 'cliente' && ($p['estado'] === 'contratado' || $p['pagado'] > 0) && $p['valor'] > 0): ?>
                            <small class="<?= $p['saldo'] > 0 ? '' : 'pn-ok' ?>"><?= $p['saldo'] > 0 ? 'falta ' . dinero($p['saldo']) : 'pagado ✓' ?></small>
                        <?php endif; ?>
                        <?php if ($p['comision_total'] > 0): ?><small>comisión <?= dinero($p['comision_total']) ?><?= $p['comision_por_cobrar'] > 0 ? '' : ' ✓' ?></small><?php endif; ?>
                    </div>
                </div>
                <?php if ($p['vence_pago'] && $p['saldo'] > 0): ?><p class="ayuda-chica <?= $p['vence_pago'] < $hoy ? 'pn-rojo' : 'suave' ?>">Pagarle hasta el <?= e(fecha_corta($p['vence_pago'])) ?></p><?php endif; ?>
                <?php if ($p['notas']): ?><p class="suave ayuda-chica"><?= e($p['notas']) ?></p><?php endif; ?>
                <?php if ($comercial && $puede): ?>
                <details class="pn-detalle" <?= $editar === $pid ? 'open' : '' ?>>
                    <summary>Cambiar, pagos y comisión</summary>
                    <?php $pf = $p; $pfId = 'p' . $pid; require RAIZ . '/app/vistas/_pn_partida_form.php'; ?>
                    <?php if ($p['paga'] === 'cliente'): ?>
                        <h3 class="pn-sub">Pagos del cliente a este proveedor</h3>
                        <?php if (!empty($pagosPor[$pid])): ?>
                        <ul class="filas">
                            <?php foreach ($pagosPor[$pid] as $pg): ?>
                            <li><span><?= e(fecha_corta($pg['fecha'])) ?> · <?= dinero((float) $pg['monto']) ?> · <?= e(Planificacion::METODOS[$pg['metodo']] ?? $pg['metodo']) ?>
                                <?= $pg['origen'] === 'cliente' ? '<small>(lo anotó el cliente)</small>' : '' ?><?= $pg['nota'] ? '<br><small>' . e($pg['nota']) . '</small>' : '' ?></span>
                                <form method="post" action="<?= e(url('evento_plan', ['id' => $ev['id']])) ?>" class="derecha">
                                    <?= campo_csrf() ?><input type="hidden" name="accion" value="pago_borrar"><input type="hidden" name="pago_id" value="<?= (int) $pg['id'] ?>">
                                    <button class="boton boton-claro boton-chico" type="submit" aria-label="Borrar el pago de <?= e(dinero((float) $pg['monto'])) ?>">Borrar</button>
                                </form></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                        <?php if ($p['estado'] !== 'cancelado' && ($p['saldo'] > 0 || $p['valor'] <= 0)): ?>
                        <form method="post" action="<?= e(url('evento_plan', ['id' => $ev['id']])) ?>" class="pn-en-linea">
                            <?= campo_csrf() ?><input type="hidden" name="accion" value="pago"><input type="hidden" name="partida_id" value="<?= $pid ?>">
                            <div class="campo"><label for="pg-m-<?= $pid ?>">Monto ($)</label><input type="text" inputmode="decimal" id="pg-m-<?= $pid ?>" name="monto" value="<?= e($p['saldo'] > 0 ? $num($p['saldo']) : '') ?>" required></div>
                            <div class="campo"><label for="pg-f-<?= $pid ?>">Fecha</label><input type="date" id="pg-f-<?= $pid ?>" name="fecha" value="<?= e($hoy) ?>" max="<?= e($hoy) ?>" required></div>
                            <div class="campo"><label for="pg-me-<?= $pid ?>">Forma</label><select id="pg-me-<?= $pid ?>" name="metodo"><?php foreach (Planificacion::METODOS as $k => $t): ?><option value="<?= e($k) ?>"><?= e($t) ?></option><?php endforeach; ?></select></div>
                            <div class="campo"><label for="pg-n-<?= $pid ?>">Nota <small>(opcional)</small></label><input type="text" id="pg-n-<?= $pid ?>" name="nota" maxlength="200" placeholder="Ej. anticipo 50 %"></div>
                            <button class="boton boton-claro" type="submit">Anotar pago</button>
                        </form>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="suave ayuda-chica">Va en tu contrato: el cliente te paga a ti (pestaña Pagos) y tú le pagas al proveedor en <a href="<?= e(url('compras', ['evento' => $ev['id']])) ?>">Compras</a>.</p>
                    <?php endif; ?>
                    <?php if ($p['comision_total'] > 0): ?>
                        <h3 class="pn-sub">Comisión del proveedor: <?= dinero($p['comision_total']) ?> (cobrado <?= dinero((float) $p['comision_cobrada']) ?>)</h3>
                        <?php if ($p['comision_por_cobrar'] > 0): ?>
                        <form method="post" action="<?= e(url('evento_plan', ['id' => $ev['id']])) ?>" class="pn-en-linea">
                            <?= campo_csrf() ?><input type="hidden" name="accion" value="comision"><input type="hidden" name="partida_id" value="<?= $pid ?>">
                            <div class="campo"><label for="cm-m-<?= $pid ?>">Te pagó ($)</label><input type="text" inputmode="decimal" id="cm-m-<?= $pid ?>" name="monto" value="<?= e($num($p['comision_por_cobrar'])) ?>" required></div>
                            <div class="campo"><label for="cm-f-<?= $pid ?>">Fecha</label><input type="date" id="cm-f-<?= $pid ?>" name="fecha" value="<?= e($hoy) ?>" required></div>
                            <button class="boton boton-claro" type="submit">Anotar comisión cobrada</button>
                        </form>
                        <?php endif; ?>
                        <?php if ((float) $p['comision_cobrada'] > 0): ?>
                        <form method="post" action="<?= e(url('evento_plan', ['id' => $ev['id']])) ?>">
                            <?= campo_csrf() ?><input type="hidden" name="accion" value="comision_anular"><input type="hidden" name="partida_id" value="<?= $pid ?>">
                            <button class="boton boton-claro boton-chico" type="submit">Borrar lo cobrado (si fue un error)</button>
                        </form>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($p['pagado'] <= 0 && (float) $p['comision_cobrada'] <= 0): ?>
                    <form method="post" action="<?= e(url('evento_plan', ['id' => $ev['id']])) ?>" class="pn-borrar">
                        <?= campo_csrf() ?><input type="hidden" name="accion" value="partida_borrar"><input type="hidden" name="partida_id" value="<?= $pid ?>">
                        <button class="boton boton-peligro boton-chico" type="submit">Borrar esta partida</button>
                    </form>
                    <?php endif; ?>
                </details>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <?php if ($comercial && $puede): ?>
    <details class="pn-nueva" <?= !$partidas ? '' : '' ?>>
        <summary class="boton boton-claro">+ Agregar partida</summary>
        <?php $pf = null; $pfId = 'pn'; require RAIZ . '/app/vistas/_pn_partida_form.php'; ?>
    </details>
    <?php endif; ?>
</section>
<?php endif; ?>

<section class="bloque" id="tareas" aria-labelledby="pn-t-tar">
    <h2 id="pn-t-tar">Lista de tareas <small class="suave"><?= (int) $avance['hechas'] ?> de <?= (int) $avance['total'] ?> hechas</small></h2>
    <?php if ($avance['total']): ?>
        <div class="barra" role="progressbar" aria-valuenow="<?= (int) $avance['pct'] ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Avance de tareas"><span style="width:<?= (int) $avance['pct'] ?>%"></span></div>
        <p class="suave ayuda-chica">
            <?php if ($avance['vencidas']): ?><span class="pn-rojo"><?= (int) $avance['vencidas'] ?> atrasada<?= $avance['vencidas'] === 1 ? '' : 's' ?></span> · <?php endif; ?>
            Del cliente: <?= (int) $avanceCliente['hechas'] ?> de <?= (int) $avanceCliente['total'] ?> (las marca en su portal).
        </p>
    <?php endif; ?>
    <?php if ($puede): ?>
    <form method="post" action="<?= e(url('evento_plan', ['id' => $ev['id']])) ?>" class="pn-lista-tipica">
        <?= campo_csrf() ?><input type="hidden" name="accion" value="lista_tipica">
        <button class="boton boton-claro boton-chico" type="submit">Cargar la lista típica de <?= e(mb_strtolower(\TuPlanner\Eventos::TIPOS[$ev['tipo']])) ?></button>
    </form>
    <?php endif; ?>
    <?php if (!$tareas): ?>
        <p class="vacio">Sin tareas. Carga la lista típica (con fechas contadas desde el día del evento) o agrega las tuyas.</p>
    <?php endif; ?>
    <?php foreach ($grupos as $titulo => $lista): ?>
        <h3 class="pn-mes"><?= e($titulo) ?></h3>
        <ul class="pn-tareas">
            <?php foreach ($lista as $t): $tid = (int) $t['id']; $atrasada = !$t['hecha'] && $t['vence'] && $t['vence'] < $hoy; ?>
            <li class="<?= $t['hecha'] ? 'pn-hecha' : '' ?>">
                <?php if ($puede): ?>
                <form method="post" action="<?= e(url('evento_plan', ['id' => $ev['id']])) ?>" class="pn-marcar">
                    <?= campo_csrf() ?><input type="hidden" name="accion" value="tarea_marcar"><input type="hidden" name="tarea_id" value="<?= $tid ?>">
                    <input type="hidden" name="hecha" value="<?= $t['hecha'] ? '0' : '1' ?>">
                    <button type="submit" class="pn-caja" aria-label="<?= $t['hecha'] ? 'Marcar como pendiente' : 'Marcar como hecha' ?>: <?= e($t['titulo']) ?>"><?= $t['hecha'] ? '✓' : '' ?></button>
                </form>
                <?php else: ?><span class="pn-caja" aria-hidden="true"><?= $t['hecha'] ? '✓' : '' ?></span><?php endif; ?>
                <div class="pn-tarea-txt">
                    <span><?= e($t['titulo']) ?></span>
                    <small><?= $t['vence'] ? '<span class="' . ($atrasada ? 'pn-rojo' : '') . '">' . e(fecha_corta($t['vence'])) . '</span> · ' : '' ?><span class="pn-etq pn-etq-<?= e($t['asignado']) ?>"><?= e(Planificacion::ASIGNADO[$t['asignado']]) ?></span><?= $t['hecha'] && $t['hecha_por'] === 'cliente' ? ' · la marcó el cliente' : '' ?></small>
                    <?php if ($t['notas']): ?><small><?= e($t['notas']) ?></small><?php endif; ?>
                    <?php if ($puede): ?>
                    <details class="pn-detalle">
                        <summary>Cambiar</summary>
                        <form method="post" action="<?= e(url('evento_plan', ['id' => $ev['id']])) ?>" class="pn-en-linea">
                            <?= campo_csrf() ?><input type="hidden" name="accion" value="tarea_editar"><input type="hidden" name="tarea_id" value="<?= $tid ?>">
                            <div class="campo"><label for="t-t-<?= $tid ?>">Tarea</label><input type="text" id="t-t-<?= $tid ?>" name="titulo" value="<?= e($t['titulo']) ?>" maxlength="150" required></div>
                            <div class="campo"><label for="t-v-<?= $tid ?>">Hasta</label><input type="date" id="t-v-<?= $tid ?>" name="vence" value="<?= e((string) $t['vence']) ?>"></div>
                            <div class="campo"><label for="t-a-<?= $tid ?>">Para</label><select id="t-a-<?= $tid ?>" name="asignado"><?php foreach (Planificacion::ASIGNADO as $k => $tx): ?><option value="<?= e($k) ?>" <?= $t['asignado'] === $k ? 'selected' : '' ?>><?= e($tx) ?></option><?php endforeach; ?></select></div>
                            <div class="campo"><label for="t-n-<?= $tid ?>">Nota</label><input type="text" id="t-n-<?= $tid ?>" name="notas" value="<?= e((string) $t['notas']) ?>" maxlength="300"></div>
                            <button class="boton boton-claro boton-chico" type="submit">Guardar</button>
                        </form>
                        <form method="post" action="<?= e(url('evento_plan', ['id' => $ev['id']])) ?>">
                            <?= campo_csrf() ?><input type="hidden" name="accion" value="tarea_borrar"><input type="hidden" name="tarea_id" value="<?= $tid ?>">
                            <button class="boton boton-peligro boton-chico" type="submit">Borrar tarea</button>
                        </form>
                    </details>
                    <?php endif; ?>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
    <?php endforeach; ?>
    <?php if ($puede): ?>
    <form method="post" action="<?= e(url('evento_plan', ['id' => $ev['id']])) ?>" class="pn-en-linea pn-nueva-tarea">
        <?= campo_csrf() ?><input type="hidden" name="accion" value="tarea">
        <div class="campo"><label for="pn-tt">Nueva tarea</label><input type="text" id="pn-tt" name="titulo" maxlength="150" required placeholder="Ej. Prueba de menú con los novios"></div>
        <div class="campo"><label for="pn-tv">Hasta</label><input type="date" id="pn-tv" name="vence"></div>
        <div class="campo"><label for="pn-ta">Para</label><select id="pn-ta" name="asignado"><?php foreach (Planificacion::ASIGNADO as $k => $tx): ?><option value="<?= e($k) ?>"><?= e($tx) ?></option><?php endforeach; ?></select></div>
        <button class="boton" type="submit">Agregar</button>
    </form>
    <?php endif; ?>
</section>
