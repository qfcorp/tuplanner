<?php
/**
 * Organizador de bodas y eventos (migración 100):
 *  - Evento sin lugar ("por definir"), tipos nuevos, honorarios del negocio en el evento.
 *  - Lista de tareas por meses: plantilla por tipo, lista típica, del cliente / del organizador, marcar desde el portal.
 *  - Presupuesto: partidas, reparto típico, pagos directos del cliente, saldo, reventa, comisiones, honorarios por %.
 *  - Resumen del dinero (sistema y cliente: el cliente no ve costos de reventa ni comisiones).
 *  - Capacidad del equipo por día y disponibilidad pública.
 *  - Ideas: fotos, me encanta, paleta, límites, quién borra qué.
 *  - Aislamiento entre negocios.
 */
use TuPlanner\{Configuracion, Cotizaciones, Eventos, Inspiracion, Pagos, Planificacion, Reportes};

$db = base_limpia();
planes_de_antes($db);   // estas pruebas usan los planes de antes (Esencial $19, Planner $39, Agencia $69); los de $20 y $45: logica_112
$hoy = new DateTimeImmutable('today');
[$nid, $dueno] = negocio_prueba($db);
[$nidB, $duenoB] = negocio_prueba($db, 'salon', 'Otra Planner');
$hacienda = espacio_id($db, $nid, 'Hacienda La Herradura');
$ev = new Eventos($db);
$cot = new Cotizaciones($db);
$pag = new Pagos($db);
$plan = new Planificacion($db);
$ins = new Inspiracion($db);
$fecha = $hoy->modify('+300 days')->format('Y-m-d');

titulo('1. Datos de ejemplo del organizador');
check('Lista de tareas de boda en la plantilla', count(Planificacion::LISTA_TIPICA['boda']),
      (int) $db->query("SELECT count(*) FROM plantilla_tareas WHERE negocio_id = $nid AND tipo_evento = 'boda'")->fetchColumn());
check('Lista general (los demás eventos)', count(Planificacion::LISTA_TIPICA['otro']),
      (int) $db->query("SELECT count(*) FROM plantilla_tareas WHERE negocio_id = $nid AND tipo_evento IS NULL")->fetchColumn());
check('Hay tareas para el cliente en la plantilla', true,
      (int) $db->query("SELECT count(*) FROM plantilla_tareas WHERE negocio_id = $nid AND asignado = 'cliente'")->fetchColumn() > 5);
check('Servicio "Organización integral" en el catálogo', 'honorarios',
      $db->query("SELECT categoria FROM productos WHERE negocio_id = $nid AND nombre = 'Organización integral'")->fetchColumn());
check('Lugares de ejemplo con dirección y tipo', 'ceremonia',
      $db->query("SELECT tipo_lugar FROM espacios WHERE negocio_id = $nid AND nombre = 'Capilla del Valle'")->fetchColumn());
check('Reglas por defecto: 2 eventos por día, tarifa fija, 10 % de comisión', ['2', 'fija', '10.00'],
      array_values($db->query("SELECT max_eventos_dia::text, honorario_modo, comision_pct::text FROM negocios WHERE id = $nid")->fetch(PDO::FETCH_NUM) ?: []));
check('Plan Planner sin límite de lugares', null, $db->query("SELECT max_espacios FROM planes WHERE codigo = 'salon'")->fetchColumn());
check('Nombres de los planes (Esencial = solo la base, Anexo B)', ['Esencial', 'Planner', 'Agencia'], $db->query("SELECT nombre FROM planes ORDER BY precio_mensual")->fetchAll(PDO::FETCH_COLUMN));

titulo('2. Evento sin lugar y tipos nuevos');
$boda = $ev->crear($nid, ['cliente' => ['nombre' => 'Ana Torres', 'telefono' => '0991112233'], 'tipo' => 'boda', 'fecha' => $fecha,
                          'hora_inicio' => '17:00', 'hora_fin' => '01:00', 'invitados' => 150, 'espacios' => []], (int) $dueno['id'], $hoy);
$E = $ev->obtener($nid, $boda);
check('Lugar por definir', 'Lugar por definir', $E['espacios_texto']);
check('Sin lugares guardados', 0, (int) $db->query("SELECT count(*) FROM evento_espacios WHERE evento_id = $boda")->fetchColumn());
check('Tareas de la boda copiadas', count(Planificacion::LISTA_TIPICA['boda']), count($plan->tareas($boda)));
check('Algunas son del cliente', true, count($plan->tareas($boda, 'cliente')) > 5);
check('Honorarios del negocio: tarifa fija', 'fija', $E['honorario_modo']);
$pedida = $ev->crear($nid, ['cliente' => ['nombre' => 'Luis Mena', 'telefono' => '0992223344'], 'tipo' => 'pedida', 'fecha' => $fecha,
                            'invitados' => 30, 'espacios' => []], (int) $dueno['id'], $hoy);
