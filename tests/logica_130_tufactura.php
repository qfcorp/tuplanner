<?php
/**
 * Factura electrónica con TuFactura (módulo «facturacion», 9 nov 2026) con el TuFactura SIMULADO (tests/tufactura_simulado.php):
 * viene apagado y solo a pedido (ni «*» ni la demo lo traen), se enciende, se conecta la clave, cada pago confirmado del evento se
 * factura (a mano o solo), nunca dos veces, a nombre del cliente; la garantía y los pagos por revisar no se facturan.
 */
use TuPlanner\{Eventos, FacturaTukan, Modulos, Pagos};

$db = base_limpia();
$puerto = (int) (getenv('TF_SIM_PUERTO') ?: 8396);
$simDir = sys_get_temp_dir() . '/tf_sim_tuplanner_' . getmypid();
@mkdir($simDir);
$proc = proc_open(['php', '-S', "127.0.0.1:$puerto", RAIZ . '/tests/tufactura_simulado.php'], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pp, null, ['TF_SIM_DIR' => $simDir] + getenv());
for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $puerto); $i++) usleep(100000);
$sim = "http://127.0.0.1:$puerto";

titulo('1. El módulo viene apagado y es solo a pedido');
[$nid, $dueno] = negocio_prueba($db);
$mods = new Modulos($db);
check('Existe en el catálogo', true, Modulos::existe('facturacion'));
check('Es solo a pedido (no entra en «*» ni en la demo con todo)', [true, false], [in_array('facturacion', Modulos::SOLO_A_PEDIDO, true), in_array('facturacion', Modulos::todos(), true)]);
check('No cuenta entre los módulos a elegir', true, in_array('facturacion', Modulos::FUERA_DEL_CUPO, true));
check('Precio en el programa $0 (TuFactura se paga aparte)', 0.0, (float) $mods->catalogo()['facturacion']['precio_mensual']);
check('Apagado en la cuenta nueva', false, FacturaTukan::activo($nid));

titulo('2. Pagos del evento');
$fecha = (new DateTimeImmutable('today'))->modify('+60 days')->format('Y-m-d');
$ev = new Eventos($db);
$e1 = $ev->crear($nid, ['cliente' => ['nombre' => 'María José Paredes', 'telefono' => '0991112233', 'identificacion' => '1710034065', 'email' => 'mj@ejemplo.com'],
    'tipo' => 'boda', 'fecha' => $fecha, 'hora_inicio' => '18:00', 'hora_fin' => '02:00', 'invitados' => 120, 'espacios' => [(int) $db->query("SELECT id FROM espacios WHERE negocio_id = $nid ORDER BY id LIMIT 1")->fetchColumn()]], (int) $dueno['id']);
$pag = new Pagos($db);
$p0 = $pag->registrar($nid, $e1, ['monto' => 500, 'metodo' => 'transferencia', 'tipo' => 'evento'], $dueno);
$tf = FacturaTukan::enlace($db);
check('Apagado: el pago no se factura', null, $tf->deOrigen($nid, 'pago', (string) $p0));
$mods->cambiar($nid, ['facturacion'], []);
check('Encendido desde el Panel', true, FacturaTukan::activo($nid));
[$cli, $items, $extra] = FacturaTukan::datos($nid, (string) $p0);
check('Datos: a nombre del cliente del evento', ['1710034065', 'María José Paredes', 'mj@ejemplo.com'], [$cli['identificacion'], $cli['nombre'], $cli['correo']]);
check('Datos: el pago con su valor', 500.0, $items[0]['precio']);
check('Datos: forma de pago', 'transferencia', $extra['forma']);
$pg = $pag->registrar($nid, $e1, ['monto' => 200, 'metodo' => 'efectivo', 'tipo' => 'garantia'], $dueno);
check('La garantía no se factura (se devuelve)', null, FacturaTukan::datos($nid, (string) $pg));
check('Pagos para facturar: solo los del evento', [(string) $p0], array_column(FacturaTukan::cobros($nid), 0));

titulo('3. Conectar y facturar');
$con = $tf->conectar($nid, 'tf_' . str_repeat('c3', 20), $sim);
check('Conectado (simulado)', 'EMPRESA DE PRUEBA S.A.', $con['empresa']);
$d = $tf->facturar($nid, 'pago', (string) $p0, ...FacturaTukan::datos($nid, (string) $p0));
check('Factura a mano: autorizada por 500', ['autorizado', 500.0], [$d['estado'], (float) $d['total']]);
check('Forma de pago del SRI: transferencia (20)', '20', json_decode((string) $d['envio'], true)['forma_pago']);
check('No se factura dos veces', (int) $d['id'], (int) $tf->facturar($nid, 'pago', (string) $p0, ...FacturaTukan::datos($nid, (string) $p0))['id']);
$tf->opciones($nid, true, true, 15);
$p1 = $pag->registrar($nid, $e1, ['monto' => 300, 'metodo' => 'tarjeta', 'tipo' => 'evento'], $dueno);
check('Automático: el pago sale facturado solo', 'autorizado', $tf->deOrigen($nid, 'pago', (string) $p1)['estado'] ?? null);
// Comprobante del cliente: se factura cuando el negocio lo CONFIRMA
$db->prepare("INSERT INTO pagos (negocio_id, evento_id, tipo, monto, metodo, estado, origen) VALUES (?, ?, 'evento', 150, 'transferencia', 'por_revisar', 'cliente')")->execute([$nid, $e1]);
$p2 = (int) $db->lastInsertId();
check('Por revisar: todavía no se factura', null, FacturaTukan::datos($nid, (string) $p2));
$pag->confirmar($nid, $p2, $dueno);
check('Al confirmarlo sale facturado solo', 'autorizado', $tf->deOrigen($nid, 'pago', (string) $p2)['estado'] ?? null);
$mods->cambiar($nid, [], ['facturacion']);
$p3 = $pag->registrar($nid, $e1, ['monto' => 100, 'metodo' => 'efectivo', 'tipo' => 'evento'], $dueno);
check('Apagado de nuevo: ya no factura solo', null, $tf->deOrigen($nid, 'pago', (string) $p3));

