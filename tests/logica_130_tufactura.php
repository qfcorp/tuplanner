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

proc_terminate($proc);
array_map('unlink', glob("$simDir/*") ?: []);
@rmdir($simDir);
