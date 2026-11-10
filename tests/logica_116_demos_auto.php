<?php
/**
 * Demos automáticas de 3 días (10 nov 2026, contrato de las demos automáticas): los 7 datos obligatorios del pedido de demo,
 * campo por campo; cómo se guardan; cómo los lee el Panel Tukán Central (solicitud demo_pedida, claves exactas); el aviso al
 * instante al Panel (demo_aviso.php, token v2 de tipo «demo») con un Panel simulado; y los días de demo (3, una sola vez).
 */
use TuPlanner\{PedidosDemo, Planes, TukanCentral, TukanCentralV2};

$db = base_limpia();
$hoy = new DateTimeImmutable('today');
$valor = fn(string $sql) => $db->query($sql)->fetchColumn();
$bien = ['nombre' => 'Paula Interesada', 'nombre_comercial' => 'Quinta Las Demos', 'telefono' => '022345678', 'celular' => '0991234567',
         'direccion' => 'Av. de los Shyris N35-71 y Suecia', 'ciudad' => 'Quito', 'email' => 'paula@interesada.ec'];

titulo('1. Los 7 datos son obligatorios: se rechaza campo por campo');
check('Las 7 claves del contrato', ['nombre', 'nombre_comercial', 'telefono', 'celular', 'direccion', 'ciudad', 'email'], PedidosDemo::CAMPOS);
check('Completo: sin errores', [], PedidosDemo::validar($bien));
foreach (PedidosDemo::CAMPOS as $campo) {
    check("Sin «{$campo}»: solo ese campo con error", [$campo], array_keys(PedidosDemo::validar([$campo => ''] + $bien)));
}
check('Vacío: los 7 con error', PedidosDemo::CAMPOS, array_keys(PedidosDemo::validar([])));
$malos = ['email' => ['malo', 'a@b', 'sin arroba.ec', '@x.ec'], 'telefono' => ['12345', 'abc1234567', '1234567890123456', '02-23x5678'],
          'celular' => ['12345678', '0212345678', '+593212345678', '09912345', '099123456789', 'cel0991234567'], 'direccion' => ['Av.'], 'ciudad' => ['Q'], 'nombre' => ['Pa'], 'nombre_comercial' => ['QD']];
foreach ($malos as $campo => $lista) foreach ($lista as $v) check("«{$campo}» = «{$v}»: rechazado", true, isset(PedidosDemo::validar([$campo => $v] + $bien)[$campo]));
$buenos = ['telefono' => ['2345678', '02 234-5678', '+59322345678', '0991234567'], 'celular' => ['0991234567', '+593991234567', '+593 99 123 4567', '099-123-4567', '+573001234567'],
           'email' => ['  Paula@Interesada.EC ', 'p.a+demo@sub.dominio.com']];
foreach ($buenos as $campo => $lista) foreach ($lista as $v) check("«{$campo}» = «{$v}»: aceptado", [], PedidosDemo::validar([$campo => $v] + $bien));
check('Mensaje claro del correo', 'El correo no es válido. Revísalo: ahí te llega la clave de tu demo.', PedidosDemo::validar(['email' => 'malo'] + $bien)['email']);
check('Mensaje claro del celular', 'Escribe tu celular: 09XXXXXXXX o +5939XXXXXXXX.', PedidosDemo::validar(['celular' => '123'] + $bien)['celular']);
$l = PedidosDemo::limpiar(['email' => '  Paula@Interesada.EC ', 'celular' => '+593 99 123-4567', 'telefono' => '(02) 234 5678', 'negocio' => 'Con nombre de antes', 'whatsapp' => '0981112233'] );
check('Limpia: correo en minúsculas y sin espacios, teléfono solo con números y +', ['paula@interesada.ec', '022345678'], [$l['email'], $l['telefono']]);
check('… y acepta los nombres de antes (negocio, whatsapp) si no vienen los nuevos', ['Con nombre de antes', '+593991234567'], [$l['nombre_comercial'], $l['celular']]);

titulo('2. Guardar el pedido');
$pd = new PedidosDemo($db);
check_error('Incompleto no se guarda y dice todo lo que falta', 'Escribe la dirección de tu negocio. Escribe tu ciudad.', fn() => $pd->crear(['direccion' => '', 'ciudad' => ''] + $bien));
check('… nada guardado', 0, (int) $valor('SELECT count(*) FROM pedidos_demo'));
$id = $pd->crear(['email' => ' PAULA@Interesada.ec', 'celular' => '+593 99 123 4567', 'necesita' => 'Dos jardines', 'modulos' => ['invitados', 'no_existe']] + $bien, '203.0.113.7');
check('Guardado con los 7 datos', ['Paula Interesada', 'Quinta Las Demos', '022345678', '+593991234567', 'Av. de los Shyris N35-71 y Suecia', 'Quito', 'paula@interesada.ec', 'nueva', 'invitados'],
      array_values($db->query("SELECT nombre, negocio, telefono, whatsapp, direccion, ciudad, email, estado, modulos FROM pedidos_demo WHERE id = $id")->fetch(PDO::FETCH_ASSOC)));