// ---- Ventas al EXTERIOR: sin IVA en ningún producto (27 nov 2026): TukanFactura::esExterior y armar (copia de tufactura/docs/enlace)
echo "-- Ventas al exterior: sin IVA --\n";
foreach ([[['pais' => 'Ecuador'], false], [['pais' => 'Paraguay'], true], [['pais' => 'Ecuador', 'direccion' => 'Asunción, Paraguay'], false],
    [['direccion' => 'Madrid, España'], true], [['direccion' => 'Av. Colombia y Peru, Quito, Ecuador'], false], [['direccion' => 'Av. Amazonas y Colón, Quito'], false],
    [[], false], [['tipo_id' => '08'], true], [['tipo_id' => '08', 'direccion' => 'Cuenca, Ecuador'], false]] as [$cli_x, $espera_x]) {
    check('esExterior ' . json_encode($cli_x, JSON_UNESCAPED_UNICODE), $espera_x, \TukanFactura::esExterior($cli_x));
}
$tf_x = new \TukanFactura(new PDO('sqlite::memory:'), str_repeat('k', 32), 'Prueba');
$con_x = ['precios_con_iva' => 1, 'iva' => 15];
$items_x = [['descripcion' => 'Servicio', 'cantidad' => 1, 'precio' => 115], ['descripcion' => 'Servicio 10 %', 'cantidad' => 2, 'precio' => 10, 'iva' => 15]];
$loc_x = $tf_x->armar($con_x, 'venta', '1', ['identificacion' => '1710034065', 'nombre' => 'Ana', 'direccion' => 'Quito'], $items_x);
check('Cliente de Ecuador: con IVA y precio sin IVA separado', [15, true], [$loc_x['items'][0]['iva'], $loc_x['items'][0]['precio'] < 115]);
$ext_x = $tf_x->armar($con_x, 'venta', '2', ['identificacion' => 'PY1234567', 'nombre' => 'Federación Paraguaya', 'pais' => 'Paraguay', 'ciudad' => 'Asunción'], $items_x);
check('Cliente del exterior: todas las líneas a 0 %', [0, 0], [$ext_x['items'][0]['iva'], $ext_x['items'][1]['iva']]);
check('Cliente del exterior: no se le quita IVA (el total es lo que pagó)', [115.0, 10.0], [$ext_x['items'][0]['precio'], $ext_x['items'][1]['precio']]);
check('El país y la ciudad viajan a TuFactura', ['Paraguay', 'Asunción'], [$ext_x['cliente']['pais'] ?? '', $ext_x['cliente']['ciudad'] ?? '']);
$ext2_x = $tf_x->armar(['precios_con_iva' => 0, 'iva' => 15], 'venta', '3', ['identificacion' => 'AB123456', 'nombre' => 'Cliente', 'direccion' => 'Madrid, España'], [['descripcion' => 'X', 'precio' => 50, 'iva' => 15]]);
check('Una línea con 15 % sale a 0 % si la dirección es del exterior', 0, $ext2_x['items'][0]['iva']);

