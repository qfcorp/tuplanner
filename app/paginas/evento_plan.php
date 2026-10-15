<?php
use TuPlanner\{Cotizaciones, Eventos, Planificacion, Ventas};

/**
 * Evento — pestaña "Planificación" (migración 100): /?r=evento_plan&id=<evento>
 *  - Presupuesto: partidas por proveedor (quién paga, estimado, contratado, pagos directos del cliente, comisión del proveedor).
 *  - Honorarios del organizador: tarifa fija o porcentaje del presupuesto (se pasan a la cotización).
 *  - Lista de tareas por meses (del organizador y del cliente).
 * Permisos: dueño y vendedor cambian todo; el coordinador ve y marca las tareas (sin dinero); consulta solo mira.
 * Acciones (POST accion=...): tarea, tarea_marcar, tarea_editar, tarea_borrar, lista_tipica, partida, partida_borrar, repartir,
 *                              pago, pago_borrar, comision, comision_anular, honorario, honorario_cotizacion.
 */
$nid = (int) $u['negocio_id'];
$id = (int) ($_GET['id'] ?? 0);
$eventos = new Eventos(db());
$ev = $eventos->obtener($nid, $id);
if (!$ev) {
    http_response_code(404);
    vista('error', ['mensaje' => 'No encontramos ese evento. Puede que el enlace esté mal o que sea de otro negocio.'], 'Planificación · TuPlanner');
    return;
}
$tab = 'evento_plan';
$plan = new Planificacion(db());
$comercial = es_comercial($u);
$verDinero = $u['rol'] !== 'coordinador';
$volver = function (string $ancla) use ($id): never {
    header('Location: ' . url('evento_plan', ['id' => $id]) . '#' . $ancla);
    exit;
};

if (es_post()) {
    verificar_csrf();
    exigir(puede_editar($u), 'Tu usuario solo puede mirar.');
    $accion = (string) ($_POST['accion'] ?? '');
    $ancla = 'tareas';
    $quien = (string) $u['nombre'];
    $uid = (int) $u['id'];
    try {
        if (!in_array($accion, ['tarea', 'tarea_marcar', 'tarea_editar', 'tarea_borrar', 'lista_tipica'], true)) {
            exigir($comercial, 'Solo el dueño o un vendedor pueden cambiar el presupuesto y los honorarios.');
        }
        switch ($accion) {
            case 'tarea':
                $plan->agregarTarea($nid, $id, (string) ($_POST['titulo'] ?? ''), (string) ($_POST['vence'] ?? ''),
                                    (string) ($_POST['asignado'] ?? 'planner'), $uid, $quien);
                aviso('Tarea agregada.');
                break;
            case 'tarea_marcar':
                $plan->marcarTarea($nid, $id, (int) ($_POST['tarea_id'] ?? 0), ($_POST['hecha'] ?? '') === '1', 'planner', $uid, $quien);
                break;
            case 'tarea_editar':
                $plan->editarTarea($nid, $id, (int) ($_POST['tarea_id'] ?? 0), (string) ($_POST['titulo'] ?? ''), (string) ($_POST['vence'] ?? ''),
                                   (string) ($_POST['asignado'] ?? 'planner'), (string) ($_POST['notas'] ?? ''));
                aviso('Tarea guardada.');
                break;
            case 'tarea_borrar':
                $plan->borrarTarea($nid, $id, (int) ($_POST['tarea_id'] ?? 0));
                aviso('Tarea borrada.');
                break;
            case 'lista_tipica':
                $n = $plan->cargarListaTipica($nid, $id);
                aviso($n ? "Listo: agregamos $n tareas con sus fechas. Cambia o borra las que no apliquen." : 'Ya tenías todas las tareas de la lista típica.');
                break;
            case 'partida':
                $ancla = 'presupuesto';
                $pid = (int) ($_POST['partida_id'] ?? 0) ?: null;
                $plan->guardarPartida($nid, $id, $_POST, $pid, $uid, $quien);
                aviso($pid ? 'Partida guardada.' : 'Partida agregada al presupuesto.');
                break;
            case 'partida_borrar':
                $ancla = 'presupuesto';
                $plan->borrarPartida($nid, $id, (int) ($_POST['partida_id'] ?? 0), $uid, $quien);
                aviso('Partida borrada.');
                break;
            case 'repartir':
                $ancla = 'presupuesto';
                $n = $plan->repartir($nid, $id, Cotizaciones::num($_POST['presupuesto'] ?? 0), $uid, $quien);
                aviso("Listo: repartimos el presupuesto en $n partidas con los porcentajes típicos. Cámbialos según lo que pida el cliente.");
                break;
            case 'pago':
                $ancla = 'presupuesto';
                $pid = (int) ($_POST['partida_id'] ?? 0);
                $plan->registrarPago($nid, $id, $pid, $_POST['monto'] ?? '', (string) ($_POST['fecha'] ?? date('Y-m-d')),
                                     (string) ($_POST['metodo'] ?? 'transferencia'), (string) ($_POST['nota'] ?? ''), 'planner', $uid, $quien);
                aviso('Pago al proveedor anotado.');
                $ancla = 'partida-' . $pid;
                break;
            case 'pago_borrar':
                $ancla = 'presupuesto';
                $plan->borrarPago($nid, $id, (int) ($_POST['pago_id'] ?? 0), 'planner', $uid, $quien);
                aviso('Pago borrado.');
                break;
            case 'comision':
                $pid = (int) ($_POST['partida_id'] ?? 0);
                $plan->cobrarComision($nid, $pid, $_POST['monto'] ?? '', (string) ($_POST['fecha'] ?? '') ?: null, $uid, $quien);
                aviso('Comisión cobrada anotada.');
                $ancla = 'partida-' . $pid;
                break;
            case 'comision_anular':
                $pid = (int) ($_POST['partida_id'] ?? 0);
                $plan->anularComision($nid, $pid, $uid, $quien);
                aviso('Comisión cobrada borrada.');
                $ancla = 'partida-' . $pid;
                break;
            case 'honorario':
                $ancla = 'honorarios';
                $plan->guardarHonorario($nid, $id, (string) ($_POST['modo'] ?? 'fija'), $_POST['pct'] ?? 0, $uid, $quien);
                aviso('Forma de cobro guardada.');
                break;
            case 'honorario_cotizacion':
                $ancla = 'honorarios';
                $cid = (int) ($_POST['cotizacion_id'] ?? 0);
                $monto = Cotizaciones::num($_POST['monto'] ?? 0);
                $plan->honorarioACotizacion($nid, $id, $cid, $monto, $uid, $quien);
                aviso('Honorarios puestos en la cotización (' . dinero($monto) . ' + IVA).');
                redirigir('cotizacion', ['id' => $id, 'c' => $cid]);
            default:
                throw new RuntimeException('Acción no válida.');
        }
    } catch (RuntimeException $e) {
        if ($e instanceof PDOException) throw $e;
        aviso($e->getMessage(), 'error');
    }
    $volver($ancla);
}