check('Tipo nuevo: pedida de mano', 'Pedida de mano de Luis Mena', $ev->obtener($nid, $pedida)['nombre']);
check('Sin lista propia: usa la general', count(Planificacion::LISTA_TIPICA['otro']), count($plan->tareas($pedida)));
$baby = $ev->crear($nid, ['cliente' => ['nombre' => 'Sofía Ruiz', 'telefono' => '0993334455'], 'tipo' => 'baby_shower', 'fecha' => $fecha,
                          'invitados' => 25, 'espacios' => [$hacienda]], (int) $dueno['id'], $hoy);
check('Tipo nuevo: baby shower con lugar', 'Hacienda La Herradura', $ev->obtener($nid, $baby)['espacios_texto']);
// Lugar después
$ev->actualizar($nid, $boda, ['tipo' => 'boda', 'fecha' => $fecha, 'hora_inicio' => '17:00', 'hora_fin' => '01:00', 'invitados' => 150,
                              'espacios' => [$hacienda], 'nombre' => $E['nombre']]);
check('Lugar elegido después', 'Hacienda La Herradura', $ev->obtener($nid, $boda)['espacios_texto']);
$ev->actualizar($nid, $boda, ['tipo' => 'boda', 'fecha' => $fecha, 'hora_inicio' => '17:00', 'hora_fin' => '01:00', 'invitados' => 150,
                              'espacios' => [], 'nombre' => $E['nombre']]);
check('Y se puede volver a "por definir"', 'Lugar por definir', $ev->obtener($nid, $boda)['espacios_texto']);

titulo('3. Tareas por meses');
$grupos = Planificacion::porMeses($plan->tareas($boda), $fecha);
check('Primer grupo: 6 meses o más antes', true, (bool) preg_match('/meses antes/', array_key_first($grupos)));
check('Hay grupo "El último mes"', true, isset($grupos['El último mes']));
check('Hay grupo "Después del evento"', true, isset($grupos['Después del evento']));
check('El de después va al final', 'Después del evento', array_key_last($grupos));
$t = $plan->agregarTarea($nid, $boda, 'Elegir los anillos', $hoy->modify('+60 days')->format('Y-m-d'), 'cliente', (int) $dueno['id'], 'Dimitry');
check('Tarea nueva para el cliente', 'cliente', $db->query("SELECT asignado FROM evento_tareas WHERE id = $t")->fetchColumn());
check_error('Fecha mala', 'Revisa la fecha', fn() => $plan->agregarTarea($nid, $boda, 'X tarea', '2026-02-30', 'planner'));
check_error('Para quién no válido', 'para quién', fn() => $plan->agregarTarea($nid, $boda, 'X tarea', null, 'jefe'));
check_error('Título vacío', 'Escribe la tarea', fn() => $plan->agregarTarea($nid, $boda, ' ', null));
check_error('Evento de otro negocio', 'No se encontró el evento', fn() => $plan->agregarTarea($nidB, $boda, 'Hackear', null));
$tPlanner = (int) $db->query("SELECT id FROM evento_tareas WHERE evento_id = $boda AND asignado = 'planner' ORDER BY id LIMIT 1")->fetchColumn();
check_error('El cliente no marca tareas del organizador', 'la marca tu organizador', fn() => $plan->marcarTarea($nid, $boda, $tPlanner, true, 'cliente'));
$plan->marcarTarea($nid, $boda, $t, true, 'cliente');
check('El cliente marca la suya', ['true', 'cliente'], array_values($db->query("SELECT hecha::text, hecha_por FROM evento_tareas WHERE id = $t")->fetch(PDO::FETCH_NUM)));
check('Queda en el historial como Cliente', 'Cliente', $db->query("SELECT quien FROM registro_cambios WHERE evento_id = $boda AND accion LIKE 'Tarea hecha: Elegir los anillos'")->fetchColumn());
$plan->marcarTarea($nid, $boda, $t, false, 'cliente');
check('Y la puede desmarcar', 'false', $db->query("SELECT hecha::text FROM evento_tareas WHERE id = $t")->fetchColumn());
check_error('Tarea de otro evento', 'No se encontró la tarea', fn() => $plan->marcarTarea($nid, $pedida, $t, true));
$av = $plan->avance($boda, 'cliente');
check('Avance del cliente', [0, count($plan->tareas($boda, 'cliente'))], [$av['hechas'], $av['total']]);
$plan->editarTarea($nid, $boda, $t, 'Elegir y pagar los anillos', null, 'planner', 'En la joyería del centro');
check('Editar: sin fecha, para el organizador, con nota', ['Elegir y pagar los anillos', null, 'planner', 'En la joyería del centro'],
      array_values($db->query("SELECT titulo, vence, asignado, notas FROM evento_tareas WHERE id = $t")->fetch(PDO::FETCH_NUM)));
