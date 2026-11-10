<?php
/**
 * Pagar el plan SOLO con tarjeta desde «Mi plan» (src/PagoPlan.php, 10 nov 2026). TuPlanner le pide el cobro al Panel Tukán
 * Central; aquí el Panel es simulado (una función que contesta como plux_api.php y guarda lo que le piden).
 * Cubre: Panel caído / apagado / prendido, precios que se mandan (los de Planes::cotizarNegocio, sin IVA), suscripción y pago
 * de una vez, suscripción viva (no se crea otra), cancelar un pago sin terminar, precio acordado, errores del Panel y que
 * «registrar_pago» del conector v2 (lo que manda el Panel cuando Plux aprueba) deja la cuenta activa, también una demo.
 */
use TuPlanner\{Modulos, PagoPlan, Planes, TukanCentralV2};

$db = base_limpia();
$hoy = new DateTimeImmutable('today');
$valor = fn(string $sql) => $db->query($sql)->fetchColumn();

/** Panel simulado: $panel['modo'] = caido | apagado | activo; guarda los pedidos en $panel['pedidos'] y los cobros en $panel['cobros']. */
$panel = new ArrayObject(['modo' => 'activo', 'pruebas' => true, 'pedidos' => [], 'cobros' => [], 'crear' => null, 'cambiar' => null]);
$llamar = function (array $d, string $email = '') use ($panel): array {
    $panel['pedidos'] = array_merge($panel['pedidos'], [$d + ['_email' => $email]]);
    if ($panel['modo'] === 'caido') return [0, null];
    $mios = array_values(array_filter($panel['cobros'], fn($c) => $c['cliente_id'] === $d['cliente_id']));
    if ($d['accion'] === 'estado') return [200, ['ok' => true, 'activo' => $panel['modo'] === 'activo', 'iva' => 15, 'pruebas' => $panel['pruebas'], 'cobros' => $panel['modo'] === 'activo' ? $mios : []]];
    if ($panel['modo'] !== 'activo') return [409, ['ok' => false, 'codigo' => 'apagado', 'error' => 'El pago con tarjeta no está disponible por ahora.']];
    if ($d['accion'] === 'cancelar') {
        $todos = $panel['cobros'];
        foreach ($todos as $i => $c) if ($c['id'] === $d['id'] && $c['cliente_id'] === $d['cliente_id']) $todos[$i]['estado'] = 'cancelada';
        $panel['cobros'] = $todos;
        return [200, ['ok' => true, 'programada' => false]];
    }
    if ($d['accion'] === 'cambiar') {
        if ($panel['cambiar'] !== null) return $panel['cambiar'];
        $todos = $panel['cobros'];
        foreach ($todos as $i => $c) {
            if ($c['cliente_id'] !== $d['cliente_id'] || $c['tipo'] !== 'suscripcion' || !in_array($c['estado'], ['activa', 'en_gracia'], true)) continue;
            if ($d['subtotal'] < $c['subtotal'] && !empty($d['pasa_a_completo'])) {
                $todos[$i]['subtotal'] = $d['subtotal'];
                $panel['cobros'] = $todos;
                return [200, ['ok' => true, 'cambio' => true, 'baja_al_renovar' => true, 'proporcional' => null, 'mensaje' => 'Pasó al plan Completo.']];
            }
            if ($d['subtotal'] < $c['subtotal']) return [409, ['ok' => false, 'codigo' => 'no_baja', 'error' => 'Los módulos ya contratados no se pueden quitar mientras dure el período pagado.']];
            if (abs($d['subtotal'] - $c['subtotal']) < 0.01) return [200, ['ok' => true, 'cambio' => false]];
            $todos[$i]['subtotal'] = $d['subtotal'];
            $prop = ['id' => 90 + $i, 'cliente_id' => $c['cliente_id'], 'tipo' => 'cobro', 'estado' => 'pendiente', 'estado_texto' => 'Esperando el pago', 'descripcion' => 'Proporcional módulos',
                     'subtotal' => 2.5, 'total' => 2.88, 'url_pago' => 'https://pagos.prueba.ec/p/prop'];
            $todos[] = $prop;
            $panel['cobros'] = $todos;
            return [200, ['ok' => true, 'cambio' => true, 'proporcional' => $prop]];
        }
        return [404, ['ok' => false, 'codigo' => 'no_encontrado', 'error' => 'Esa suscripción no existe.']];
    }
    if ($panel['crear'] !== null) return $panel['crear'];
    foreach ($mios as $c) if ($d['tipo'] === 'suscripcion' && $c['tipo'] === 'suscripcion' && in_array($c['estado'], PagoPlan::VIVAS, true)) return [409, ['ok' => false, 'codigo' => 'ya_tiene', 'error' => 'Ya tiene una suscripción con tarjeta (Esperando el pago).']];
    $id = count($panel['cobros']) + 1;
    $c = ['id' => $id, 'cliente_id' => $d['cliente_id'], 'tipo' => $d['tipo'], 'estado' => 'pendiente', 'estado_texto' => 'Esperando el pago', 'descripcion' => $d['descripcion'],
          'plan' => $d['plan'], 'periodo' => $d['periodo'], 'subtotal' => $d['subtotal'], 'total' => round($d['subtotal'] * 1.15, 2), 'url_pago' => 'https://pagos.prueba.ec/p/' . $id];
    $panel['cobros'] = array_merge($panel['cobros'], [$c]);
    return [201, ['ok' => true, 'cobro' => $c]];
};
$pp = fn() => new PagoPlan($db, $llamar);   // uno nuevo cada vez: el estado del Panel se pide una vez por página
$ultimo = fn() => $panel['pedidos'][count($panel['pedidos']) - 1];
$usuarioDe = function (int $nid) use ($db): array {
    return $db->query("SELECT u.id, u.nombre, u.email, u.telefono, u.negocio_id, n.plan_codigo, n.estado FROM usuarios u JOIN negocios n ON n.id = u.negocio_id WHERE u.negocio_id = $nid AND u.rol = 'dueno'")->fetch();
};
$VOLVER = 'https://tuplanner.prueba.ec/?r=planes';