check('El mismo correo con el pedido abierto: se actualiza con los datos nuevos', [$id, 'Calle Nueva 123', '072888999'],
      [$pd->crear(['direccion' => 'Calle Nueva 123', 'telefono' => '072888999'] + $bien), $valor("SELECT direccion FROM pedidos_demo WHERE id = $id"), $valor("SELECT telefono FROM pedidos_demo WHERE id = $id")]);
check('… sin repetirlo', 1, (int) $valor('SELECT count(*) FROM pedidos_demo'));

titulo('3. El Panel lo lee por el conector v2: solicitud demo_pedida con las 7 claves exactas');
$SECRETO = str_repeat('ef56', 16);
putenv('TUKAN_CENTRAL_ID=tuplanner'); putenv("TUKAN_CENTRAL_SECRET=$SECRETO"); putenv('TUKAN_CENTRAL_URL=https://panel.prueba.ec');
$v2 = function (string $accion, array $cuerpo) use ($db, $SECRETO): array {
    $c = json_encode($cuerpo === [] ? new stdClass() : $cuerpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $t = time();
    $tok = TukanCentral::firmar(['v' => 2, 'tipo' => $accion, 'inst' => 'tuplanner', 'email' => 'sistema@tukanec.com', 'nombre' => 'Panel', 'rol' => 'dueno', 'iat' => $t, 'exp' => $t + 30,
                                 'nonce' => bin2hex(random_bytes(16)), 'bh' => TukanCentral::b64(hash('sha256', $c, true))], $SECRETO);
    $db->exec('DELETE FROM tukan_central_pedidos');
    return (new TukanCentralV2($db))->procesar($accion, 'POST', 'application/json; charset=utf-8', $c, $tok, '198.51.100.20');
};
$r = $v2('solicitudes', ['por_pagina' => 200]);
$sol = array_values(array_filter($r[1]['solicitudes'] ?? [], fn($s) => $s['id'] === "demo_pedida:$id"))[0] ?? null;
check('Sale en «solicitudes» como demo_pedida pendiente', [200, 'demo_pedida', 'pendiente'], [$r[0], $sol['tipo'] ?? null, $sol['estado'] ?? null]);
check('Detalle con las 7 claves del contrato', ['nombre' => 'Paula Interesada', 'nombre_comercial' => 'Quinta Las Demos', 'telefono' => '072888999', 'celular' => '0991234567',
                                                 'direccion' => 'Calle Nueva 123', 'ciudad' => 'Quito', 'email' => 'paula@interesada.ec'],
      array_intersect_key($sol['detalle'], array_flip(PedidosDemo::CAMPOS)));
check('… y sigue mandando sus datos propios', [['invitados'], 'Dos jardines', 'Quinta Las Demos'], [$sol['detalle']['modulos'], $sol['detalle']['necesita'], $sol['detalle']['negocio']]);
$una = $v2('solicitud', ['id' => "demo_pedida:$id"]);
check('También en «solicitud» (una sola)', [200, 'paula@interesada.ec', 'Calle Nueva 123'], [$una[0], $una[1]['solicitud']['detalle']['email'] ?? null, $una[1]['solicitud']['detalle']['direccion'] ?? null]);
$dias = null;
foreach ($sol['acciones_posibles'] as $a) if ($a['accion'] === 'crear_demo') foreach ($a['campos'] as $c) if ($c['campo'] === 'dias') $dias = $c['valor'];
check('«Crear demo» propone 3 días', 3, $dias);
$r = $v2('ejecutar', ['operacion_id' => bin2hex(random_bytes(16)), 'accion' => 'crear_demo',
                      'datos' => ['solicitud_id' => "demo_pedida:$id", 'nombre' => 'Quinta Las Demos', 'contacto_nombre' => 'Paula Interesada', 'email' => 'paula@interesada.ec', 'telefono' => '0991234567', 'dias' => 3]]);
check('El Panel crea la demo de 3 días desde el pedido', [200, $hoy->modify('+3 days')->format('Y-m-d'), 'demo_creada'],
      [$r[0], $valor("SELECT prueba_hasta FROM negocios ORDER BY id DESC LIMIT 1"), $valor("SELECT estado FROM pedidos_demo WHERE id = $id")]);

titulo('4. Aviso al instante al Panel (demo_aviso.php)');
$llegó = [];
$panel = function (string $url, string $cuerpo, string $token) use (&$llegó, $SECRETO): int {
    [$a, $firma] = explode('.', $token);
    $p = json_decode((string) TukanCentral::unb64($a), true);
    $ok = hash_equals(TukanCentral::b64(hash_hmac('sha256', $a, $SECRETO, true)), $firma) && $p['bh'] === TukanCentral::b64(hash('sha256', $cuerpo, true));
    $llegó[] = ['url' => $url, 'cuerpo' => json_decode($cuerpo, true), 'p' => $p, 'firma' => $ok];
    return 202;
};
check('Avisa y el Panel contesta 202: verdadero', true, PedidosDemo::avisarPanel(41, $panel));
check('Va a {URL del Panel}/demo_aviso.php', 'https://panel.prueba.ec/demo_aviso.php', $llegó[0]['url']);
check('Cuerpo: la acción y el id de la solicitud', ['accion' => 'demo_pedida', 'id' => 'demo_pedida:41'], $llegó[0]['cuerpo']);
check('Token v2 de tipo «demo», de este programa, firmado con el secreto del Panel y con el bh del cuerpo', [2, 'demo', 'tuplanner', true], [$llegó[0]['p']['v'], $llegó[0]['p']['tipo'], $llegó[0]['p']['inst'], $llegó[0]['firma']]);
check('El token dura 60 segundos y lleva nonce', [60, 32], [$llegó[0]['p']['exp'] - $llegó[0]['p']['iat'], strlen($llegó[0]['p']['nonce'])]);
check('El Panel contesta 500: falso, sin romper nada', false, PedidosDemo::avisarPanel(41, fn() => 500));
check('El Panel no contesta (se cae): falso, sin romper nada', false, PedidosDemo::avisarPanel(41, function () { throw new RuntimeException('sin conexión'); }));
putenv('TUKAN_CENTRAL_URL=http://127.0.0.1:9');   // nadie escucha ahí
$t0 = microtime(true);
check('Sin Panel de verdad (por HTTP): falso', false, PedidosDemo::avisarPanel(41));
check('… y no tarda más de 5 segundos', true, microtime(true) - $t0 < 5.5);
putenv('TUKAN_CENTRAL_ID'); putenv('TUKAN_CENTRAL_SECRET'); putenv('TUKAN_CENTRAL_URL');
$n = 0;
check('TuPlanner sin conectar al Panel: no avisa', [false, 0], [PedidosDemo::avisarPanel(41, function () use (&$n) { $n++; return 202; }), $n]);

titulo('5. Demos de 3 días: una sola vez');
check('Instalación nueva: 3 días de demo y la marca puesta', ['3', '2026-11-10'], [$valor("SELECT valor FROM ajustes WHERE clave = 'dias_prueba'"), $valor("SELECT valor FROM ajustes WHERE clave = 'demo_3_dias'")]);
check('Una demo nueva dura 3 días', $hoy->modify('+3 days')->format('Y-m-d'), $valor('SELECT prueba_hasta FROM negocios WHERE id = ' . (new Planes($db))->crearNegocio('Demo Tres', 'demo-tres-' . bin2hex(random_bytes(3)), 'quinta')));
$db->exec("UPDATE ajustes SET valor = '10' WHERE clave = 'dias_prueba'");
$db->exec(file_get_contents(RAIZ . '/db/migraciones/116_demos_auto.sql'));
check('Si Tukán ya puso otro valor (10), correr la 116 otra vez no lo pisa', '10', $valor("SELECT valor FROM ajustes WHERE clave = 'dias_prueba'"));
$db->exec("UPDATE ajustes SET valor = '7' WHERE clave = 'dias_prueba'");
$db->exec(file_get_contents(RAIZ . '/db/migraciones/116_demos_auto.sql'));
check('… ni aunque vuelva a 7 (ya se hizo una vez)', '7', $valor("SELECT valor FROM ajustes WHERE clave = 'dias_prueba'"));
$db->exec("DELETE FROM ajustes WHERE clave = 'demo_3_dias'; UPDATE ajustes SET valor = '14' WHERE clave = 'dias_prueba'");
$db->exec(file_get_contents(RAIZ . '/db/migraciones/116_demos_auto.sql'));
check('Instalación de antes con 14 días puestos por Tukán: se respeta', '14', $valor("SELECT valor FROM ajustes WHERE clave = 'dias_prueba'"));