$plan->borrarTarea($nid, $boda, $t);
check('Borrada', 0, (int) $db->query("SELECT count(*) FROM evento_tareas WHERE id = $t")->fetchColumn());
check('Lista típica: no repite las que ya están', 0, $plan->cargarListaTipica($nid, $boda, $hoy));
$db->exec("DELETE FROM evento_tareas WHERE evento_id = $pedida");
check('Lista típica en evento vacío (pedida → lista de boda)', count(Planificacion::LISTA_TIPICA['boda']), $plan->cargarListaTipica($nid, $pedida, $hoy));
$min = $db->query("SELECT min(vence) FROM evento_tareas WHERE evento_id = $pedida")->fetchColumn();
check('Ninguna fecha antes de hoy', true, $min >= $hoy->format('Y-m-d'));
// Evento cercano: las tareas que ya debían estar hechas quedan para hoy
$cerca = $ev->crear($nid, ['cliente' => ['nombre' => 'Pía Lara', 'telefono' => '0994445566'], 'tipo' => 'quince', 'fecha' => $hoy->modify('+20 days')->format('Y-m-d'),
                           'invitados' => 100, 'espacios' => []], (int) $dueno['id'], $hoy);
check('Evento en 20 días: tareas viejas para hoy', $hoy->format('Y-m-d'), $db->query("SELECT min(vence) FROM evento_tareas WHERE evento_id = $cerca")->fetchColumn());

titulo('4. Plantilla de tareas con "para quién" (Configuración)');
$conf = new Configuracion($db);
$tp = $conf->guardarTarea($nid, ['titulo' => 'Mandar la playlist al DJ', 'dias_antes' => '20', 'tipo_evento' => 'boda', 'asignado' => 'cliente']);
check('Plantilla: tarea para el cliente', 'cliente', $db->query("SELECT asignado FROM plantilla_tareas WHERE id = $tp")->fetchColumn());
$conf->guardarTarea($nid, ['titulo' => 'Mandar la playlist al DJ', 'dias_antes' => '20', 'tipo_evento' => 'boda', 'asignado' => 'raro'], $tp);
check('Para quién raro → organizador', 'planner', $db->query("SELECT asignado FROM plantilla_tareas WHERE id = $tp")->fetchColumn());
$conf->guardarReglas($nid, ['iva_pct' => '15', 'validez_cotizacion' => '15', 'dias_retencion' => '5', 'reserva_pct' => '30', 'cuotas_defecto' => '3',
                            'dias_saldo_antes' => '15', 'garantia_defecto' => '0', 'horas_montaje' => '3', 'horas_desmontaje' => '1', 'hora_limite' => '02:00',
                            'invitados_por_mesero' => '15', 'meses_revisar_precio' => '6', 'comision_vendedor_pct' => '0', 'condiciones' => '',
                            'max_eventos_dia' => '3', 'honorario_modo' => 'porcentaje', 'honorario_pct' => '12', 'comision_pct' => '8']);
check('Reglas del organizador guardadas', ['3', 'porcentaje', '12.00', '8.00'],
      array_values($db->query("SELECT max_eventos_dia::text, honorario_modo, honorario_pct::text, comision_pct::text FROM negocios WHERE id = $nid")->fetch(PDO::FETCH_NUM)));
check_error('Máximo de eventos por día 0', 'máximo de eventos', fn() => $conf->guardarReglas($nid, ['iva_pct' => '15', 'validez_cotizacion' => '15', 'dias_retencion' => '5',
    'reserva_pct' => '30', 'cuotas_defecto' => '3', 'dias_saldo_antes' => '15', 'garantia_defecto' => '0', 'horas_montaje' => '3', 'horas_desmontaje' => '1',
    'hora_limite' => '02:00', 'invitados_por_mesero' => '15', 'meses_revisar_precio' => '6', 'comision_vendedor_pct' => '0', 'condiciones' => '',
    'max_eventos_dia' => '0', 'honorario_modo' => 'fija', 'honorario_pct' => '10', 'comision_pct' => '10']));