titulo('1. El Panel no contesta o tiene apagado el cobro con tarjeta: no se ofrece nada');
[$b] = negocio_prueba($db, 'esencial', 'Local Básico Tarjeta');
$db->prepare("UPDATE negocios SET estado = 'activo', activo_hasta = CURRENT_DATE + 10, modulos_extra = 'paquetes,contratos,portal_cliente,plano,asistente_ia' WHERE id = ?")->execute([$b]);
$ub = $usuarioDe($b);
$panel['modo'] = 'caido';
check('Panel caído: sin estado', null, $pp()->estado($b));
check('Panel caído: no está activo', false, $pp()->activo($b, $ub['email']));
check('… y se le preguntó por este negocio, firmando con el correo del dueño', ['estado', (string) $b, $ub['email']], [$ultimo()['accion'], $ultimo()['cliente_id'], $ultimo()['_email']]);
check_error('Panel caído: pagar no sigue', 'no está disponible', fn() => $pp()->crear($ub, 'esencial', 'mensual', true, $VOLVER));
$panel['modo'] = 'apagado';
check('Apagado para TuPlanner: no está activo', false, $pp()->activo($b));
check('Apagado: sin pagos abiertos', [], $pp()->vivos($b));
check_error('Apagado: pagar no sigue', 'no está disponible', fn() => $pp()->crear($ub, 'esencial', 'mensual', true, $VOLVER));
check('Una respuesta rara (sin «activo») cuenta como que no contesta', null, (new PagoPlan($db, fn() => [200, ['ok' => true, 'canceladas' => 0]]))->estado($b));
check('El estado se pide una sola vez por página', 1, (function () use ($pp, $panel, $b) { $antes = count($panel['pedidos']); $x = $pp(); $x->activo($b); $x->vivos($b); $x->suscripcion($b); $x->pruebas($b); return count($panel['pedidos']) - $antes; })());

titulo('2. Prendido: los precios son los que TuPlanner ya calcula (sin IVA)');
$panel['modo'] = 'activo';
check('Activo', true, $pp()->activo($b));
check('Modo de prueba', true, $pp()->pruebas($b));
check('Puede pagar los dos planes', ['esencial' => 'Básico', 'quinta' => 'Completo'], $pp()->planes($b));
$op = $pp()->opciones($b, 'esencial');
check('Su plan Básico con 4 módulos y la IA: $20,99 al mes (lo mismo que Modulos::mensual)', [(new Modulos($db))->mensual($b), 20.99], [$op['mensual']['monto'], $op['mensual']['monto']]);
check('6 meses: paga 5 = $104,95 y recibe 6', [104.95, 6], [$op['semestral']['monto'], $op['semestral']['meses_recibidos']]);
check('1 año: paga 10 = $209,90 y recibe 12', [209.90, 12], [$op['anual']['monto'], $op['anual']['meses_recibidos']]);
check('Si elige el Completo: $25 / $125 / $250', [25.0, 125.0, 250.0], array_values(array_column($pp()->opciones($b, 'quinta'), 'monto')));
check('Un plan que no existe: sin opciones', [], $pp()->opciones($b, 'salon'));

