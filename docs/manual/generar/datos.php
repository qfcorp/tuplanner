<?php
declare(strict_types=1);
/**
 * Datos de ejemplo para las capturas del manual: el negocio «Planner Demo Tukanec» (plan Agencia, todos los módulos)
 * con eventos en cada estado, planificación, presupuesto por proveedor, comisiones, ideas, cotización, contrato firmado,
 * pagos, día del evento e invitados. También un negocio en plan Esencial para mostrar «No está en tu plan».
 * Solo corre en una base cuyo nombre tenga "manual" o "prueba".
 *   TUPLANNER_DB_NAME=tuplanner_manual_prueba TUPLANNER_DB_USER=tuplanner TUPLANNER_DB_PASS=prueba php docs/manual/generar/datos.php
 * Imprime en JSON los ids que usan las capturas.
 */
use TuPlanner\{Altas, Contratos, Cotizaciones, DiaEvento, Eventos, Inspiracion, Invitados, Pagos, Planificacion, Usuarios};

define('RAIZ', dirname(__DIR__, 3));
date_default_timezone_set('America/Guayaquil');
spl_autoload_register(function (string $c): void {
    if (str_starts_with($c, 'TuPlanner\\') && is_file($f = RAIZ . '/src/' . substr($c, 10) . '.php')) require $f;
});
if (is_file(RAIZ . '/vendor/autoload.php')) require RAIZ . '/vendor/autoload.php';
$base = (string) getenv('TUPLANNER_DB_NAME');
if (!str_contains($base, 'manual') && !str_contains($base, 'prueba')) exit("Solo en bases de prueba (*manual* o *prueba*).\n");

$db = \TuPlanner\Db::conectar();
$hoy = new DateTimeImmutable('today');
$d = fn(int $dias) => $hoy->modify(($dias >= 0 ? '+' : '') . $dias . ' days')->format('Y-m-d');
/** El sábado más cercano a dentro de $dias días. */
$sabado = function (int $dias) use ($hoy): string {
    $f = $hoy->modify("+$dias days");
    $w = (int) $f->format('w');
    return $f->modify('+' . ((6 - $w + 7) % 7) . ' days')->format('Y-m-d');
};
$CLAVE = 'PlannerDemo2026';