$conPct = $ev->crear($nid, ['cliente' => ['nombre' => 'Rita Paz', 'telefono' => '0995556677'], 'tipo' => 'corporativo', 'fecha' => $fecha, 'invitados' => 60, 'espacios' => []],
                     (int) $dueno['id'], $hoy);
check('Evento nuevo hereda honorarios por %', ['porcentaje', 12.0], [$ev->obtener($nid, $conPct)['honorario_modo'], (float) $ev->obtener($nid, $conPct)['honorario_pct']]);

titulo('5. Presupuesto: repartir y partidas');
check_error('Repartir sin presupuesto', 'presupuesto total', fn() => $plan->repartir($nid, $boda, 0));
check('Repartir 20.000 en partidas típicas de boda', count(Planificacion::REPARTO['boda']), $plan->repartir($nid, $boda, 20000, (int) $dueno['id'], 'Dimitry'));
check('Las partidas suman exactamente el presupuesto', 20000.0, (float) $db->query("SELECT sum(estimado) FROM presupuesto_partidas WHERE evento_id = $boda")->fetchColumn());
check('Guarda el presupuesto del cliente en el evento', 20000.0, (float) $ev->obtener($nid, $boda)['presupuesto_cliente']);
check('Las partidas llevan la comisión del negocio (8 %)', 8.0, (float) $db->query("SELECT comision_pct FROM presupuesto_partidas WHERE evento_id = $boda AND categoria = 'foto_video'")->fetchColumn());
check('Imprevistos sin comisión', 0.0, (float) $db->query("SELECT comision_pct FROM presupuesto_partidas WHERE evento_id = $boda AND categoria = 'otros'")->fetchColumn());
check_error('No reparte dos veces', 'ya tiene partidas', fn() => $plan->repartir($nid, $boda, 20000));
$foto = (int) $db->query("SELECT id FROM presupuesto_partidas WHERE evento_id = $boda AND categoria = 'foto_video'")->fetchColumn();
$banq = (int) $db->query("SELECT id FROM presupuesto_partidas WHERE evento_id = $boda AND categoria = 'menu'")->fetchColumn();
$prov = (int) $db->query("INSERT INTO proveedores (negocio_id, nombre, servicio, telefono) VALUES ($nid, 'Foto Estudio Luz', 'foto_video', '0998887766') RETURNING id")->fetchColumn();
$provB = (int) $db->query("INSERT INTO proveedores (negocio_id, nombre, servicio) VALUES ($nidB, 'Proveedor Ajeno', 'menu') RETURNING id")->fetchColumn();
$plan->guardarPartida($nid, $boda, ['categoria' => 'foto_video', 'concepto' => 'Fotografía y video', 'proveedor_id' => $prov, 'estado' => 'contratado',
                                    'paga' => 'cliente', 'estimado' => '2.000', 'contratado' => '1.800,00', 'comision_pct' => '10', 'vence_pago' => $hoy->modify('+100 days')->format('Y-m-d')], $foto);
$P = $plan->partida($nid, $foto);
check('Contratado con proveedor de la lista', ['Foto Estudio Luz', 1800.0, 1800.0], [$P['proveedor_nombre'], (float) $P['contratado'], $P['valor']]);
check('Comisión 10 % de lo contratado', 180.0, $P['comision_total']);
check('Saldo = todo (sin pagos)', 1800.0, $P['saldo']);
check_error('Proveedor de otro negocio', 'No se encontró el proveedor', fn() => $plan->guardarPartida($nid, $boda, ['concepto' => 'Banquete', 'proveedor_id' => $provB], $banq));
check_error('Categoría inválida', 'categoría', fn() => $plan->guardarPartida($nid, $boda, ['concepto' => 'X', 'categoria' => 'cohetes']));
check_error('Comisión de más de 100 %', '100 %', fn() => $plan->guardarPartida($nid, $boda, ['concepto' => 'X cosa', 'comision_pct' => '150']));
check_error('Monto negativo', 'Revisa lo estimado', fn() => $plan->guardarPartida($nid, $boda, ['concepto' => 'X cosa', 'estimado' => '-5']));
check_error('Partida de otro evento', 'No se encontró la partida', fn() => $plan->guardarPartida($nid, $pedida, ['concepto' => 'Fotos'], $foto));
// Contratado sin precio: toma el estimado
$plan->guardarPartida($nid, $boda, ['categoria' => 'menu', 'concepto' => 'Banquete', 'estado' => 'contratado', 'paga' => 'planner', 'estimado' => '5.600',
                                    'comision_pct' => '0', 'proveedor_nombre' => 'Catering Sabores'], $banq);