// ---- Exterior v2 (27 nov 2026, noche): la cédula o el RUC ecuatoriano válido PREVALECE; país y ciudad en los datos del cliente
titulo('Exterior v2: cédula/RUC válido prevalece; país y ciudad del cliente');
foreach ([
    ['cédula válida + Paraguay => con IVA', ['identificacion' => '1710034065', 'pais' => 'Paraguay'], false],
    ['cédula INVÁLIDA + Paraguay => sin IVA', ['identificacion' => '1710034066', 'pais' => 'Paraguay'], true],
    ['país Ecuador con dirección extranjera => con IVA', ['pais' => 'Ecuador', 'direccion' => 'Calle 5, Asunción, Paraguay'], false],
    ['pasaporte sin país (tipo_id 08) => sin IVA', ['identificacion' => 'AB123456', 'tipo_id' => '08'], true],
    ['RUC válido + dirección en España => con IVA', ['identificacion' => '1710034065001', 'direccion' => 'Madrid, España'], false],
] as [$nom_v, $cli_v, $espera_v]) {
    check($nom_v, $espera_v, \TukanFactura::esExterior($cli_v));
}
check('Paises: sin país = Ecuador', ['Ecuador', true, false], [\TuPlanner\Paises::normalizar(''), \TuPlanner\Paises::esEcuador('ecuador'), \TuPlanner\Paises::esEcuador('Paraguay')]);
check('Paises: pasaporte → tipo 08; cédula → sin tipo', ['08', ''], [\TuPlanner\Paises::tipoId('PY1234567'), \TuPlanner\Paises::tipoId('1710034065')]);
check_error('En Ecuador la identificación sigue siendo cédula (10) o RUC (13)', 'La cédula debe tener 10 dígitos', fn() => $ev->guardarCliente($nid, ['nombre' => 'Cliente Local', 'identificacion' => 'AB12345']));
check_error('Fuera de Ecuador el pasaporte debe ser válido (5 a 20 letras y números)', 'pasaporte', fn() => $ev->guardarCliente($nid, ['nombre' => 'Cliente Afuera', 'identificacion' => 'A1', 'pais' => 'Paraguay']));
check('Un cliente viejo (migración) queda en Ecuador', 'Ecuador', (string) $db->query("SELECT c.pais FROM clientes c JOIN eventos e ON e.cliente_id = c.id WHERE e.id = $e1")->fetchColumn());
// De punta a punta: cliente de Paraguay con pasaporte
$fecha2 = (new DateTimeImmutable('today'))->modify('+61 days')->format('Y-m-d');
$esp = (int) $db->query("SELECT id FROM espacios WHERE negocio_id = $nid ORDER BY id LIMIT 1")->fetchColumn();
$e2 = $ev->crear($nid, ['cliente' => ['nombre' => 'Federación Paraguaya', 'telefono' => '0992223344', 'identificacion' => 'py-1234567', 'email' => 'fp@ejemplo.com', 'pais' => 'Paraguay', 'ciudad' => 'Asunción'],
    'tipo' => 'boda', 'fecha' => $fecha2, 'hora_inicio' => '18:00', 'hora_fin' => '02:00', 'invitados' => 80, 'espacios' => [$esp]], (int) $dueno['id']);
$pX = $pag->registrar($nid, $e2, ['monto' => 115, 'metodo' => 'transferencia', 'tipo' => 'evento'], $dueno);
[$cliX, $itemsX] = FacturaTukan::datos($nid, (string) $pX);
check('Datos al exterior: identificación y tipo 08 (pasaporte)', ['PY1234567', '08'], [$cliX['identificacion'], $cliX['tipo_id']]);
check('Datos al exterior: país y ciudad', ['Paraguay', 'Asunción'], [$cliX['pais'], $cliX['ciudad']]);
$fX = $tf->facturar($nid, 'pago', (string) $pX, ...FacturaTukan::datos($nid, (string) $pX));
$envioX = json_decode((string) $fX['envio'], true);
check('Factura al exterior: todas las líneas a 0 % y por lo cobrado (115)', [[0], 115.0], [array_values(array_unique(array_column($envioX['items'], 'iva'))), (float) $fX['total']]);
check('Factura al exterior: viaja el país', 'Paraguay', $envioX['cliente']['pais'] ?? '');
// Cédula ecuatoriana válida con país Paraguay: prevalece la cédula (con IVA)
$cl2 = $ev->guardarCliente($nid, ['nombre' => 'Rosa Quiroga', 'telefono' => '0993334455', 'identificacion' => '1710034065', 'pais' => 'Paraguay', 'ciudad' => 'Asunción']);
check('Cliente con cédula ecuatoriana y país Paraguay: se guarda con su país', 'Paraguay', (string) $db->query("SELECT pais FROM clientes WHERE id = $cl2")->fetchColumn());
check('… pero la regla lo trata como de Ecuador (con IVA)', false, \TukanFactura::esExterior(\TuPlanner\Paises::paraFactura('1710034065', 'Paraguay', 'Asunción', 'Calle 5')));
// La ficha del cliente se edita con país y ciudad
(new \TuPlanner\Ventas($db))->editarCliente($nid, $cl2, ['nombre' => 'Rosa Quiroga', 'telefono' => '0993334455', 'identificacion' => '1710034065', 'pais' => 'Ecuador', 'ciudad' => 'Quito']);
check('Editar la ficha: país y ciudad', ['Ecuador', 'Quito'], [$db->query("SELECT pais FROM clientes WHERE id = $cl2")->fetchColumn(), $db->query("SELECT ciudad FROM clientes WHERE id = $cl2")->fetchColumn()]);
// La migración 120 se puede correr dos veces
$mig = file_get_contents(RAIZ . '/db/migraciones/120_pais_ciudad.sql');
$db->exec($mig);
check('Migración 120 idempotente (otra vez no falla ni toca filas)', 'Paraguay', (string) $db->query("SELECT pais FROM clientes WHERE id = " . (int) $db->query("SELECT cliente_id FROM eventos WHERE id = $e2")->fetchColumn())->fetchColumn());

proc_terminate($proc);
array_map('unlink', glob("$simDir/*") ?: []);
@rmdir($simDir);
