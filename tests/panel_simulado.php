<?php
declare(strict_types=1);

/**
 * Panel Tukán Central SIMULADO para probar «Pagar con tarjeta» de Mi plan (php -S 127.0.0.1:PUERTO tests/panel_simulado.php).
 * Imita plux_api.php del Panel (tukan-central/public/plux_api.php y PagosPlux::atenderPrograma): revisa el token v2 de tipo
 * «plux» (firma con TUKAN_CENTRAL_SECRET, programa TUKAN_CENTRAL_ID y «bh» = sha256 del cuerpo) y contesta estado, crear,
 * cancelar, cambiar (como PagosPlux «cambiar»: sube con proporcional, pasa_a_completo aunque baje, mejora (prorrateo por subir de plan sin suscripción), 409 no_baja, 409 sin_pagar, igual) y cancelar_plan. No cobra nada: no habla con Plux ni con nadie.
 *  - PANEL_SIM_DIR/config.json: {"activo": true|false, "pruebas": true|false, "caido": true|false} (lo cambian las pruebas).
 *  - PANEL_SIM_DIR/cobros.json: los cobros creados (las pruebas leen y cambian su estado, p. ej. a «activa»).
 *  - PANEL_SIM_DIR/pedidos.log: una línea JSON por cada pedido recibido con firma buena.
 */