check('Contratado sin precio: usa el estimado; va en el contrato (reventa)', [5600.0, 'planner', 'Catering Sabores'],
      [(float) $plan->partida($nid, $banq)['contratado'], $plan->partida($nid, $banq)['paga'], $plan->partida($nid, $banq)['proveedor_nombre']]);
$flores = $plan->guardarPartida($nid, $boda, ['categoria' => 'flores', 'concepto' => 'Flores de la iglesia', 'estado' => 'cotizando', 'paga' => 'cliente',
                                              'estimado' => '450', 'comision_monto' => '40', 'visible_cliente' => '0']);
check('Partida oculta al cliente', false, (bool) $plan->partida($nid, $flores)['visible_cliente']);
check('Comisión fija: no cuenta hasta contratar', 0.0, $plan->partida($nid, $flores)['comision_total']);

titulo('6. Pagos directos del cliente a proveedores');
check_error('Pago a partida de reventa', 'contrato del organizador', fn() => $plan->registrarPago($nid, $boda, $banq, '100', $hoy->format('Y-m-d')));
check_error('Pago mayor que el saldo', 'Es más de lo que falta', fn() => $plan->registrarPago($nid, $boda, $foto, '2.000', $hoy->format('Y-m-d')));
check_error('Fecha futura', 'no puede ser futura', fn() => $plan->registrarPago($nid, $boda, $foto, '100', $hoy->modify('+1 day')->format('Y-m-d'), 'transferencia', '', 'planner', null, 'X', $hoy));
check_error('Monto 0', 'cuánto se pagó', fn() => $plan->registrarPago($nid, $boda, $foto, '0', $hoy->format('Y-m-d')));
$p1 = $plan->registrarPago($nid, $boda, $foto, '900', $hoy->format('Y-m-d'), 'transferencia', 'Anticipo 50 %', 'planner', (int) $dueno['id'], 'Dimitry', $hoy);
$p2 = $plan->registrarPago($nid, $boda, $foto, '400', $hoy->format('Y-m-d'), 'deuna', '', 'cliente', null, 'X', $hoy);
check('Pagado y saldo', [1300.0, 500.0], [$plan->partida($nid, $foto)['pagado'], $plan->partida($nid, $foto)['saldo']]);
check_error('El cliente no paga partidas ocultas', 'No se encontró la partida', fn() => $plan->registrarPago($nid, $boda, $flores, '50', $hoy->format('Y-m-d'), 'deuna', '', 'cliente'));
check_error('El cliente no borra pagos del organizador', 'lo anotó tu organizador', fn() => $plan->borrarPago($nid, $boda, $p1, 'cliente'));
$plan->borrarPago($nid, $boda, $p2, 'cliente');
check('El cliente borra el suyo', 900.0, $plan->partida($nid, $foto)['pagado']);
check_error('No se borra una partida con pagos', 'Tiene pagos', fn() => $plan->borrarPartida($nid, $boda, $foto));
check_error('No pasa a reventa con pagos del cliente', 'Bórralos antes', fn() => $plan->guardarPartida($nid, $boda, ['categoria' => 'foto_video', 'concepto' => 'Fotografía y video',
    'estado' => 'contratado', 'paga' => 'planner', 'estimado' => '2000', 'contratado' => '1800', 'comision_pct' => '10'], $foto));
check_error('Pago desde otro negocio', 'No se encontró la partida', fn() => $plan->registrarPago($nidB, $boda, $foto, '10', $hoy->format('Y-m-d')));

titulo('7. Comisiones');
check_error('Cobrar más de la comisión', 'Es más de lo que falta', fn() => $plan->cobrarComision($nid, $foto, '200'));
check_error('Partida sin comisión', 'no tiene comisión', fn() => $plan->cobrarComision($nid, $banq, '10'));
check('Cobro parcial', 100.0, $plan->cobrarComision($nid, $foto, '100', $hoy->format('Y-m-d'), (int) $dueno['id'], 'Dimitry'));
check('Por cobrar', 80.0, $plan->partida($nid, $foto)['comision_por_cobrar']);
check('Lista de comisiones por cobrar (evento sin separar no aparece)', 0, count($plan->comisiones($nid)));
$db->exec("UPDATE eventos SET estado = 'separado' WHERE id = $boda");
$lista = $plan->comisiones($nid);
check('Evento separado: aparece en por cobrar', [1, 80.0], [count($lista), $lista[0]['comision_por_cobrar'] ?? 0]);
check('Cobradas este año', 1, count($plan->comisiones($nid, 'cobradas', $hoy->format('Y') . '-01-01', $hoy->format('Y') . '-12-31')));
check('Total cobrado en el periodo', 100.0, $plan->comisionesCobradas($nid, $hoy->format('Y') . '-01-01', $hoy->format('Y') . '-12-31'));
check('Otro negocio no ve comisiones', 0, count($plan->comisiones($nidB)));
// Bajar el precio contratado deja lo cobrado en el máximo
$plan->guardarPartida($nid, $boda, ['categoria' => 'foto_video', 'concepto' => 'Fotografía y video', 'proveedor_id' => $prov, 'estado' => 'contratado', 'paga' => 'cliente',
                                    'estimado' => '2000', 'contratado' => '1500', 'comision_pct' => '5'], $foto);
