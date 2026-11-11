<?php
use TuPlanner\{Modulos, PagoPlan, PanelTukan};

/**
 * Mi plan (dueño): sus módulos encendidos, lo que paga al mes por SU plan, los módulos y planes que puede pedir y sus pagos.
 * Regla del dueño de Tukán: el precio de lo que el cliente NO tiene nunca se muestra; siempre se pide cotización.
 * Planes de $20 y $45 (migración 112): en Básico el cliente elige 3 módulos incluidos; «Tu plan trae 3 módulos a tu elección».
 * "Pedir cotización" (de un módulo o de un plan) crea un pedido (solicitud cambio_modulos) que llega al Panel Tukán Central.
 * También se abre con la demo terminada o el plan vencido.
 * (10 nov 2026) Pagar solo con tarjeta: si el Panel Tukán Central tiene prendido el cobro con tarjeta para TuPlanner, el dueño
 * elige plan y período y paga aquí mismo (src/PagoPlan.php). Si no, o en la app de Google Play, esta página queda como siempre.
 */
const NO_EN_APP = 'Desde la app solo se eligen los módulos incluidos en tu plan.';
exigir(es_admin_total($u), 'Solo el dueño o un administrador puede ver y cambiar el plan de TuPlanner.');
$nid = (int) $u['negocio_id'];
// «Cancelar mi plan» (módulo común src/TukanCancelarPlan.php, 7 nov 2026)
require_once dirname(__DIR__, 2) . '/src/TukanCancelarPlan.php';
require_once dirname(__DIR__, 2) . '/src/TukanLegal.php';
if (es_post() && ($_POST['accion'] ?? '') === 'aceptar_terminos_tukan') {   // aviso de arriba (vistas/_diseno.php)
    verificar_csrf();
    $okTl = TukanLegal::procesar(db(), (string) $nid, $u['nombre'] . ' <' . $u['email'] . '>');
    aviso($okTl ? 'Gracias: aceptaste los términos y la política de privacidad.' : 'Para seguir, marca «Leí y acepto los términos y la política de privacidad».', $okTl ? 'ok' : 'error');
    header('Location: ' . url('inicio'));
    exit;
}
if (es_post() && in_array($_POST['accion'] ?? '', ['cancelar_plan', 'seguir_plan'], true)) {
    verificar_csrf();
    try {
        if (en_app_android()) throw new RuntimeException('En la app cancelas en Google Play → Pagos y suscripciones.');
        if ($_POST['accion'] === 'seguir_plan') {
            TukanCancelarPlan::seguir(db(), (string) $nid, (string) $u['negocio'], (string) $u['email']);
            aviso('¡Qué bueno! Tu plan sigue como siempre.');
        } else {
            if (empty($_POST['confirmo'])) throw new RuntimeException('Para cancelar, marca «Sí, quiero cancelar mi plan».');
            $rTc = TukanCancelarPlan::pedir(db(), (string) $nid, (string) $u['negocio'], $u['activo_hasta'] ?? null, (string) ($_POST['motivo'] ?? ''),
                $u['nombre'] . ' <' . $u['email'] . '>', (string) $u['email']);
            aviso('Listo: tu plan no se renovará.' . ($rTc['hasta'] ? ' Sigues usando todo hasta el ' . date('d/m/Y', strtotime($rTc['hasta'])) . '.' : '')
                . ' Tus datos no se borran. Si cambias de idea, toca «Seguir con mi plan».');
        }
    } catch (RuntimeException $e) {
        aviso($e->getMessage(), 'error');
    }
    header('Location: ' . url('planes') . '#cancelar-plan');
    exit;
}
// Pagar el plan con tarjeta, solo (src/PagoPlan.php). En la app de Google Play no existe (regla de Google).
$pagoPlan = en_app_android() ? null : new PagoPlan(db());
// «Pasarme al Completo» con suscripción con tarjeta pagada (10 nov 2026, tercera parte): cambia de plan ya y el Panel cobra el
// Completo (prorrateo aparte si sube; si baja, desde el próximo cobro). Sin suscripción va a pagar con tarjeta. Sin tarjeta, no cambia.
if (es_post() && ($_POST['accion'] ?? '') === 'pasar_completo') {
    verificar_csrf();
    try {
        if (!$pagoPlan) throw new RuntimeException(NO_EN_APP);
        $sug = $pagoPlan->sugerenciaCompleto($nid);
        $codCompleto = $sug['plan'] ?? null;
        foreach (modulos()->planes() as $cod => $pl) if (!empty($pl['todos'])) $codCompleto ??= (string) $cod;
        if (!$codCompleto || $codCompleto === (string) $u['plan_codigo']) throw new RuntimeException('Ya tienes el plan Completo.');
        if (modulos()->estado($nid)['precio_acordado'] !== null) throw new RuntimeException('Tienes un precio acordado con Tukán: escríbenos por WhatsApp para cambiar de plan.');
        // (10 nov 2026, corrección de Dimitry: «debe cobrarse… un método de pago válido y pagar el prorrateo») Nadie pasa al
        // Completo sin pagar. Sin cobro con tarjeta disponible (Panel apagado, caído o sin conectar) el plan NO cambia y no se crea pedido.
        if (!$pagoPlan->activo($nid, (string) $u['email'])) throw new RuntimeException(PagoPlan::SIN_TARJETA);
        $sus = $pagoPlan->suscripcionPagada($nid, (string) $u['email']);
        if (!$sus) {
            // Con cobro con tarjeta y sin suscripción pagada: va a pagar el Completo (queda marcado). El plan cambia al aprobarse el pago.
            if ($pagoPlan->suscripcion($nid)) throw new RuntimeException('Tienes un pago con tarjeta sin terminar: termínalo o cancélalo y vuelve a tocar «Pasarme al Completo».');
            // (10 nov 2026, 18:26) Cliente con el período pagado vigente y sin suscripción: paga con tarjeta solo el PRORRATEO de los
            // días que le faltan. El plan cambia cuando el Panel dice que quedó pagado (PagoPlan::aplicarMejoras, más abajo).
            if ($pagoPlan->mejora($nid)) {
                $r = $pagoPlan->pedirMejora($u, url_completa('planes'));
                if (!empty($r['sin_cobro'])) {
                    aviso('Listo: ya tienes el plan Completo. La diferencia es menor a $1: no se cobra. Tu vencimiento no cambia.');
                    header('Location: ' . url('planes'));
                } else {
                    header('Location: ' . $r['url_pago']);
                }
                exit;
            }
            // Demo o período vencido: paga el Completo entero (queda marcado)
            header('Location: ' . url('planes', ['pagar' => $codCompleto]) . '#pagar-' . rawurlencode($codCompleto));
            exit;
        }
        // Con suscripción con tarjeta pagada: cambia ya y el Panel cobra (prorrateo aparte si sube; si baja, desde el próximo cobro).
        // Si el Panel no acepta el cambio, el plan se queda como estaba.
        $mods = modulos();
        $antes = $mods->estado($nid);
        db()->beginTransaction();
        try {
            (new PanelTukan(db()))->cambiarPlan($nid, $codCompleto);
            $mods->olvidar();
            PagoPlan::revisarBaja($sus, $antes, $mods->estado($nid), $u['activo_hasta'] ?? null, true);
            $m = $pagoPlan->ajustar($nid, $sus, (string) $u['email'], true);
            if ($m !== null && !$pagoPlan->ultimoOk) throw new RuntimeException('No se pudo cobrar el cambio, así que tu plan sigue igual. ' . $m);
            db()->commit();
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            $mods->olvidar();
            throw $e;
        }
        aviso($m ?? 'Pasaste al plan Completo.');
    } catch (RuntimeException $e) {
        aviso($e->getMessage(), 'error');
    }
    header('Location: ' . url('planes'));
    exit;
}
if (es_post() && in_array($_POST['accion'] ?? '', ['pagar_tarjeta', 'cancelar_tarjeta'], true)) {
    verificar_csrf();
    try {
        if (!$pagoPlan) throw new RuntimeException(NO_EN_APP);
        if ($_POST['accion'] === 'cancelar_tarjeta') {
            $pagoPlan->cancelar($nid, (int) ($_POST['id'] ?? 0), (string) $u['email']);
            aviso('Listo: ese pago con tarjeta quedó cancelado. Ya puedes elegir otro.');
        } else {
            // «opcion» = plan|período (el formulario muestra todos los planes con su precio); también sirven «plan» y «periodo» sueltos
            [$planElegido, $periodoElegido] = isset($_POST['opcion']) ? array_pad(explode('|', (string) $_POST['opcion'], 2), 2, '') : [(string) ($_POST['plan'] ?? ''), (string) ($_POST['periodo'] ?? '')];
            $cobro = $pagoPlan->crear($u, $planElegido, $periodoElegido, ($_POST['forma'] ?? '') !== 'una_vez', url_completa('planes'));
            header('Location: ' . $cobro['url_pago']);   // la página de pago segura (Plux, por Tukán Pagos)
            exit;
        }
    } catch (RuntimeException $e) {
        aviso($e->getMessage(), 'error');
    }
    header('Location: ' . url('planes') . '#pagar-tarjeta');
    exit;
}
// (10 nov 2026, 18:26) ¿Ya quedó pagado el prorrateo de un cambio de plan? Se pone el plan nuevo, una sola vez.
if (!es_post() && $pagoPlan && ($planPuesto = $pagoPlan->aplicarMejoras($nid, (string) $u['email']))) {
    aviso('¡Pago recibido! Ya tienes el plan ' . $planPuesto . ' con todos los módulos. Tu vencimiento no cambia: en la renovación pagas el plan ' . $planPuesto . '.');
    header('Location: ' . url('planes'));
    exit;
}
if (!es_post()) TukanCancelarPlan::reintentar(db(), (string) $nid, (string) $u['email']);
$cancelacion = TukanCancelarPlan::estado(db(), (string) $nid);
if (es_post()) {
    verificar_csrf();
    try {
        $accion = (string) ($_POST['accion'] ?? '');
        // App de Google Play: no se piden cotizaciones ni planes (sería comprar el software por fuera de Google Play)
        if (en_app_android() && $accion === 'pedir_plan') throw new RuntimeException(NO_EN_APP);
        if ($accion === 'pedir_plan') {
            // Cotizar otro plan: se piden los módulos de ese plan que hoy no tiene (mismo pedido cambio_modulos del Panel Central)
            $codigo = (string) ($_POST['plan'] ?? '');
            $pm = modulos()->planes()[$codigo] ?? null;
            if (!$pm || $codigo === (string) $u['plan_codigo']) throw new RuntimeException('Ese plan no existe.');
            $faltan = array_values(array_diff($pm['todos'] ? array_keys(modulos()->catalogo()) : $pm['modulos'], modulos()->estado($nid)['pagados']));
            if (!$faltan) throw new RuntimeException('Ese plan no te suma módulos. Escríbenos por WhatsApp y te ayudamos.');
            $nombrePlan = $pm['nombre'];
            modulos()->pedir($nid, (int) $u['id'], $faltan, trim('Pide cotización del plan ' . $nombrePlan . '. ' . campo('nota')));
            aviso('Listo: pediste la cotización del plan ' . $nombrePlan . '. Tukán te escribe con el valor; tus datos no cambian.');
            header('Location: ' . url('planes') . '#mp-t-planes');
            exit;
        }
        if ($accion !== 'pedir') throw new RuntimeException('Acción no válida.');
        $mid = (string) ($_POST['modulo'] ?? '');
        if (!Modulos::existe($mid)) throw new RuntimeException('Ese módulo no existe.');
        // Planes de $20 y $45 (migración 112): si su plan aún tiene módulos por elegir, es uno de esos (incluido en su plan)
        $eligeIncluido = modulos()->estado($nid)['por_elegir'] > 0 && !in_array($mid, Modulos::FUERA_DEL_CUPO, true);
        if (en_app_android() && !$eligeIncluido) throw new RuntimeException(NO_EN_APP);   // en la app solo se eligen los incluidos
        modulos()->pedir($nid, (int) $u['id'], [$mid], trim(($eligeIncluido ? 'Lo elige entre los módulos incluidos en su plan. ' : '') . campo('nota')));
        aviso($eligeIncluido
            ? 'Listo: elegiste ' . Modulos::nombre($mid) . ' entre los módulos de tu plan. Tukán lo enciende y te avisa; tus datos no cambian.'
            : 'Listo: pediste la cotización de ' . Modulos::nombre($mid) . '. Tukán te escribe con el valor y para activarlo; tus datos no cambian.');
    } catch (RuntimeException $e) {
        aviso($e->getMessage(), 'error');
    }
    header('Location: ' . url('planes') . '#mod-' . rawurlencode((string) ($_POST['modulo'] ?? '')));
    exit;
}
$p = planes();
$mods = modulos();
$estadoMod = $mods->estado($nid);
$catalogo = $mods->catalogo();
$planesMod = $mods->planes();
$precios = [];
foreach (['mensual', 'semestral', 'anual'] as $per) $precios[$per] = $p->cotizarNegocio($nid, (string) $u['plan_codigo'], $per);
$estadoCuenta = $p->estadoCuenta($u);
$pagos = (new PanelTukan(db()))->pagos($nid, 30);
$pedidos = $mods->solicitudesDe($nid);
$pedidosPendientes = [];
foreach ($pedidos as $s) if ($s['estado'] === 'pendiente') foreach (Modulos::parse($s['encender']) as $m) $pedidosPendientes[$m] = true;
$st = db()->prepare("SELECT (SELECT count(*) FROM espacios WHERE negocio_id = ? AND activo) AS espacios,
                            (SELECT count(*) FROM usuarios WHERE negocio_id = ? AND activo AND rol <> 'admin') AS usuarios");   // los administradores no cuentan en el plan
$st->execute([$nid, $nid]);
$uso = $st->fetch();
$listaPlanes = $p->lista();
// Pago con tarjeta: solo si el Panel dice que está prendido para TuPlanner (si no contesta o está apagado, $tarjeta = null y nada cambia)
$tarjeta = null;
if ($pagoPlan && $pagoPlan->activo($nid, (string) $u['email'])) {
    $planesPago = $pagoPlan->planes($nid);
    $opcionesPago = $incluyePago = [];
    foreach (array_keys($planesPago) as $cod) {   // (10 nov 2026) todos los planes con su precio, de entrada
        $opcionesPago[$cod] = $pagoPlan->opciones($nid, $cod);
        $incluyePago[$cod] = $mods->estado($nid, $cod);
    }
    $tarjeta = ['marcar' => (string) ($_GET['pagar'] ?? ''), 'pruebas' => $pagoPlan->pruebas($nid), 'vivos' => $pagoPlan->vivos($nid), 'suscripcion' => $pagoPlan->suscripcion($nid),
                'planes' => $planesPago, 'opciones' => array_filter($opcionesPago),
                'incluye' => $incluyePago, 'vuelta' => (string) ($_GET['plux'] ?? '')];
}
// (10 nov 2026, 18:26) Subir al Completo con el período pagado vigente: se paga solo el prorrateo (con tarjeta o por WhatsApp).
// No aplica si ya tiene una suscripción con tarjeta pagada (esa se ajusta sola con «cambiar»).
$mejora = $pagoPlan ? $pagoPlan->mejora($nid) : null;
if ($mejora && $tarjeta && $tarjeta['suscripcion'] && in_array($tarjeta['suscripcion']['estado'], ['activa', 'en_gracia'], true)) $mejora = null;
if ($mejora && $tarjeta) unset($tarjeta['opciones'][$mejora['plan']]);   // el Completo no se paga entero: se paga la diferencia
// «Te sugerimos el Completo» (10 nov 2026, tercera parte): solo fuera de la app, para el Básico sin precio acordado
$sugerencia = $pagoPlan ? $pagoPlan->sugerenciaCompleto($nid) : null;
$sugerenciaModulo = [];   // módulo => lo que pagaría con él, si iguala o pasa al Completo (se avisa antes de pedirlo)
if ($pagoPlan && $estadoMod['precio_acordado'] === null && empty($planesMod[$u['plan_codigo']]['todos'])) {
    foreach (array_keys($catalogo) as $mid) {
        if (in_array($mid, $estadoMod['pagados'], true) || ($estadoMod['por_elegir'] > 0 && !in_array($mid, Modulos::FUERA_DEL_CUPO, true))) continue;
        $sm = $pagoPlan->sugerenciaCompleto($nid, [$mid]);
        if ($sm && $sm['tipo'] === 'mayor') $sugerenciaModulo[$mid] = $sm;
    }
}
vista('planes', compact('cancelacion', 'estadoMod', 'catalogo', 'planesMod', 'precios', 'estadoCuenta', 'pagos', 'pedidos', 'pedidosPendientes', 'uso', 'listaPlanes', 'tarjeta', 'sugerencia', 'sugerenciaModulo', 'mejora'),
      'Mi plan · TuPlanner');
