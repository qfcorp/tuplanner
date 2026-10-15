<?php
use TuPlanner\{Eventos, Planificacion};

/**
 * Planificación para el cliente (público, con el enlace del portal): /?r=portal_plan&t=<token_cliente>
 *  - Sus tareas (las marca él) y las del organizador (solo mira el avance).
 *  - El presupuesto por proveedor (solo las partidas visibles): cuánto cuesta, qué está contratado y qué falta pagar.
 *  - Anotar que le pagó a un proveedor directo (y borrar lo que él anotó).
 *  - Próximas reuniones con el organizador.
 * POST accion=tarea (tarea_id, hecha) · pago (partida_id, monto, fecha, metodo, nota) · pago_borrar (pago_id).
 */
$token = (string) ($_GET['t'] ?? '');
$ev = (new Eventos(db()))->porToken('token_cliente', $token);
$negocio = null;
if ($ev) {
    $st = db()->prepare('SELECT * FROM negocios WHERE id = ?');
    $st->execute([$ev['negocio_id']]);
    $negocio = $st->fetch();
}
if (!$ev || $negocio['estado'] === 'cancelado') {
    http_response_code(404);
    vista_publica('error_publico', ['mensaje' => 'Este enlace no es válido o ya no existe. Pide uno nuevo a quien organiza tu evento.'], 'Mi planificación · TuPlanner');
    return;
}
$nid = (int) $ev['negocio_id'];
$eid = (int) $ev['id'];
$marca = ['nombre' => $negocio['nombre'], 'color' => $negocio['color'], 'slug' => $negocio['slug'], 'logo' => $negocio['logo']];
$plan = new Planificacion(db());
$activo = !in_array($ev['estado'], ['perdido', 'cancelado'], true);

if (es_post()) {
    verificar_csrf();
    $accion = (string) ($_POST['accion'] ?? '');
    $ancla = 'tareas';
    try {
        if (!$activo) throw new RuntimeException('Este evento ya no está activo.');
        if ($negocio['estado'] === 'suspendido') throw new RuntimeException('Por ahora no se pueden guardar cambios. Escríbele a ' . $negocio['nombre'] . '.');
        switch ($accion) {
            case 'tarea':
                $plan->marcarTarea($nid, $eid, (int) ($_POST['tarea_id'] ?? 0), ($_POST['hecha'] ?? '') === '1', 'cliente');
                break;
            case 'pago':
                $ancla = 'presupuesto';
                $plan->registrarPago($nid, $eid, (int) ($_POST['partida_id'] ?? 0), $_POST['monto'] ?? '', (string) ($_POST['fecha'] ?? ''),
                                     (string) ($_POST['metodo'] ?? 'transferencia'), (string) ($_POST['nota'] ?? ''), 'cliente');
                aviso('Listo: anotamos tu pago. ' . $negocio['nombre'] . ' también lo ve.');
                break;
            case 'pago_borrar':
                $ancla = 'presupuesto';
                $plan->borrarPago($nid, $eid, (int) ($_POST['pago_id'] ?? 0), 'cliente');
                aviso('Pago borrado.');
                break;
            default:
                throw new RuntimeException('Acción no válida.');
        }
    } catch (RuntimeException $e) {
        if ($e instanceof PDOException) throw $e;
        aviso($e->getMessage(), 'error');
    }
    header('Location: ' . url('portal_plan', ['t' => $token]) . '#' . $ancla);
    exit;
}

$tareasCliente = $plan->tareas($eid, 'cliente');
$grupos = Planificacion::porMeses($tareasCliente, $ev['fecha']);
$avanceCliente = $plan->avance($eid, 'cliente');
$avancePlanner = $plan->avance($eid, 'planner');
$partidas = $plan->partidas($nid, $eid, true);
$resumen = $plan->resumen($nid, $eid, true);
$pagosPor = [];
foreach ($plan->pagos($nid, $eid) as $pg) $pagosPor[(int) $pg['partida_id']][] = $pg;
$reuniones = $plan->proximasReuniones($eid);
$primerNombre = trim(explode(' ', trim((string) $ev['cliente']))[0]);
vista_publica('portal_plan', compact('ev', 'negocio', 'token', 'activo', 'grupos', 'avanceCliente', 'avancePlanner', 'partidas', 'resumen',
                                      'pagosPor', 'reuniones', 'primerNombre'),
              'Mi planificación · ' . $ev['nombre'], $marca);