check('Comisión bajó a 75: lo cobrado se ajusta', [75.0, 75.0, 0.0],
      [$plan->partida($nid, $foto)['comision_total'], (float) $plan->partida($nid, $foto)['comision_cobrada'], $plan->partida($nid, $foto)['comision_por_cobrar']]);
$plan->anularComision($nid, $foto);
check('Anular lo cobrado', 0.0, (float) $plan->partida($nid, $foto)['comision_cobrada']);
check_error('No se borra una partida con comisión cobrada', 'Ya cobraste', function () use ($plan, $nid, $boda, $flores) {
    $plan->guardarPartida($nid, $boda, ['categoria' => 'flores', 'concepto' => 'Flores de la iglesia', 'estado' => 'contratado', 'paga' => 'cliente', 'estimado' => '450',
                                        'comision_monto' => '40', 'visible_cliente' => '0'], $flores);
    $plan->cobrarComision($nid, $flores, '40');
    $plan->borrarPartida($nid, $boda, $flores);
});

titulo('8. Honorarios por porcentaje y cotización');
$plan->guardarHonorario($nid, $boda, 'porcentaje', '10', (int) $dueno['id'], 'Dimitry');
$h = $plan->honorarioSugerido($nid, $boda);
$base = round(array_sum(array_map(fn($p) => $p['valor'], array_filter($plan->partidas($nid, $boda), fn($p) => $p['categoria'] !== 'honorarios'))), 2);
check('Base = suma de partidas', $base, $h['base']);
check('10 % de la base', round($base * 0.10, 2), $h['monto']);
check_error('Porcentaje 0', 'entre 1 y 100', fn() => $plan->guardarHonorario($nid, $boda, 'porcentaje', '0'));
check_error('Modo raro', 'Elige cómo', fn() => $plan->guardarHonorario($nid, $boda, 'trueque', '10'));
$c = $cot->crear($nid, $boda, null, (int) $dueno['id'], $hoy);
$plan->honorarioACotizacion($nid, $boda, $c, $h['monto'], (int) $dueno['id'], 'Dimitry');
$items = $cot->items($c);
$lineasH = array_values(array_filter($items, fn($i) => $i['categoria'] === 'honorarios'));
check('Una línea de honorarios con el monto', [1, $h['monto']], [count($lineasH), (float) $lineasH[0]['precio_unitario']]);
check('La descripción dice el %', true, str_contains($lineasH[0]['descripcion'], '10 % del presupuesto'));
$plan->honorarioACotizacion($nid, $boda, $c, 2500, (int) $dueno['id'], 'Dimitry');
check('Volver a ponerla la reemplaza (no duplica)', [1, 2500.0], [count(array_filter($cot->items($c), fn($i) => $i['categoria'] === 'honorarios')),
      (float) array_values(array_filter($cot->items($c), fn($i) => $i['categoria'] === 'honorarios'))[0]['precio_unitario']]);
check('El total de la cotización incluye los honorarios con IVA', true, (float) $cot->obtener($nid, $c)['total'] >= 2875.0);
check_error('Cotización de otro evento', 'No se encontró la cotización', fn() => $plan->honorarioACotizacion($nid, $pedida, $c, 100));
check_error('Honorario 0', 'mayores que 0', fn() => $plan->honorarioACotizacion($nid, $boda, $c, 0));
// Aceptada: ya no se cambia
$cot->enviar($nid, $c, $dueno);
$cot->aceptar($nid, $c, 'Ana Torres', '1.1.1.1', null, $hoy);
check_error('Cotización aceptada no se toca', 'Crea una nueva versión', fn() => $plan->honorarioACotizacion($nid, $boda, $c, 3000));
check('La línea sigue (no se borró al fallar)', 1, count(array_filter($cot->items($c), fn($i) => $i['categoria'] === 'honorarios')));