// ---------- Negocio y equipo ----------
$alta = (new Altas($db))->crearNegocioConDueno('Planner Demo Tukanec', 'Ana Pérez', '0991234567', 'demo@tuplanner.tukanec.com', $CLAVE, 'quinta', 'Quito', 'super');
$nid = $alta['negocio_id'];
$db->prepare("UPDATE negocios SET estado = 'activo', activo_hasta = ?, razon_social = 'Ana Pérez Eventos', ruc = '1712345678001',
              direccion = 'Av. González Suárez N32-45, Quito', instagram = '@plannerdemotukanec', color = '#A0526D',
              descripcion = 'Organizamos bodas, quince años y eventos inolvidables en Quito y los valles: planificación completa, proveedores de confianza y coordinación del día.',
              pago_banco = 'Banco Pichincha · Cuenta de ahorros 2201234567 · Ana Pérez · C.I. 1712345678', pago_deuna = '0991234567',
              max_eventos_dia = 2, comision_pct = 10 WHERE id = ?")
   ->execute([$d(300), $nid]);
$db->prepare("UPDATE usuarios SET clave_temporal = false WHERE negocio_id = ?")->execute([$nid]);
$dueno = $db->query("SELECT * FROM usuarios WHERE id = {$alta['usuario_id']}")->fetch();
$U = ['id' => (int) $dueno['id'], 'nombre' => 'Ana Pérez'];
$us = new Usuarios($db);
$equipo = [];
foreach ([['Carla Mena', 'carla@tuplanner.tukanec.com', '0987654321', 'vendedor'], ['Diego Salazar', 'diego@tuplanner.tukanec.com', '0981112233', 'coordinador'],
          ['Rosa Vela', 'contadora@tuplanner.tukanec.com', '0984445566', 'consulta'], ['Lucía Andrade', 'lucia@tuplanner.tukanec.com', '0993334455', 'admin']] as [$n, $e, $t, $r]) {
    $x = $us->crear($nid, $n, $e, $t, $r);
    $equipo[$r] = (int) ($x['id'] ?? $db->query("SELECT id FROM usuarios WHERE email = '$e'")->fetchColumn());
}
$db->prepare("UPDATE usuarios SET password_hash = ?, clave_temporal = false, ultimo_ingreso = now() - interval '1 day' WHERE negocio_id = ?")
   ->execute([password_hash($CLAVE, PASSWORD_DEFAULT), $nid]);

// Lugares: los de ejemplo con datos más reales y uno más
$db->prepare("UPDATE espacios SET descripcion = 'Hacienda con salón cubierto para 250 personas, jardines, pista de baile y tarima.', direccion = 'Vía a Tumbaco, km 5', contacto = 'Jorge Andrade', telefono = '0995556677' WHERE negocio_id = ? AND nombre = 'Hacienda La Herradura'")->execute([$nid]);
$db->prepare("UPDATE espacios SET descripcion = 'Capilla colonial con jardín para la ceremonia y el cóctel.', direccion = 'Valle de los Chillos, San Rafael' WHERE negocio_id = ? AND nombre = 'Capilla del Valle'")->execute([$nid]);
$db->prepare("INSERT INTO espacios (negocio_id, nombre, descripcion, capacidad_banquete, capacidad_coctel, precio_base, color, orden, direccion, tipo_lugar)
              VALUES (?, 'Quinta Los Arupos', 'Quinta con piscina y jardín para eventos al aire libre.', 180, 250, 1300, '#2A6F97', 3, 'Puembo, calle Manuel Burbano', 'ambos')")->execute([$nid]);
$esp = fn(string $n) => (int) $db->query("SELECT id FROM espacios WHERE negocio_id = $nid AND nombre = " . $db->quote($n))->fetchColumn();
$hacienda = $esp('Hacienda La Herradura'); $capilla = $esp('Capilla del Valle'); $arupos = $esp('Quinta Los Arupos');

// Proveedores
$dia = new DiaEvento($db);
$prov = [];
foreach ([['Foto Estudio Luz', 'foto_video', 'Mateo Ríos', '0991110001'], ['Florería Primavera', 'flores', 'Lucía Paredes', '0991110002'],
          ['DJ Max Sonido', 'musica', 'Max Villacís', '0991110003'], ['Catering Sabores del Valle', 'menu', 'Patricio León', '0991110004'],
          ['Pastelería Dulce Encanto', 'pasteleria', 'Gabriela Ortiz', '0991110005'], ['Glam Studio Maquillaje', 'belleza', 'Karla Benítez', '0991110006'],
          ['Decoraciones Aurora', 'decoracion', 'Aurora Salazar', '0991110007'], ['Bar Móvil Brindis', 'bebidas', 'Felipe Cueva', '0991110008']] as [$n, $s, $c, $t]) {
    $prov[$s] = $dia->guardarProveedor($nid, null, ['nombre' => $n, 'servicio' => $s, 'contacto' => $c, 'telefono' => $t]);
}
// Personal del día
$personal = [];
foreach ([['Diego Salazar', 'coordinador', 60], ['Valentina Mora', 'coordinador', 45], ['Jhon Quishpe', 'mesero', 35], ['Luis Tapia', 'mesero', 35]] as [$n, $r, $p]) {
    $personal[] = $dia->guardarPersona($nid, null, ['nombre' => $n, 'telefono' => '09' . random_int(80000000, 89999999), 'rol' => $r, 'pago_evento' => $p]);
}

$ev = new Eventos($db);
$cot = new Cotizaciones($db);
$pag = new Pagos($db);
$con = new Contratos($db);
$plan = new Planificacion($db);
$ins = new Inspiracion($db);
$paquete = fn(string $n) => (int) $db->query("SELECT id FROM paquetes WHERE negocio_id = $nid AND nombre = " . $db->quote($n))->fetchColumn();
$producto = fn(string $n) => (int) $db->query("SELECT id FROM productos WHERE negocio_id = $nid AND nombre = " . $db->quote($n))->fetchColumn();
$TMP = sys_get_temp_dir() . '/tuplanner_manual_' . getmypid();
@mkdir($TMP, 0700, true);

/** Imagen de ejemplo (degradado suave con círculos, como flores o luces) para el tablero de ideas y los comprobantes. */
$imagen = function (string $nombre, array $colores, int $semilla, bool $comprobante = false) use ($TMP): array {
    mt_srand($semilla);
    $w = 900; $h = 700;
    $im = imagecreatetruecolor($w, $h);
    $rgb = fn(string $hex) => sscanf($hex, '#%02x%02x%02x');
    [$r1, $g1, $b1] = $rgb($colores[0]); [$r2, $g2, $b2] = $rgb($colores[1]);
    for ($y = 0; $y < $h; $y++) {
        $t = $y / $h;
        imageline($im, 0, $y, $w, $y, imagecolorallocate($im, (int) ($r1 + ($r2 - $r1) * $t), (int) ($g1 + ($g2 - $g1) * $t), (int) ($b1 + ($b2 - $b1) * $t)));
    }
    if ($comprobante) {
        $blanco = imagecolorallocate($im, 255, 255, 255); $gris = imagecolorallocate($im, 60, 60, 70);
        imagefilledrectangle($im, 200, 80, 700, 620, $blanco);
        imagestring($im, 5, 260, 130, 'TRANSFERENCIA EXITOSA', $gris);
        imagestring($im, 4, 260, 200, 'Monto: USD 500.00', $gris);
        imagestring($im, 4, 260, 240, 'Beneficiario: Ana Perez', $gris);
        imagestring($im, 4, 260, 280, 'Ref: 0048213', $gris);
    } else {
        imagealphablending($im, true);
        for ($i = 0; $i < 38; $i++) {
            [$r, $g, $b] = $rgb($colores[2 + $i % (count($colores) - 2)]);
            $c = imagecolorallocatealpha($im, $r, $g, $b, mt_rand(25, 80));
            $x = mt_rand(0, $w); $y = mt_rand(0, $h); $s = mt_rand(40, 190);
            imagefilledellipse($im, $x, $y, $s, $s, $c);
        }
    }
    $ruta = "$TMP/$nombre.jpg";
    imagejpeg($im, $ruta, 88);
    return ['name' => "$nombre.jpg", 'tmp_name' => $ruta, 'error' => UPLOAD_ERR_OK, 'size' => filesize($ruta), 'type' => 'image/jpeg'];
};
$firma = function (): string {
    $img = imagecreatetruecolor(400, 150);
    imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
    $tinta = imagecolorallocate($img, 20, 30, 80);
    imagesetthickness($img, 3);
    $px = 30; $py = 100;
    for ($x = 30; $x < 360; $x += 6) { $y = (int) (90 + 35 * sin($x / 18) * cos($x / 47)); imageline($img, $px, $py, $x, $y, $tinta); $px = $x; $py = $y; }
    ob_start(); imagepng($img); imagedestroy($img);
    return 'data:image/png;base64,' . base64_encode((string) ob_get_clean());
};
$cliente = fn(string $n, string $t, string $origen, string $ced = '') => ['nombre' => $n, 'telefono' => $t, 'identificacion' => $ced, 'email' => '', 'origen' => $origen];

// ---------- 1. Boda de Valeria y Andrés (la boda completa, en 6 meses) ----------
$fBoda = $sabado(180);
$boda = $ev->crear($nid, ['cliente' => $cliente('Valeria Andrade', '0998765432', 'instagram', '1718765432'), 'tipo' => 'boda', 'nombre' => 'Boda de Valeria y Andrés',
                          'fecha' => $fBoda, 'hora_inicio' => '16:00', 'hora_fin' => '01:00', 'invitados' => 150, 'espacios' => [$capilla, $hacienda],
                          'origen' => 'instagram', 'montaje' => 'redondas', 'presupuesto_cliente' => 18000, 'vendedor_id' => $U['id'],
                          'notas' => 'Quieren ceremonia al atardecer y recepción en jardín. Les encantan las flores blancas y el verde oliva.'], $U['id']);
$plan->guardarHonorario($nid, $boda, 'porcentaje', '10', $U['id'], 'Ana Pérez');
$plan->repartir($nid, $boda, 18000, $U['id'], 'Ana Pérez');
$part = fn(int $e, string $cat) => (int) $db->query("SELECT id FROM presupuesto_partidas WHERE evento_id = $e AND categoria = '$cat' ORDER BY id LIMIT 1")->fetchColumn();
$cambiar = function (int $e, string $cat, array $nuevo) use ($plan, $nid, $part, $db, $U) {
    $id = $part($e, $cat);
    $P = $plan->partida($nid, $id);
    $plan->guardarPartida($nid, $e, $nuevo + ['categoria' => $P['categoria'], 'concepto' => $P['concepto'], 'estado' => $P['estado'], 'paga' => $P['paga'],
                                             'estimado' => $P['estimado'], 'comision_pct' => $P['comision_pct'], 'visible_cliente' => true], $id, $U['id'], 'Ana Pérez');
    return $id;
};
$cambiar($boda, 'espacio', ['concepto' => 'Hacienda La Herradura (recepción)', 'estado' => 'contratado', 'paga' => 'cliente', 'contratado' => '2.500', 'comision_pct' => '0', 'vence_pago' => $d(40)]);
$banquete = $cambiar($boda, 'menu', ['concepto' => 'Banquete tres tiempos (150 personas)', 'proveedor_id' => $prov['menu'], 'estado' => 'contratado', 'paga' => 'planner', 'contratado' => '4.800', 'comision_pct' => '0']);
$fotoP = $cambiar($boda, 'foto_video', ['proveedor_id' => $prov['foto_video'], 'estado' => 'contratado', 'paga' => 'cliente', 'contratado' => '1.700', 'vence_pago' => $d(60), 'notas' => 'Incluye sesión preboda y álbum de 40 páginas.']);
$floresP = $cambiar($boda, 'flores', ['concepto' => 'Flores, ramo y boutonnières', 'proveedor_id' => $prov['flores'], 'estado' => 'contratado', 'paga' => 'cliente', 'contratado' => '650']);
$cambiar($boda, 'musica', ['proveedor_id' => $prov['musica'], 'estado' => 'cotizando', 'notas' => 'Comparar con orquesta en vivo para la hora loca.']);
$cambiar($boda, 'decoracion', ['proveedor_id' => $prov['decoracion'], 'estado' => 'cotizando']);
$cambiar($boda, 'belleza', ['proveedor_id' => $prov['belleza'], 'estado' => 'contratado', 'paga' => 'cliente', 'contratado' => '320']);
$db->prepare('UPDATE eventos SET presupuesto_cliente = 24000 WHERE id = ?')->execute([$boda]);   // el presupuesto real de los novios, con tus honorarios
$plan->registrarPago($nid, $boda, $fotoP, '850', $d(-12), 'transferencia', 'Anticipo 50 %', 'planner', $U['id'], 'Ana Pérez', $hoy);
$plan->registrarPago($nid, $boda, $floresP, '200', $d(-3), 'deuna', 'Reserva de flores', 'cliente', null, 'Valeria Andrade', $hoy);
$plan->registrarPago($nid, $boda, $part($boda, 'espacio'), '1.000', $d(-20), 'transferencia', 'Reserva de la hacienda', 'planner', $U['id'], 'Ana Pérez', $hoy);
$plan->cobrarComision($nid, $floresP, '65', $d(-2), $U['id'], 'Ana Pérez');
// Cotización con los honorarios (10 % de lo que suman las partidas), aceptada, contrato firmado y reserva pagada
$cBoda = $cot->crear($nid, $boda, $paquete('Organización integral de boda'), $U['id']);
$sug = $plan->honorarioSugerido($nid, $boda);
$plan->honorarioACotizacion($nid, $boda, $cBoda, (float) $sug['monto'], $U['id'], 'Ana Pérez');
$cot->agregarLineaLibre($nid, $cBoda, ['categoria' => 'menu', 'descripcion' => 'Banquete tres tiempos (Catering Sabores del Valle)', 'cantidad' => 150, 'unidad' => 'persona', 'precio_unitario' => 32, 'costo_unitario' => 32, 'con_iva' => true]);
$cot->enviar($nid, $cBoda, $U);
$cot->aceptar($nid, $cBoda, 'Valeria Andrade', '181.39.10.20', null);
$cuotas = $pag->cuotas($boda);
$pag->registrar($nid, $boda, ['monto' => $cuotas[0]['monto'], 'metodo' => 'transferencia', 'referencia' => 'PICH-48213', 'fecha' => $d(-10)], $U);
$pag->revisarSeparacion($nid, $boda, $U);
$kBoda = $con->generar($nid, $boda, null, $U);
$con->enviar($nid, $kBoda, $U);
$K = $con->obtener($nid, $kBoda);
$con->firmar($K['token'], 'Valeria Andrade Mena', '1718765432', $firma(), true, '181.39.10.20', 'Chrome en Android');
// Lista de tareas: las que ya debían estar hechas, hechas
foreach ($plan->tareas($boda) as $i => $t) {
    if ($t['vence'] && $t['vence'] <= $d(5) && $i % 5 !== 4) $plan->marcarTarea($nid, $boda, (int) $t['id'], true, $t['asignado'] === 'cliente' ? 'cliente' : 'planner', $t['asignado'] === 'cliente' ? null : $U['id'], $t['asignado'] === 'cliente' ? 'Valeria Andrade' : 'Ana Pérez');
}
$plan->agregarTarea($nid, $boda, 'Prueba de menú con los novios en Sabores del Valle', $d(9), 'planner', $U['id'], 'Ana Pérez');
$plan->agregarTarea($nid, $boda, 'Enviar la lista final de canciones al DJ', $d(150), 'cliente', $U['id'], 'Ana Pérez');
// Ideas: temática, paleta y fotos (del organizador y de la novia)
$ins->guardarEstilo($nid, $boda, ['#FFFFFF', '#E9DCC9', '#7D8F69', '#C9A86A', '#A0526D'], 'Jardín romántico al atardecer', 'Ana Pérez', $U['id']);
$paleta = ['#F6EFE6', '#E4D3BF', '#FFFFFF', '#7D8F69', '#C9A86A', '#F2C6C2'];
foreach ([['ramo', 'flores', 'Ramo en cascada con peonías blancas', 'planner', true], ['centro', 'decoracion', 'Centros de mesa bajos con velas', 'planner', true],
          ['arco', 'decoracion', 'Arco de ceremonia con follaje', 'cliente', true], ['torta', 'pasteleria', 'Torta de tres pisos semidesnuda', 'cliente', null],
          ['luces', 'decoracion', 'Guirnaldas de luces sobre la pista', 'planner', null], ['mesa', 'decoracion', 'Mesa imperial con camino de yute', 'planner', false]] as $i => [$n, $cat, $nota, $orig, $ok]) {
    $id = $ins->agregar($nid, $boda, $imagen($n, [$paleta[$i % 2], $paleta[($i + 1) % 2], ...array_slice($paleta, 2)], 40 + $i), $cat, $nota, $orig);
    if ($ok !== null) $ins->marcar($nid, $boda, $id, $ok);
}
$ev->agregarActividad($nid, $boda, null, 'reunion', 'Reunión de diseño con Valeria y Andrés: paleta y flores', $hoy->format('Y-m-d') . ' 16:00', $U['id']);
$ev->agregarActividad($nid, $boda, null, 'degustacion', 'Degustación del menú en Sabores del Valle', $d(9) . ' 12:30', $U['id']);

// ---------- 2. Quince años de Camila (en 6 semanas, separado, con cuota vencida y comprobante por revisar) ----------
$fQuince = $sabado(42);
$quince = $ev->crear($nid, ['cliente' => $cliente('Patricia Torres', '0992223344', 'referido', '1709876543'), 'tipo' => 'quince', 'nombre' => 'Quince años de Camila',
                            'fecha' => $fQuince, 'hora_inicio' => '19:00', 'hora_fin' => '02:00', 'invitados' => 120, 'espacios' => [$arupos],
                            'origen' => 'referido', 'presupuesto_cliente' => 9000, 'vendedor_id' => $equipo['vendedor']], $equipo['vendedor']);
$plan->repartir($nid, $quince, 9000, $U['id'], 'Ana Pérez');
$musQ = $cambiar($quince, 'musica', ['proveedor_id' => $prov['musica'], 'estado' => 'contratado', 'paga' => 'cliente', 'contratado' => '700', 'comision_pct' => '10']);
$cambiar($quince, 'foto_video', ['proveedor_id' => $prov['foto_video'], 'estado' => 'contratado', 'paga' => 'cliente', 'contratado' => '900', 'comision_pct' => '10']);
$cambiar($quince, 'pasteleria', ['proveedor_id' => $prov['pasteleria'], 'estado' => 'contratado', 'paga' => 'cliente', 'contratado' => '280', 'comision_pct' => '8']);
$cQ = $cot->crear($nid, $quince, $paquete('Paquete Quince Años'), $equipo['vendedor'], $hoy->modify('-40 days'));
$cot->agregarProducto($nid, $cQ, $producto('Coordinación del día del evento'));
$cot->enviar($nid, $cQ, null);
$cot->aceptar($nid, $cQ, 'Patricia Torres', '', $U, $hoy->modify('-38 days'));
$cq = $pag->cuotas($quince);
$pag->registrar($nid, $quince, ['monto' => $cq[0]['monto'], 'metodo' => 'deuna', 'fecha' => $d(-37)], $U);
$pag->revisarSeparacion($nid, $quince, $U);
$pag->subirComprobante($quince, $imagen('comprobante', ['#E8EEF6', '#C9D6EA', '#FFFFFF'], 7, true), 500, 'transferencia', 'Banco Pichincha 0048213');
$kQ = $con->generar($nid, $quince, null, $U);
$con->enviar($nid, $kQ, $U);
$plan->cargarListaTipica($nid, $quince, $hoy);
foreach ($plan->tareas($quince) as $i => $t) if ($t['vence'] && $t['vence'] <= $d(0) && $i % 3 !== 0) $plan->marcarTarea($nid, $quince, (int) $t['id'], true, 'planner', $U['id'], 'Ana Pérez');

// ---------- 3. Boda de María José y Santiago (este sábado: día del evento) ----------
$fCerca = $d(4);
$cerca = $ev->crear($nid, ['cliente' => $cliente('María José Paredes', '0995551122', 'google', '1715551122'), 'tipo' => 'boda', 'nombre' => 'Boda de María José y Santiago',
                           'fecha' => $fCerca, 'hora_inicio' => '17:00', 'hora_fin' => '01:00', 'invitados' => 90, 'espacios' => [$hacienda],
                           'origen' => 'google', 'montaje' => 'redondas', 'presupuesto_cliente' => 12000, 'vendedor_id' => $U['id']], $U['id']);
$cC = $cot->crear($nid, $cerca, $paquete('Paquete Boda Oro'), $U['id'], $hoy->modify('-150 days'));
$cot->enviar($nid, $cC, null);
$cot->aceptar($nid, $cC, 'María José Paredes', '', $U, $hoy->modify('-150 days'));
$total = (float) $db->query("SELECT total FROM eventos WHERE id = $cerca")->fetchColumn();
$pag->registrar($nid, $cerca, ['monto' => round($total * 0.3, 2), 'metodo' => 'transferencia', 'fecha' => $d(-148)], $U);
$pag->revisarSeparacion($nid, $cerca, $U);
$pag->registrar($nid, $cerca, ['monto' => round($total * 0.45, 2), 'metodo' => 'transferencia', 'fecha' => $d(-60)], $U);
$pag->registrar($nid, $cerca, ['monto' => round($total - round($total * 0.3, 2) - round($total * 0.45, 2), 2), 'metodo' => 'transferencia', 'fecha' => $d(-16)], $U);
$kC = $con->generar($nid, $cerca, null, $U);
$con->enviar($nid, $kC, $U);
$con->firmar($con->obtener($nid, $kC)['token'], 'María José Paredes Ruiz', '1715551122', $firma(), true, '186.4.1.1', 'Safari en iPhone');
$dia->cargarEjemplo($nid, $cerca);
foreach ($personal as $p) $dia->asignar($nid, $cerca, $p, [], $U);
foreach (['foto_video', 'musica', 'flores', 'pasteleria'] as $s) $dia->agregarProveedorEvento($nid, $cerca, ['proveedor_id' => $prov[$s], 'hora_llegada' => $s === 'flores' ? '13:00' : '15:30']);
$inv = new Invitados($db);
$inv->importar($nid, $cerca, "Nombre;Celular;Pases;Grupo\nFamilia Paredes Ruiz;0991000001;4;Familia de la novia\nFamilia Cevallos;0991000002;3;Familia del novio\nLucía Andrade;0991000003;2;Amigos\nCarlos Mena;0991000004;1;Trabajo\nGabriela Ortiz;0991000005;2;Amigos\nFamilia Torres Vega;0991000006;5;Familia de la novia\nRoberto Salinas;0991000007;2;Familia del novio\nDaniela Ruiz;0991000008;1;Amigos\nAndrés Pazmiño;0991000009;2;Trabajo\nFamilia León;0991000010;4;Familia del novio");
foreach (array_slice($inv->lista($nid, $cerca), 0, 7) as $i => $x) $inv->responder($x['token'], $i !== 3, max(1, (int) $x['pases'] - ($i === 0 ? 1 : 0)), $i === 1 ? 'Vegetariano' : '');
$inv->crearMesas($nid, $cerca, 9, 10);
$plan->repartir($nid, $cerca, 12000, $U['id'], 'Ana Pérez');
foreach ($plan->tareas($cerca) as $i => $t) if ($i % 6 !== 5) $plan->marcarTarea($nid, $cerca, (int) $t['id'], true, 'planner', $U['id'], 'Ana Pérez');
$db->prepare('UPDATE eventos SET coordinador_id = ? WHERE id = ?')->execute([$equipo['coordinador'], $cerca]);

// ---------- 4. Baby shower (cotizado, retenido) ----------
$baby = $ev->crear($nid, ['cliente' => $cliente('Sofía Herrera', '0996667788', 'facebook'), 'tipo' => 'baby_shower', 'fecha' => $sabado(12),
                          'hora_inicio' => '15:00', 'hora_fin' => '19:00', 'invitados' => 40, 'espacios' => [$arupos], 'origen' => 'facebook', 'vendedor_id' => $equipo['vendedor']], $equipo['vendedor']);
$cB = $cot->crear($nid, $baby, null, $equipo['vendedor']);
foreach (['Bocaditos de sal y dulce', 'Gaseosas, agua y hielo ilimitado', 'Decoración floral de mesas', 'Coordinación del día del evento'] as $p) $cot->agregarProducto($nid, $cB, $producto($p));
$cot->enviar($nid, $cB, null);
$ev->agregarActividad($nid, $baby, null, 'whatsapp', 'Preguntar si revisó la cotización', $d(0) . ' 10:00', $equipo['vendedor']);

// ---------- 5. Pedida de mano (consulta, lugar por definir) ----------
$pedida = $ev->crear($nid, ['cliente' => $cliente('Daniela Ruiz', '0993334455', 'referido'), 'tipo' => 'pedida', 'fecha' => $sabado(25),
                            'hora_inicio' => '19:00', 'hora_fin' => '23:00', 'invitados' => 30, 'espacios' => [], 'origen' => 'referido', 'presupuesto_cliente' => 2500,
                            'notas' => 'Sorpresa para la novia: quiere algo íntimo con cena y música en vivo.'], $U['id']);
$ev->agregarActividad($nid, $pedida, null, 'llamada', 'Llamar a Daniela para proponer dos lugares', $d(0) . ' 11:30', $U['id']);

// ---------- 6. Consulta nueva desde la página pública ----------
$web = $ev->crear($nid, ['cliente' => $cliente('Paula Jaramillo', '0997778899', 'pagina'), 'tipo' => 'boda', 'fecha' => $sabado(300), 'hora_inicio' => '17:00',
                         'hora_fin' => '01:00', 'invitados' => 200, 'espacios' => [], 'origen' => 'pagina', 'notas' => 'Escribió desde la página: boda en la playa o en una hacienda.'], null);

// ---------- 7. Corporativo realizado y graduación perdida ----------
$corp = $ev->crear($nid, ['cliente' => $cliente('Café Altura S.A.', '0994440011', 'cliente_anterior'), 'tipo' => 'corporativo', 'nombre' => 'Lanzamiento Café Altura',
                          'fecha' => $d(30), 'hora_inicio' => '18:00', 'hora_fin' => '22:00', 'invitados' => 80, 'espacios' => [$arupos], 'origen' => 'cliente_anterior', 'vendedor_id' => $equipo['vendedor']], $equipo['vendedor']);
$cCo = $cot->crear($nid, $corp, null, $equipo['vendedor'], $hoy->modify('-60 days'));
foreach (['Bocaditos de sal y dulce', 'Barra libre nacional (5 horas)', 'DJ con sonido e iluminación (6 horas)', 'Coordinación del día del evento'] as $p) $cot->agregarProducto($nid, $cCo, $producto($p));
$cot->enviar($nid, $cCo, null);
$cot->aceptar($nid, $cCo, 'Café Altura S.A.', '', $U, $hoy->modify('-60 days'));
$totCo = (float) $db->query("SELECT total FROM eventos WHERE id = $corp")->fetchColumn();
$pag->registrar($nid, $corp, ['monto' => $totCo, 'metodo' => 'transferencia', 'fecha' => $d(-45)], $U);
$pag->revisarSeparacion($nid, $corp, $U);
$plan->guardarPartida($nid, $corp, ['categoria' => 'musica', 'concepto' => 'DJ y sonido', 'proveedor_id' => $prov['musica'], 'estado' => 'contratado', 'paga' => 'cliente', 'contratado' => '650', 'comision_pct' => '10'], null, $U['id'], 'Ana Pérez');
$db->prepare("UPDATE eventos SET fecha = ?, estado = 'realizado' WHERE id = ?")->execute([$d(-18), $corp]);
$db->prepare("UPDATE evento_espacios SET periodo = tsrange(lower(periodo) - interval '48 days', upper(periodo) - interval '48 days') WHERE evento_id = ?")->execute([$corp]);
$grad = $ev->crear($nid, ['cliente' => $cliente('Colegio Los Pinos', '0992221100', 'google'), 'tipo' => 'graduacion', 'fecha' => $sabado(60), 'hora_inicio' => '19:00',
                          'hora_fin' => '01:00', 'invitados' => 180, 'espacios' => [], 'origen' => 'google'], $equipo['vendedor']);
$ev->cambiarEstado($nid, $grad, 'perdido', ['motivo' => 'Precio alto'], $U);

// Avisos que llegan a la campana
(new \TuPlanner\Notificaciones($db))->alNegocio($nid, 'Nueva consulta desde tu página: Paula Jaramillo, boda para 200 invitados.', $web);

// ---------- Negocio en plan Esencial (para mostrar «No está en tu plan») ----------
$altaE = (new Altas($db))->crearNegocioConDueno('Organiza Fácil Eventos', 'Lorena Castro', '0990001122', 'esencial@tuplanner.tukanec.com', $CLAVE, 'esencial', 'Cuenca', 'super');
$db->prepare("UPDATE negocios SET estado = 'activo', activo_hasta = ? WHERE id = ?")->execute([$d(200), $altaE['negocio_id']]);
$db->prepare("UPDATE usuarios SET clave_temporal = false WHERE negocio_id = ?")->execute([$altaE['negocio_id']]);
$evE = $ev->crear($altaE['negocio_id'], ['cliente' => $cliente('Mónica Vélez', '0990002233', 'instagram'), 'tipo' => 'boda', 'fecha' => $sabado(90), 'hora_inicio' => '17:00',
                                         'hora_fin' => '01:00', 'invitados' => 100, 'espacios' => []], null);

$db->exec("UPDATE negocios SET pasos_ocultos = true WHERE id = $nid");

// Ayuda e IA (110): preguntas frecuentes de ejemplo (el plan Agencia ya trae el Asistente IA incluido: migración 111)
require_once RAIZ . '/src/tukan_ia.php';
$faqs = new \TukanIaFaqPdo($db);
$faqs->agregar('¿Cómo registro un pago?', "1. Abra el evento y toque la pestaña **Pagos**.\n2. En **Registrar un pago recibido** escriba el valor, la forma de pago y la fecha.\n3. Toque **Guardar pago y generar recibo**.\nCuando lo pagado completa la reserva, la fecha queda **separada**.");
$faqs->agregar('¿Cómo agrego una partida al presupuesto?', "1. Abra el evento y toque **Planificación**.\n2. En **Presupuesto por proveedor** toque **+ Agregar partida**.\n3. Escriba qué es, el proveedor, quién le paga y el estimado, y toque **Guardar**.");

array_map('unlink', glob("$TMP/*") ?: []);
@rmdir($TMP);
echo json_encode(['nid' => $nid, 'slug' => $alta['slug'], 'boda' => $boda, 'quince' => $quince, 'cerca' => $cerca, 'baby' => $baby, 'pedida' => $pedida,
                  'web' => $web, 'corp' => $corp, 'grad' => $grad, 'esencial_evento' => $evE, 'cot_boda' => $cBoda, 'contrato_boda' => $kBoda], JSON_PRETTY_PRINT) . "\n";