titulo('3. Pagar: suscripción (se cobra sola) por defecto, o una sola vez');
$c = $pp()->crear($ub, 'esencial', 'semestral', true, $VOLVER);
$p = $ultimo();
check('Devuelve la página de pago', 'https://pagos.prueba.ec/p/1', $c['url_pago']);
check('Pide una suscripción cada 6 meses del plan Básico', ['crear', 'suscripcion', (string) $b, 'esencial', 'Básico', 'semestral', 6], [$p['accion'], $p['tipo'], $p['cliente_id'], $p['plan'], $p['plan_nombre'], $p['periodo'], $p['meses']]);
check('… por $104,95 sin IVA (el IVA lo suma Tukán Pagos)', 104.95, $p['subtotal']);
check('… con la descripción que ve al pagar', 'TuPlanner plan Básico · suscripción cada 6 meses · Local Básico Tarjeta', $p['descripcion']);
check('… el comprador es quien paga', [$ub['nombre'], $ub['email']], [$p['comprador']['nombre'], $p['comprador']['correo']]);
check('… con el RUC y la razón social para la factura', ['1790000000001', 'Arrayanes Eventos S.A.'], [$p['fiscal']['identificacion'], $p['fiscal']['razon_social']]);
check('… y vuelve a Mi plan', $VOLVER, $p['volver']);
check('Ahora tiene una suscripción viva', 1, $pp()->suscripcion($b)['id']);
check_error('Con suscripción viva no se crea otra (ni se le pide al Panel)', 'Ya tienes una suscripción', fn() => $pp()->crear($ub, 'esencial', 'mensual', true, $VOLVER));
$antes = count($panel['pedidos']);
$x = $pp();
check_error('Cancelar un pago que no es suyo: no', 'ya no se puede cancelar', fn() => $x->cancelar($b, 99));
$pp()->cancelar($b, 1, $ub['email']);
check('Cancelar el pago sin terminar: el Panel lo cancela', ['cancelar', 1, 'cancelada'], [$ultimo()['accion'], $ultimo()['id'], $panel['cobros'][0]['estado']]);
check('… y ya puede elegir otro', null, $pp()->suscripcion($b));
$c = $pp()->crear($ub, 'esencial', 'anual', false, $VOLVER);
$p = $ultimo();
check('Pago de una sola vez por 1 año: cobro de $209,90 por 12 meses', ['cobro', 'anual', 12, 209.90], [$p['tipo'], $p['periodo'], $p['meses'], $p['subtotal']]);
check('… descripción', 'TuPlanner plan Básico · anual (12 meses) · Local Básico Tarjeta', $p['descripcion']);
check('Un pago de una vez no cuenta como suscripción viva', null, $pp()->suscripcion($b));
check('… pero se ve entre los pagos abiertos', 1, count($pp()->vivos($b)));
check_error('Período que no existe', 'Elige el plan', fn() => $pp()->crear($ub, 'esencial', 'trimestral', true, $VOLVER));
check_error('Plan que no existe', 'Elige el plan', fn() => $pp()->crear($ub, 'platino', 'mensual', true, $VOLVER));
$todos = $panel['cobros']; $todos[] = ['id' => 50, 'cliente_id' => (string) $b, 'tipo' => 'suscripcion', 'estado' => 'activa', 'estado_texto' => 'Suscripción activa', 'descripcion' => 'x', 'total' => 24.14, 'url_pago' => null]; $panel['cobros'] = $todos;
check('Suscripción activa: es la viva', 50, $pp()->suscripcion($b)['id']);
check_error('La activa no se cancela con este botón (va por «Cancelar mi plan»)', 'Cancelar mi plan', fn() => $pp()->cancelar($b, 50));

titulo('4. Errores del Panel: se muestran y no se manda al cliente a ninguna parte');
[$e] = negocio_prueba($db, 'quinta', 'Quinta Errores');
$db->prepare("UPDATE negocios SET estado = 'activo', activo_hasta = CURRENT_DATE + 10, ruc = NULL WHERE id = ?")->execute([$e]);
$ue = $usuarioDe($e);
$panel['crear'] = [409, ['ok' => false, 'codigo' => 'ya_tiene', 'error' => 'Ya tiene una suscripción con tarjeta (Suscripción activa).']];
check_error('409 del Panel: su mensaje', 'Ya tiene una suscripción con tarjeta', fn() => $pp()->crear($ue, 'quinta', 'mensual', true, $VOLVER));
check('Sin RUC: no manda datos de factura (el Panel decide)', [], $ultimo()['fiscal']);
$panel['crear'] = [0, null];
check_error('El Panel se cae al crear', 'No se pudo conectar', fn() => $pp()->crear($ue, 'quinta', 'mensual', true, $VOLVER));
$panel['crear'] = [201, ['ok' => true, 'cobro' => ['id' => 9, 'url_pago' => 'javascript:alert(1)']]];
check_error('Una dirección de pago rara no se usa', 'no devolvió la página de pago', fn() => $pp()->crear($ue, 'quinta', 'mensual', true, $VOLVER));
$panel['crear'] = null;