titulo('9. Resumen del dinero (sistema y cliente)');
$total = (float) $ev->obtener($nid, $boda)['total'];
$R = $plan->resumen($nid, $boda);
check('Con el organizador = cotización aceptada', $total, $R['organizador']);
$directo = round(array_sum(array_map(fn($p) => $p['valor'], array_filter($plan->partidas($nid, $boda), fn($p) => $p['paga'] === 'cliente' && $p['estado'] !== 'cancelado'))), 2);
check('Directo = partidas que paga el cliente', $directo, $R['directo']);
check('Total = contrato + directo', round($total + $directo, 2), $R['total']);
check('Pagado directo', 900.0, $R['directo_pagado']);
check('Diferencia con el presupuesto', round(20000 - $R['proyectado'], 2), $R['diferencia']);
check('Comisiones en el resumen del sistema', true, $R['comision'] > 0);
$RC = $plan->resumen($nid, $boda, true);
check('El cliente no ve las partidas ocultas', false, in_array($flores, array_column($plan->partidas($nid, $boda, true), 'id'), true));
check('El cliente no ve comisiones ni costo de reventa', [0.0, 0.0, []], [$RC['comision'], $RC['en_contrato'], $RC['por_categoria']]);
check('Para el cliente el directo no incluye lo oculto', round($directo - 450, 2), $RC['directo']);
// Antes de aceptar: al cliente no se le suma el costo de reventa
$sinCot = $ev->crear($nid, ['cliente' => ['nombre' => 'Eva Gil', 'telefono' => '0996667788'], 'tipo' => 'boda', 'fecha' => $fecha, 'invitados' => 90, 'espacios' => []],
                     (int) $dueno['id'], $hoy);
$plan->guardarPartida($nid, $sinCot, ['categoria' => 'menu', 'concepto' => 'Banquete', 'paga' => 'planner', 'estimado' => '4000']);
$plan->guardarPartida($nid, $sinCot, ['categoria' => 'musica', 'concepto' => 'DJ', 'paga' => 'cliente', 'estimado' => '700']);
check('Sistema: proyectado = reventa + directo', 4700.0, $plan->resumen($nid, $sinCot)['proyectado']);
check('Cliente: proyectado = solo lo directo', 700.0, $plan->resumen($nid, $sinCot, true)['proyectado']);
check('Resumen de evento ajeno', 'No se encontró el evento.', (function () use ($plan, $nidB, $boda) { try { $plan->resumen($nidB, $boda); return 'sin error'; } catch (RuntimeException $e) { return $e->getMessage(); } })());

titulo('10. Capacidad del equipo y página pública');
$dia = $hoy->modify('+90 days')->format('Y-m-d');
$db->exec("UPDATE negocios SET max_eventos_dia = 2 WHERE id = $nid");
check('Día libre', 'libre', $plan->disponibilidadPublica($nid, $dia));
$e1 = $ev->crear($nid, ['cliente' => ['nombre' => 'A Uno', 'telefono' => '0990000001'], 'tipo' => 'boda', 'fecha' => $dia, 'invitados' => 50, 'espacios' => []], null, $hoy);
check('Una consulta no ocupa cupo', 'libre', $plan->disponibilidadPublica($nid, $dia));
$ev->cambiarEstado($nid, $e1, 'separado', [], $dueno, $hoy);
check('Uno separado de 2: queda un cupo', 'consultar', $plan->disponibilidadPublica($nid, $dia));
$e2 = $ev->crear($nid, ['cliente' => ['nombre' => 'B Dos', 'telefono' => '0990000002'], 'tipo' => 'quince', 'fecha' => $dia, 'invitados' => 50, 'espacios' => []], null, $hoy);
$ev->cambiarEstado($nid, $e2, 'separado', [], $dueno, $hoy);
check('Dos separados: sin cupo', 'ocupado', $plan->disponibilidadPublica($nid, $dia));
$carga = $plan->cargaDelDia($nid, $dia, $e1);
check('Carga del día sin contarse a sí mismo', [1, 2, false], [$carga['cuantos'], $carga['maximo'], $carga['lleno']]);
check('Otro negocio: su día está libre', 'libre', $plan->disponibilidadPublica($nidB, $dia));
$otro = $hoy->modify('+91 days')->format('Y-m-d');
$db->exec("INSERT INTO fechas_cerradas (negocio_id, fecha, motivo) VALUES ($nid, '$otro', 'Vacaciones')");
check('Día cerrado para todo el negocio', 'ocupado', $plan->disponibilidadPublica($nid, $otro));
check('Dos eventos separados el mismo día sin lugar: se puede', 'separado', $ev->obtener($nid, $e2)['estado']);