$dir = getenv('PANEL_SIM_DIR') ?: sys_get_temp_dir() . '/panel_sim';
@mkdir($dir, 0777, true);
$leer = fn(string $a, array $def) => is_file("$dir/$a") ? (json_decode((string) file_get_contents("$dir/$a"), true) ?: $def) : $def;
$json = function (int $c, array $d): never { http_response_code($c); header('Content-Type: application/json; charset=utf-8'); echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; };
$cfg = $leer('config.json', []) + ['activo' => true, 'pruebas' => true, 'caido' => false];
$ruta = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
if ($ruta === '/') $json(200, ['ok' => true, 'simulado' => true]);
if ($cfg['caido']) { http_response_code(503); echo 'Panel en mantenimiento'; exit; }
if ($ruta !== '/plux_api.php' || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') $json(405, ['ok' => false, 'error' => 'Solo POST.']);

$cuerpo = (string) file_get_contents('php://input');
$b64 = fn(string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
$partes = explode('.', (string) ($_SERVER['HTTP_X_TUKAN_TOKEN'] ?? ''));
$p = count($partes) === 2 ? json_decode((string) base64_decode(strtr($partes[0], '-_', '+/')), true) : null;
$firmaBien = is_array($p) && hash_equals($b64(hash_hmac('sha256', $partes[0], (string) getenv('TUKAN_CENTRAL_SECRET'), true)), $partes[1]);
if (!$firmaBien || ($p['v'] ?? 0) !== 2 || ($p['tipo'] ?? '') !== 'plux' || ($p['inst'] ?? '') !== getenv('TUKAN_CENTRAL_ID')
    || ($p['bh'] ?? '') !== $b64(hash('sha256', $cuerpo, true)) || (int) ($p['exp'] ?? 0) < time()) {
    $json(401, ['ok' => false, 'codigo' => 'firma', 'error' => 'Token no válido.']);
}
$b = json_decode($cuerpo, true);
if (!is_array($b)) $json(400, ['ok' => false, 'codigo' => 'invalido', 'error' => 'El cuerpo debe ser JSON.']);
file_put_contents("$dir/pedidos.log", json_encode($b + ['_email' => $p['email'] ?? ''], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND);

$accion = (string) ($b['accion'] ?? '');
$cid = (string) ($b['cliente_id'] ?? '');
if ($cid === '') $json(422, ['ok' => false, 'codigo' => 'invalido', 'error' => 'Falta el cliente_id.']);
$cobros = $leer('cobros.json', []);
$guardar = function () use (&$cobros, $dir) { file_put_contents("$dir/cobros.json", json_encode(array_values($cobros), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)); };
$textos = ['pendiente' => 'Esperando el pago', 'rechazado' => 'Tarjeta rechazada (puede intentar otra vez)', 'activa' => 'Suscripción activa', 'en_gracia' => 'Suscripción con un cobro atrasado',
           'pagado' => 'Pagado', 'cancelada' => 'Suscripción cancelada', 'vencida' => 'Suscripción vencida'];
$publico = function (array $f) use ($textos): array {
    $f['estado_texto'] = $textos[$f['estado']] ?? $f['estado'];
    if (!in_array($f['estado'], ['pendiente', 'rechazado'], true)) $f['url_pago'] = null;
    $f['origen'] = $f['origen'] ?? 'programa';
    unset($f['cliente_id']);
    return $f;
};
$mios = fn() => array_values(array_map($publico, array_filter($cobros, fn($f) => $f['cliente_id'] === $cid)));
$viva = function () use (&$cobros, $cid): ?array {
    foreach ($cobros as $f) if ($f['cliente_id'] === $cid && $f['tipo'] === 'suscripcion' && in_array($f['estado'], ['pendiente', 'rechazado', 'activa', 'en_gracia'], true)) return $f;
    return null;
};

if ($accion === 'cancelar_plan') $json(200, ['ok' => true, 'canceladas' => 0, 'programadas' => 0, 'errores' => []]);
if ($accion === 'estado') $json(200, ['ok' => true, 'activo' => (bool) $cfg['activo'], 'iva' => 15, 'pruebas' => (bool) $cfg['pruebas'], 'tope_consumidor' => 50, 'cobros' => $cfg['activo'] ? $mios() : []]);
if (!$cfg['activo']) $json(409, ['ok' => false, 'codigo' => 'apagado', 'error' => 'El pago con tarjeta no está disponible por ahora. Pague por transferencia y envíe el comprobante.']);
if ($accion === 'cancelar') {
    foreach ($cobros as $i => $f) {
        if ($f['cliente_id'] === $cid && $f['id'] === (int) ($b['id'] ?? 0)) {
            $cobros[$i]['estado'] = 'cancelada';
            $guardar();
            $json(200, ['ok' => true, 'programada' => false, 'cobro' => $publico($cobros[$i])]);
        }
    }
    $json(404, ['ok' => false, 'codigo' => 'no_encontrado', 'error' => 'Esa suscripción no existe.']);
}
if ($accion === 'cambiar') {   // como PagosPlux::atenderPrograma «cambiar» + subirValor
    $i = null;
    foreach ($cobros as $k => $f) {
        if ($f['cliente_id'] !== $cid || $f['tipo'] !== 'suscripcion') continue;
        if ((int) ($b['id'] ?? 0) > 0 ? $f['id'] === (int) $b['id'] : in_array($f['estado'], ['pendiente', 'rechazado', 'activa', 'en_gracia'], true)) $i = $k;
    }
    if ($i === null) $json(404, ['ok' => false, 'codigo' => 'no_encontrado', 'error' => 'Esa suscripción no existe.']);
    $f = $cobros[$i];
    $nuevo = round((float) ($b['subtotal'] ?? 0), 2);
    $antes = round((float) $f['subtotal'], 2);
    if (in_array($f['estado'], ['pendiente', 'rechazado'], true)) $json(409, ['ok' => false, 'codigo' => 'sin_pagar', 'error' => 'Todavía no pagó la primera cuota: cancele ese pago y vuelva a pagar con el valor nuevo.', 'cobro' => $publico($f)]);
    if (abs($nuevo - $antes) < 0.01) $json(200, ['ok' => true, 'cambio' => false, 'cobro' => $publico($f)]);
    $hasta = (string) ($f['pagado_hasta'] ?? '');
    if ($nuevo < $antes && !empty($b['pasa_a_completo'])) {   // pasarse al Completo sí se acepta aunque baje: desde el próximo cobro
        $cobros[$i]['subtotal'] = $nuevo;
        $cobros[$i]['total'] = round($nuevo * 1.15, 2);
        $guardar();
        $json(200, ['ok' => true, 'cambio' => true, 'baja_al_renovar' => true, 'cambiado_en_plux' => true, 'proporcional' => null,
                    'mensaje' => 'Pasó al plan Completo. Desde el próximo cobro' . ($hasta ? ' (' . date('d/m/Y', strtotime($hasta)) . ')' : '') . ' paga $' . number_format($cobros[$i]['total'], 2, ',', '.') . ' con IVA. Lo ya pagado no se devuelve.',
                    'cobro' => $publico($cobros[$i])]);
    }
    if ($nuevo < $antes) $json(409, ['ok' => false, 'codigo' => 'no_baja', 'error' => 'Los módulos ya contratados no se pueden quitar mientras dure el período pagado' . ($hasta ? ' (hasta el ' . date('d/m/Y', strtotime($hasta)) . ')' : '') . '. La suscripción con tarjeta sigue en $' . number_format((float) $f['total'], 2, ',', '.') . '.', 'cobro' => $publico($f)]);
    $cobros[$i]['subtotal'] = $nuevo;
    $cobros[$i]['total'] = round($nuevo * 1.15, 2);
    $prop = null;
    if ($hasta !== '' && date('Y-m-d') < $hasta) {
        $fin = strtotime($hasta); $ini = strtotime($hasta . ' -' . max(1, (int) $f['meses']) . ' months');
        $propSub = round(($nuevo - $antes) * max(0.0, min(1.0, ($fin - strtotime(date('Y-m-d'))) / max(1, $fin - $ini))), 2);
        if ($propSub >= 1.0) {
            $id = count($cobros) + 1;
            $prop = ['id' => $id, 'cliente_id' => $cid, 'tipo' => 'cobro', 'estado' => 'pendiente', 'descripcion' => 'Proporcional módulos del ' . date('d/m') . ' al ' . date('d/m/Y', $fin),
                     'plan' => $f['plan'], 'periodo' => null, 'meses' => 1, 'subtotal' => $propSub, 'total' => round($propSub * 1.15, 2),
                     'url_pago' => 'http://' . ($_SERVER['HTTP_HOST'] ?? '127.0.0.1') . '/pagar/' . $id, 'cobra_sola' => false, 'pagado_hasta' => null, 'cuotas' => 0,
                     'pruebas' => (bool) $cfg['pruebas'], 'creado' => date('c'), 'cancelar_hasta' => null, 'cancelacion_programada' => null, 'volver' => $f['volver'] ?? null];
            $cobros[] = $prop;
        }
    }
    $guardar();
    $json(200, ['ok' => true, 'cambio' => true, 'cambiado_en_plux' => true, 'mensaje' => 'Suscripción cambiada.', 'proporcional' => $prop ? $publico($prop) : null, 'cobro' => $publico($cobros[$i])]);
}
if ($accion === 'mejora') {   // como PagosPlux «mejora»: prorrateo de UNA vez por subir de plan sin suscripción; no manda registrar_pago
    if ($viva()) $json(409, ['ok' => false, 'codigo' => 'ya_tiene', 'error' => 'Ya tiene una suscripción con tarjeta: el cambio de plan se ajusta en ella.']);
    $plan = trim((string) ($b['plan'] ?? '')); $planNombre = trim((string) ($b['plan_nombre'] ?? ''));
    if ($plan === '' || $planNombre === '') $json(422, ['ok' => false, 'codigo' => 'invalido', 'error' => 'Falta el plan nuevo.']);
    $subtotal = round((float) ($b['subtotal'] ?? 0), 2);
    foreach ($cobros as $i => $f) {   // una mejora a la vez: si hay una igual esperando, se devuelve esa; si es otra, se anula
        if ($f['cliente_id'] !== $cid || ($f['origen'] ?? '') !== 'mejora' || !in_array($f['estado'], ['pendiente', 'rechazado'], true)) continue;
        if ($f['plan'] === $plan && abs($f['subtotal'] - $subtotal) < 0.01) $json(200, ['ok' => true, 'repetido' => true, 'cobro' => $publico($f)]);
        $cobros[$i]['estado'] = 'anulado';
    }
    if ($subtotal < 1) { $guardar(); $json(422, ['ok' => false, 'codigo' => 'minimo', 'error' => 'Menos de $1 no se cobra con tarjeta: el plan se cambia sin cobro.']); }
    $id = count($cobros) + 1;
    $hasta = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($b['hasta'] ?? '')) ? ' hasta el ' . date('d/m/Y', strtotime((string) $b['hasta'])) : '';
    $f = ['id' => $id, 'cliente_id' => $cid, 'tipo' => 'cobro', 'estado' => 'pendiente', 'descripcion' => mb_substr('Cambio al plan ' . $planNombre . ': prorrateo' . $hasta, 0, 90), 'plan' => $plan,
          'periodo' => null, 'meses' => 1, 'subtotal' => $subtotal, 'total' => round($subtotal * 1.15, 2), 'url_pago' => 'http://' . ($_SERVER['HTTP_HOST'] ?? '127.0.0.1') . '/pagar/' . $id,
          'cobra_sola' => false, 'pagado_hasta' => null, 'cuotas' => 0, 'pruebas' => (bool) $cfg['pruebas'], 'creado' => date('c'), 'cancelar_hasta' => null, 'cancelacion_programada' => null,
          'volver' => $b['volver'] ?? null, 'origen' => 'mejora'];
    $cobros[] = $f;
    $guardar();
    $json(201, ['ok' => true, 'cobro' => $publico($f)]);
}
if ($accion !== 'crear') $json(400, ['ok' => false, 'codigo' => 'invalido', 'error' => 'Acción no válida (crear, estado, cancelar o cambiar).']);
$tipo = (string) ($b['tipo'] ?? 'cobro');
if ($tipo === 'suscripcion' && ($v = $viva())) $json(409, ['ok' => false, 'codigo' => 'ya_tiene', 'error' => 'Ya tiene una suscripción con tarjeta (' . ($textos[$v['estado']] ?? $v['estado']) . ').', 'cobro' => $publico($v)]);
$subtotal = round((float) ($b['subtotal'] ?? 0), 2);
$periodos = ['mensual' => 1, 'trimestral' => 3, 'semestral' => 6, 'anual' => 12];
if ($subtotal < 0.5 || $subtotal > 50000) $json(422, ['ok' => false, 'codigo' => 'invalido', 'error' => 'El valor sin IVA debe estar entre $0,50 y $50.000.']);
if ($tipo === 'suscripcion' && !isset($periodos[$b['periodo'] ?? ''])) $json(422, ['ok' => false, 'codigo' => 'invalido', 'error' => 'Elige cada cuánto se cobra (mensual, cada 3 meses, cada 6 meses o anual).']);
if (trim((string) ($b['descripcion'] ?? '')) === '') $json(422, ['ok' => false, 'codigo' => 'invalido', 'error' => 'Falta la descripción (la ve el cliente al pagar).']);
$id = count($cobros) + 1;
$f = ['id' => $id, 'cliente_id' => $cid, 'tipo' => $tipo, 'estado' => 'pendiente', 'descripcion' => mb_substr((string) $b['descripcion'], 0, 90), 'plan' => $b['plan'] ?? null,
      'periodo' => $b['periodo'] ?? null, 'meses' => $tipo === 'suscripcion' ? $periodos[$b['periodo']] : (int) ($b['meses'] ?? 1), 'subtotal' => $subtotal, 'total' => round($subtotal * 1.15, 2),
      'url_pago' => 'http://' . ($_SERVER['HTTP_HOST'] ?? '127.0.0.1') . '/pagar/' . $id, 'cobra_sola' => false, 'pagado_hasta' => null, 'cuotas' => 0, 'pruebas' => (bool) $cfg['pruebas'],
      'creado' => date('c'), 'cancelar_hasta' => null, 'cancelacion_programada' => null, 'volver' => $b['volver'] ?? null];
$cobros[] = $f;
$guardar();
$json(201, ['ok' => true, 'cobro' => $publico($f)]);