$tareas = $plan->tareas($id);
$grupos = Planificacion::porMeses($tareas, $ev['fecha']);
$avance = $plan->avance($id);
$avanceCliente = $plan->avance($id, 'cliente');
$partidas = $verDinero ? $plan->partidas($nid, $id) : [];
$pagosDirectos = $verDinero ? $plan->pagos($nid, $id) : [];
$resumen = $verDinero ? $plan->resumen($nid, $id) : null;
$sugerido = $verDinero ? $plan->honorarioSugerido($nid, $id) : null;
$cotEditables = [];
if ($comercial) {
    $st = db()->prepare("SELECT id, numero, version, estado, total FROM cotizaciones WHERE negocio_id = ? AND evento_id = ? AND estado IN ('borrador','enviada') ORDER BY version DESC");
    $st->execute([$nid, $id]);
    $cotEditables = $st->fetchAll();
}
$proveedores = [];
if ($comercial) {
    $st = db()->prepare('SELECT id, nombre, servicio FROM proveedores WHERE negocio_id = ? AND activo ORDER BY nombre');
    $st->execute([$nid]);
    $proveedores = $st->fetchAll();
}
$carga = $plan->cargaDelDia($nid, $ev['fecha'], $id);
$editar = (int) ($_GET['editar'] ?? 0);
$st = db()->prepare('SELECT comision_pct FROM negocios WHERE id = ?');
$st->execute([$nid]);
$comisionDefecto = (float) $st->fetchColumn();

vista('evento_plan', compact('ev', 'tab', 'tareas', 'grupos', 'avance', 'avanceCliente', 'partidas', 'pagosDirectos', 'resumen', 'sugerido',
                             'cotEditables', 'proveedores', 'carga', 'comercial', 'verDinero', 'editar', 'comisionDefecto'),
      'Planificación · ' . $ev['nombre']);