titulo('11. Ideas y estilo');
check('Paleta: lee colores y descarta lo que no es color', ['#aabbcc', '#112233'], Inspiracion::leerPaleta('#AABBCC, rojo, 112233, #aabbcc'));
check('Máximo 6 colores', 6, count(Inspiracion::leerPaleta(['#000001', '#000002', '#000003', '#000004', '#000005', '#000006', '#000007'])));
$ins->guardarEstilo($nid, $boda, ['#F5E6D3', '#a0526d'], '  Jardín  romántico ', 'Dimitry', (int) $dueno['id']);
check('Estilo guardado', ['#f5e6d3,#a0526d', 'Jardín romántico'], array_values($db->query("SELECT paleta, tematica FROM eventos WHERE id = $boda")->fetch(PDO::FETCH_NUM)));
check_error('Estilo en evento ajeno', 'No se encontró el evento', fn() => $ins->guardarEstilo($nidB, $boda, [], 'x'));
$f = foto_subida();
$i1 = $ins->agregar($nid, $boda, $f, 'flores', 'Así el ramo', 'planner');
$I = $ins->idea($nid, $i1);
check('Foto guardada limpia (JPG con nombre al azar)', true, (bool) preg_match('/^[a-f0-9]{32}\.jpg$/', $I['archivo']));
check('Archivo existe', true, $ins->ruta($nid, $boda, $i1) !== null);
check('Ruta desde otro evento: nada', null, $ins->ruta($nid, $pedida, $i1));
check('Ruta desde otro negocio: nada', null, $ins->ruta($nidB, $boda, $i1));
$i2 = $ins->agregar($nid, $boda, foto_subida(), 'honorarios', '', 'cliente');
check('Categoría honorarios no aplica a ideas → decoración', 'decoracion', $ins->idea($nid, $i2)['categoria']);
check_error('No es foto', 'debe ser una foto', function () use ($ins, $nid, $boda) {
    $ruta = tempnam(sys_get_temp_dir(), 'txt');
    file_put_contents($ruta, 'hola');
    $ins->agregar($nid, $boda, ['name' => 'x.jpg', 'tmp_name' => $ruta, 'error' => UPLOAD_ERR_OK, 'size' => 4], 'flores');
});
$ins->marcar($nid, $boda, $i1, true);
check('Me encanta', true, (bool) $ins->idea($nid, $i1)['aprobada']);
$ins->marcar($nid, $boda, $i1, null);
check('Sin decidir', null, $ins->idea($nid, $i1)['aprobada']);
check_error('Marcar foto de otro evento', 'No se encontró la foto', fn() => $ins->marcar($nid, $pedida, $i1, true));
check_error('El cliente no borra fotos del organizador', 'solo él la puede borrar', fn() => $ins->borrar($nid, $boda, $i1, 'cliente'));
$archivo2 = $ins->idea($nid, $i2)['archivo'];
$ins->borrar($nid, $boda, $i2, 'cliente');
check('El cliente borra la suya (y el archivo)', [0, false], [(int) $db->query("SELECT count(*) FROM evento_ideas WHERE id = $i2")->fetchColumn(),
      is_file(\TuPlanner\Fotos::carpeta('ideas') . '/' . $archivo2)]);
$ins->editarNota($nid, $boda, $i1, 'Ramo en cascada', 'flores');
check('Nota editada', 'Ramo en cascada', $ins->idea($nid, $i1)['nota']);
$db->exec("INSERT INTO evento_ideas (negocio_id, evento_id, archivo, origen) SELECT $nid, $boda, md5(g::text) || '.jpg', 'cliente' FROM generate_series(1, " . Inspiracion::MAX_FOTOS_CLIENTE . ") g");
check_error('Límite de fotos del cliente', 'Ya subiste', fn() => $ins->agregar($nid, $boda, foto_subida(), 'flores', '', 'cliente'));

titulo('12. Reuniones para el portal');
$ev->agregarActividad($nid, $boda, null, 'reunion', 'Reunión de diseño', $hoy->modify('+5 days')->format('Y-m-d 16:00'), (int) $dueno['id']);
$ev->agregarActividad($nid, $boda, null, 'llamada', 'Llamar al DJ', $hoy->modify('+2 days')->format('Y-m-d 10:00'), (int) $dueno['id']);
$reu = $plan->proximasReuniones($boda);
check('Solo reuniones, visitas y degustaciones pendientes', ['Reunión de diseño'], array_column($reu, 'texto'));