titulo('5. Precio acordado con Tukán: paga su plan a ese precio y no cambia de plan aquí');
$db->prepare('UPDATE negocios SET precio_acordado = 18 WHERE id = ?')->execute([$e]);
check('Solo su plan', ['quinta' => 'Completo'], $pp()->planes($e));
check('$18 al mes, $90 por 6 meses, $180 al año', [18.0, 90.0, 180.0], array_values(array_column($pp()->opciones($e, 'quinta'), 'monto')));
check('El otro plan: sin opciones', [], $pp()->opciones($e, 'esencial'));
$db->prepare('UPDATE negocios SET precio_acordado = 0 WHERE id = ?')->execute([$e]);
check('Precio acordado $0 (cortesía): no hay nada que pagar con tarjeta', [], $pp()->opciones($e, 'quinta'));

titulo('6. Una demo paga: al llegar «registrar_pago» del Panel queda como cliente activo');
$SECRETO = str_repeat('cd34', 16);
putenv('TUKAN_CENTRAL_ID=tuplanner'); putenv("TUKAN_CENTRAL_SECRET=$SECRETO"); putenv('TUKAN_CENTRAL_URL=https://panel.prueba.ec');
$b64 = fn($s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
/** Pedido del Panel por el conector v2 (como lo arma tukan-central): $v2('ejecutar', [...]) -> [http, json] */
$v2 = function (string $accion, array $cuerpo) use ($db, $SECRETO, $b64): array {
    $c = json_encode($cuerpo === [] ? new stdClass() : $cuerpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    $t = time();
    $a = $b64(json_encode(['v' => 2, 'tipo' => $accion, 'inst' => 'tuplanner', 'email' => 'sistema@tukanec.com', 'nombre' => 'Tukán Pagos', 'rol' => 'dueno', 'iat' => $t, 'exp' => $t + 30,
                           'nonce' => bin2hex(random_bytes(16)), 'bh' => $b64(hash('sha256', $c, true))]));
    $db->exec('DELETE FROM tukan_central_pedidos');
    return (new TukanCentralV2($db))->procesar($accion, 'POST', 'application/json; charset=utf-8', $c, $a . '.' . $b64(hash_hmac('sha256', $a, $SECRETO, true)), '198.51.100.20');
};
$cap = $v2('capacidades', [])[1];
check('TuPlanner le dice al Panel sus períodos (6 y 12 meses recibidos) y que acepta el método «plux»', [['mensual' => 1, 'semestral' => 6, 'anual' => 12], true],
      [array_column($cap['periodos'], 'meses', 'periodo'), in_array('plux', array_column($cap['metodos_pago'], 'metodo'), true)]);
[$d] = negocio_prueba($db, 'quinta', 'Quinta Demo Paga');
$ud = $usuarioDe($d);
check('Es una demo del plan Completo', ['prueba', 'quinta'], [$ud['estado'], $ud['plan_codigo']]);
check('La demo puede elegir cualquiera de los dos planes', ['esencial', 'quinta'], array_keys($pp()->planes($d)));
check('Si elige el Básico: $12,99 al mes', 12.99, $pp()->opciones($d, 'esencial')['mensual']['monto']);
$pp()->crear($ud, 'esencial', 'mensual', true, $VOLVER);
$p = $ultimo();
check('Pide la suscripción mensual del Básico por $12,99', ['suscripcion', 'esencial', 'mensual', 12.99], [$p['tipo'], $p['plan'], $p['periodo'], $p['subtotal']]);
check('Mientras no pague sigue en demo', 'prueba', $valor("SELECT estado FROM negocios WHERE id = $d"));
// Plux aprobó: el Panel manda registrar_pago con el plan, el período, el método plux, el valor sin IVA y la fecha (PagosPlux::datosRegistrar)
$r = $v2('ejecutar', ['operacion_id' => bin2hex(random_bytes(16)), 'accion' => 'registrar_pago', 'cliente_id' => (string) $d,
                      'datos' => ['plan' => $p['plan'], 'periodo' => $p['periodo'], 'metodo' => 'plux', 'monto' => $p['subtotal'], 'fecha' => $hoy->format('Y-m-d'), 'nota' => 'Plux · suscripción 1']]);
check('registrar_pago aceptado', [200, true], [$r[0], $r[1]['ok'] ?? null]);
$n = $db->query("SELECT estado, plan_codigo, activo_hasta FROM negocios WHERE id = $d")->fetch();
check('La demo queda como cliente activo, con el plan Básico, pagado un mes', ['activo', 'esencial', $hoy->modify('+1 month -1 day')->format('Y-m-d')], [$n['estado'], $n['plan_codigo'], $n['activo_hasta']]);
check('El pago queda en «Pagos hechos»: Básico mensual, $12,99, por Plux', ['esencial', 'mensual', '12.99', 'plux'],
      array_values($db->query("SELECT plan_codigo, periodo, monto, metodo_pago FROM suscripciones WHERE negocio_id = $d")->fetch(PDO::FETCH_ASSOC)));
check('Ya no está en demo: le quedan sus 3 módulos por elegir', [false, 3], [(new Modulos($db))->estado($d)['demo'], (new Modulos($db))->estado($d)['por_elegir']]);
check('Cuenta al día', 'activo', (new Planes($db))->estadoCuenta($n));
// La cuota siguiente de la suscripción (Plux cobra sola): el Panel manda otro registrar_pago
$r = $v2('ejecutar', ['operacion_id' => bin2hex(random_bytes(16)), 'accion' => 'registrar_pago', 'cliente_id' => (string) $d,
                      'datos' => ['plan' => 'esencial', 'periodo' => 'mensual', 'metodo' => 'plux', 'monto' => 12.99, 'fecha' => $hoy->format('Y-m-d'), 'nota' => 'Plux · suscripción 1 · cuota 2']]);
check('La cuota siguiente suma otro mes', $hoy->modify('+1 month -1 day')->modify('+1 day +1 month -1 day')->format('Y-m-d'), $valor("SELECT activo_hasta FROM negocios WHERE id = $d"));

[$a] = negocio_prueba($db, 'quinta', 'Quinta Demo Anual');
$db->prepare("UPDATE negocios SET prueba_hasta = CURRENT_DATE - 5 WHERE id = ?")->execute([$a]);   // demo terminada
$ua = $usuarioDe($a);
$pp()->crear($ua, 'quinta', 'anual', true, $VOLVER);
$p = $ultimo();
check('Demo terminada también puede pagar: Completo anual $250 (paga 10 meses)', ['quinta', 'anual', 12, 250.0], [$p['plan'], $p['periodo'], $p['meses'], $p['subtotal']]);
$r = $v2('ejecutar', ['operacion_id' => bin2hex(random_bytes(16)), 'accion' => 'registrar_pago', 'cliente_id' => (string) $a,
                      'datos' => ['plan' => 'quinta', 'periodo' => 'anual', 'metodo' => 'plux', 'monto' => 250.0, 'fecha' => $hoy->format('Y-m-d'), 'nota' => 'Plux']]);
$n = $db->query("SELECT estado, plan_codigo, activo_hasta FROM negocios WHERE id = $a")->fetch();
check('… y queda activa 12 meses con el plan Completo', [200, 'activo', 'quinta', $hoy->modify('+12 months -1 day')->format('Y-m-d')], [$r[0], $n['estado'], $n['plan_codigo'], $n['activo_hasta']]);

titulo('7. Cliente Básico que paga el Completo: cambia de plan al aprobarse');
check('Antes: Básico', 'esencial', $valor("SELECT plan_codigo FROM negocios WHERE id = $b"));
$r = $v2('ejecutar', ['operacion_id' => bin2hex(random_bytes(16)), 'accion' => 'registrar_pago', 'cliente_id' => (string) $b,
                      'datos' => ['plan' => 'quinta', 'periodo' => 'semestral', 'metodo' => 'plux', 'monto' => 125.0, 'fecha' => $hoy->format('Y-m-d'), 'nota' => 'Plux']]);
$n = $db->query("SELECT plan_codigo, activo_hasta FROM negocios WHERE id = $b")->fetch();
check('Después: Completo, y los 6 meses se suman a lo que ya tenía pagado', [200, 'quinta', $hoy->modify('+11 days +6 months -1 day')->format('Y-m-d')], [$r[0], $n['plan_codigo'], $n['activo_hasta']]);
check('En el Completo paga $25 (sus módulos extra ya vienen en el plan)', 25.0, (new Modulos($db))->mensual($b));

titulo('8. Cambio de módulos con suscripción con tarjeta pagada (segunda parte, decisión de Dimitry)');
$mods = new Modulos($db);
[$m] = negocio_prueba($db, 'esencial', 'Local Suscrito');
$db->prepare("UPDATE negocios SET estado = 'activo', activo_hasta = CURRENT_DATE + 20, modulos_extra = 'paquetes,contratos,portal_cliente,plano' WHERE id = ?")->execute([$m]);
$mods->olvidar();
check('Paga $17,99 al mes (Básico + 1 extra)', 17.99, $mods->mensual($m));
check('Sin suscripción: no hay suscripción pagada', null, $pp()->suscripcionPagada($m));
$sinSus = count($panel['pedidos']);
check('Sin suscripción: ajustar no avisa nada', null, $pp()->ajustar($m, null));
check('… ni le pide nada al Panel', $sinSus, count($panel['pedidos']));
$todos = $panel['cobros']; $todos[] = ['id' => 70, 'cliente_id' => (string) $m, 'tipo' => 'suscripcion', 'estado' => 'pendiente', 'estado_texto' => 'Esperando el pago', 'descripcion' => 'x', 'plan' => 'esencial',
                                       'periodo' => 'mensual', 'subtotal' => 17.99, 'total' => 20.69, 'url_pago' => 'https://pagos.prueba.ec/p/70', 'pagado_hasta' => null]; $panel['cobros'] = $todos;
check('Suscripción sin pagar todavía: no cuenta como pagada (se puede cambiar libre)', null, $pp()->suscripcionPagada($m));
$todos = $panel['cobros']; $todos[count($todos) - 1]['estado'] = 'activa'; $todos[count($todos) - 1]['pagado_hasta'] = $hoy->modify('+20 days')->format('Y-m-d'); $panel['cobros'] = $todos;
$sus = $pp()->suscripcionPagada($m);
check('Suscripción activa: es la pagada', [70, 'mensual'], [$sus['id'], $sus['periodo']]);
$antes = $mods->estado($m);
check('Igual (no cambia el valor): no avisa ni le pide nada al Panel', [null, 0], (function () use ($pp, $m, $sus, $panel) { $n = count($panel['pedidos']); $r = $pp()->ajustar($m, $sus); return [$r, count($panel['pedidos']) - $n]; })());
// Sube: se enciende un módulo más ($5)
$db->prepare("UPDATE negocios SET modulos_extra = 'paquetes,contratos,portal_cliente,plano,invitados' WHERE id = ?")->execute([$m]);
$mods->olvidar();
PagoPlan::revisarBaja($sus, $antes, $mods->estado($m));
check('Encender un módulo sí se puede', 22.99, $mods->mensual($m));
$msg = $pp()->ajustar($m, $sus);
check('Sube: le manda «cambiar» al Panel con el valor nuevo del mismo período', ['cambiar', (string) $m, 22.99, 70], [$ultimo()['accion'], $ultimo()['cliente_id'], $ultimo()['subtotal'], $ultimo()['id']]);
check('… y avisa el valor nuevo y el proporcional con su enlace', 'La suscripción con tarjeta queda en $22,99 + IVA cada mes desde el próximo cobro. Por los días que faltan del período pagado se cobra aparte $2,50 + IVA: https://pagos.prueba.ec/p/prop (el enlace también sale en Mi plan del cliente).', $msg);
$db->prepare("UPDATE negocios SET modulos_extra = 'paquetes,contratos,portal_cliente,plano,invitados,mobiliario' WHERE id = ?")->execute([$m]);
check('Al dueño se le dice el prorrateo con IVA y dónde pagarlo', 'La suscripción con tarjeta queda en $27,99 + IVA cada mes desde el próximo cobro. Por los días que faltan se cobra aparte $2,88 con IVA: toca «Pagar ahora» en «Pagar con tarjeta».',
      $pp()->ajustar($m, ['subtotal' => 22.99] + $sus, '', true));
$db->prepare("UPDATE negocios SET modulos_extra = 'paquetes,contratos,portal_cliente,plano,invitados' WHERE id = ?")->execute([$m]);
$todos = $panel['cobros']; foreach ($todos as $i => $c) if (($c['id'] ?? 0) === 70) $todos[$i]['subtotal'] = 22.99; $panel['cobros'] = $todos;
check('Sin cobro con tarjeta: el mensaje de «Pasarme al Completo»', 'Para pasarte al Completo necesitas pagar con tarjeta. En este momento el pago con tarjeta no está disponible: escríbenos y te ayudamos.', PagoPlan::SIN_TARJETA);
check('El proporcional sale entre los pagos abiertos de Mi plan', true, in_array('Proporcional módulos', array_column($pp()->vivos($m), 'descripcion'), true));
// Quitar un módulo contratado: no se permite
$sus = $pp()->suscripcionPagada($m);
$antes = $mods->estado($m);
$db->prepare("UPDATE negocios SET modulos_extra = 'paquetes,contratos,portal_cliente,plano' WHERE id = ?")->execute([$m]);
$mods->olvidar();
check_error('Quitar un módulo contratado: no se permite', 'Los módulos ya contratados no se pueden quitar mientras dure el período pagado (hasta el ' . $hoy->modify('+20 days')->format('d/m/Y') . '): Invitados', fn() => PagoPlan::revisarBaja($sus, $antes, $mods->estado($m)));
$db->prepare("UPDATE negocios SET modulos_extra = 'paquetes,contratos,portal_cliente,plano,invitados' WHERE id = ?")->execute([$m]);
$mods->olvidar();
check_error('Bajar el valor (precio acordado menor): no se permite', 'no puede bajar mientras dure el período pagado', fn() => PagoPlan::revisarBaja($sus, $antes, ['mensual' => 15.0] + $mods->estado($m)));
check_error('Bajar de plan (Completo → Básico): no se permite', 'no se pueden quitar', fn() => PagoPlan::revisarBaja($sus, $mods->estado($m, 'quinta'), $mods->estado($m, 'esencial')));
PagoPlan::revisarBaja($sus, $mods->estado($m, 'esencial'), $mods->estado($m, 'quinta'));
check('Subir de plan (Básico → Completo): sí se puede', true, true);
PagoPlan::revisarBaja(null, $antes, ['mensual' => 1.0, 'pagados' => []]);
check('Sin suscripción pagada: se puede quitar y bajar como siempre', true, true);
// Si igual llega una baja al Panel, contesta 409 no_baja y se muestra
$db->prepare('UPDATE negocios SET precio_acordado = 10 WHERE id = ?')->execute([$m]);
check('Baja que llega al Panel: 409 no_baja, se muestra su texto', 'Suscripción con tarjeta: Los módulos ya contratados no se pueden quitar mientras dure el período pagado.', $pp()->ajustar($m, $sus));
$db->prepare('UPDATE negocios SET precio_acordado = NULL WHERE id = ?')->execute([$m]);
$panel['cambiar'] = [409, ['ok' => false, 'codigo' => 'sin_pagar', 'error' => 'Todavía no pagó la primera cuota: cancele ese pago y vuelva a pagar con el valor nuevo.']];
$db->prepare("UPDATE negocios SET modulos_extra = 'paquetes,contratos,portal_cliente,plano,invitados,mobiliario' WHERE id = ?")->execute([$m]);
check('Primera cuota sin pagar (409 sin_pagar): se muestra', 'Suscripción con tarjeta: Todavía no pagó la primera cuota: cancele ese pago y vuelva a pagar con el valor nuevo.', $pp()->ajustar($m, $sus));
$panel['cambiar'] = null;
// Panel caído: el cambio igual se hace y se avisa que se ajustará después
$panel['modo'] = 'caido';
check('Panel caído: avisa que la suscripción se ajustará cuando el Panel responda', 'El Panel Tukán no respondió: la suscripción con tarjeta se ajustará a $27,99 + IVA cuando el Panel responda (avísale a Tukán).', $pp()->ajustar($m, $sus));
check('Panel caído sin pagos con Plux registrados aquí: no hay suscripción pagada', null, $pp()->suscripcionPagada($m));
(new Planes($db))->suscribir($m, 'esencial', 'mensual', $hoy, 'plux', null, 17.99, $hoy);
$loc = $pp()->suscripcionPagada($m);
check('Panel caído con un pago con Plux que cubre hoy: igual no deja quitar módulos', [true, 'mensual', 17.99], [$loc['local'], $loc['periodo'], $loc['subtotal']]);
$panel['modo'] = 'activo';

titulo('9. «Te sugerimos el Completo» y pasarse al Completo a mitad del período (tercera parte)');
[$k] = negocio_prueba($db, 'esencial', 'Local Sugerencia');
$db->prepare("UPDATE negocios SET estado = 'activo', activo_hasta = CURRENT_DATE + 20, modulos_extra = '' WHERE id = ?")->execute([$k]);
check('Lejos del Completo ($12,99 contra $25): no sugiere nada', null, $pp()->sugerenciaCompleto($k));
$db->prepare("UPDATE negocios SET modulos_extra = 'paquetes,contratos,portal_cliente,plano,asistente_ia' WHERE id = ?")->execute([$k]);
$sg = $pp()->sugerenciaCompleto($k);
check('Cerca ($20,99, a $4,01 del Completo): aviso suave', ['cerca', 20.99, 25.0, 4.01, 'quinta'], [$sg['tipo'], $sg['actual'], $sg['completo'], $sg['diferencia'], $sg['plan']]);
check('Con un módulo más ($25,99) igualaría o pasaría al Completo', ['mayor', 25.99], [($x = $pp()->sugerenciaCompleto($k, ['invitados']))['tipo'], $x['actual']]);
$db->prepare("UPDATE negocios SET modulos_extra = 'paquetes,contratos,portal_cliente,plano,asistente_ia,invitados' WHERE id = ?")->execute([$k]);
check('Mayor ($25,99 contra $25): aviso fuerte', ['mayor', 25.99, 25.0, 0.0], array_values(array_intersect_key($pp()->sugerenciaCompleto($k), array_flip(['tipo', 'actual', 'completo', 'diferencia']))));
$db->exec("UPDATE planes SET precio_mensual = 25.99 WHERE codigo = 'quinta'");
check('Igual ($25,99 y $25,99): aviso fuerte', ['mayor', 0.0], [($x = $pp()->sugerenciaCompleto($k))['tipo'], $x['diferencia']]);
$db->exec("UPDATE planes SET precio_mensual = 31.00 WHERE codigo = 'quinta'");
check('A $5,01 del Completo: ya no sugiere', null, $pp()->sugerenciaCompleto($k));
$db->exec("UPDATE planes SET precio_mensual = 30.99 WHERE codigo = 'quinta'");
check('A $5,00 justos: aviso suave', 'cerca', $pp()->sugerenciaCompleto($k)['tipo']);
$db->exec("UPDATE planes SET precio_mensual = 25 WHERE codigo = 'quinta'");
$db->prepare('UPDATE negocios SET precio_acordado = 30 WHERE id = ?')->execute([$k]);
check('Con precio acordado con Tukán: no sugiere', null, $pp()->sugerenciaCompleto($k));
$db->prepare('UPDATE negocios SET precio_acordado = NULL WHERE id = ?')->execute([$k]);
check('Quien ya tiene el Completo: no sugiere', null, $pp()->sugerenciaCompleto($a));
// Con suscripción con tarjeta pagada cada mes de $25,99: pasarse al Completo ($25) sí se permite
$todos = $panel['cobros']; $todos[] = ['id' => 80, 'cliente_id' => (string) $k, 'tipo' => 'suscripcion', 'estado' => 'activa', 'estado_texto' => 'Suscripción activa', 'descripcion' => 'x', 'plan' => 'esencial',
                                       'periodo' => 'mensual', 'subtotal' => 25.99, 'total' => 29.89, 'url_pago' => null, 'pagado_hasta' => $hoy->modify('+20 days')->format('Y-m-d')]; $panel['cobros'] = $todos;
$susK = $pp()->suscripcionPagada($k);
$antes = $mods->estado($k);
$db->prepare("UPDATE negocios SET plan_codigo = 'quinta' WHERE id = ?")->execute([$k]);
$mods->olvidar();
check_error('Sin decir que pasa al Completo, bajar el valor sigue bloqueado', 'no puede bajar', fn() => PagoPlan::revisarBaja($susK, $antes, $mods->estado($k)));
PagoPlan::revisarBaja($susK, $antes, $mods->estado($k), null, true);
check('Pasarse al Completo: se permite aunque baje', true, true);
$x = $pp();
$msg = $x->ajustar($k, $susK);
check('Le manda «cambiar» con pasa_a_completo y el Completo del mismo período ($25)', ['cambiar', true, 25.0], [$ultimo()['accion'], $ultimo()['pasa_a_completo'] ?? null, $ultimo()['subtotal']]);
check('… y avisa que rige desde el próximo cobro', ['Pasaste al plan Completo. Desde el próximo cobro (' . $hoy->modify('+20 days')->format('d/m/Y') . ') la suscripción con tarjeta queda en $25,00 + IVA cada mes. Lo ya pagado no se devuelve.', true], [$msg, $x->ultimoOk]);
check_error('Bajar del Completo al Básico sigue bloqueado', 'no se pueden quitar', fn() => PagoPlan::revisarBaja($susK, $mods->estado($k), $mods->estado($k, 'esencial')));
$panel['modo'] = 'caido';
$x = $pp();
check('Panel caído al subir: aviso rojo', false, ($x->ajustar($m, $sus) !== null) && $x->ultimoOk);
$panel['modo'] = 'activo';

putenv('TUKAN_CENTRAL_ID'); putenv('TUKAN_CENTRAL_SECRET'); putenv('TUKAN_CENTRAL_URL');
