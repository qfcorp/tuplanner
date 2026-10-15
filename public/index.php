<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

/*
 * Rutas de TuPlanner: /?r=<ruta>. Cada ruta es un archivo app/paginas/<ruta>.php
 */

// Páginas públicas (sin iniciar sesión): acceso, página del negocio, enlaces secretos del cliente, invitados y personal
$publicas = [
    'login', 'registro', 'privacidad', 'admin_entrar', 'telegram',
    'sitio', 'consulta', 'disponible', 'imagen_publica',      // página pública del negocio
    'cot', 'firmar', 'verificar',                              // cotización, firma y verificación del contrato
    'portal', 'portal_pagar', 'payphone', 'portal_invitados', // portal del cliente: pagos y lista de invitados
    'invitacion', 'puerta', 'ingreso',                         // invitados: confirmación, control de ingreso con QR
    'vivo', 'staff', 'ical',                                   // cronograma en vivo, portal del personal, calendario externo
];
// Páginas del sistema (con sesión)
$privadas = [
    'inicio', 'calendario', 'eventos', 'evento', 'evento_nuevo', 'evento_editar',
    'cotizacion', 'contrato', 'evento_pagos', 'evento_dia', 'invitados',
    'clientes', 'cliente', 'seguimiento', 'cobros', 'mensajes',
    'catalogo', 'paquetes', 'espacios', 'personal', 'proveedores', 'mobiliario',
    'reportes', 'exportar', 'configuracion', 'usuarios', 'mi_cuenta', 'planes', 'prueba_terminada',
    'notificaciones', 'archivo', 'pdf', 'buscar', 'mas', 'salir',
];
// Lo que puede abrir un coordinador (el día del evento); lo demás lo hace el dueño o el vendedor
$deCoordinador = ['inicio', 'calendario', 'eventos', 'evento', 'evento_dia', 'invitados', 'personal', 'proveedores', 'mobiliario', 'espacios',
                  'mi_cuenta', 'notificaciones', 'archivo', 'pdf', 'buscar', 'mas', 'salir', 'prueba_terminada'];
// Plano del salón (el coordinador también lo arma) y metas de ventas — módulo 080
array_push($privadas, 'plano', 'metas');
$deCoordinador[] = 'plano';
// Panel del super administrador (Tukán): sesión aparte
$deAdmin = ['admin', 'admin_negocios', 'admin_negocio', 'admin_nuevo', 'admin_planes', 'admin_ver', 'admin_volver', 'admin_salir'];
// Portal del cliente, segunda parte (migración 070): formulario del evento, extras pedidos en línea y encuesta de satisfacción
array_push($publicas, 'portal_cuestionario', 'portal_extras', 'encuesta');
array_push($privadas, 'evento_formulario', 'cuestionarios', 'extras');
$deCoordinador[] = 'evento_formulario';   // el coordinador ve las respuestas del formulario (útil el día del evento)
$publicas[] = 'firmar_anexo';             // anexo de contrato por extras aprobados (migración 090)
// Proveedores y compras (migración 060): portal del proveedor, verificación de la orden de compra, ficha, compras, por pagar e incidencias
array_push($publicas, 'portal_proveedor', 'verificar_oc');
array_push($privadas, 'proveedor', 'compras', 'compra', 'por_pagar', 'incidencias', 'proveedor_archivo');
array_push($deCoordinador, 'proveedor', 'incidencias', 'proveedor_archivo');   // ficha sin dinero, incidencias y documentos
// Cómo se calcula el puntaje de proveedores (091): el dueño lo cambia; los demás ven la explicación
$privadas[] = 'puntaje_proveedores'; $deCoordinador[] = 'puntaje_proveedores';

$ruta = (string) ($_GET['r'] ?? 'inicio');
if (in_array($ruta, $deAdmin, true) && !superadmin()) {
    redirigir('admin_entrar');
}
if (!in_array($ruta, array_merge($publicas, $privadas, $deAdmin), true) || !is_file(RAIZ . "/app/paginas/$ruta.php")) {
    http_response_code(404);
    $ruta = usuario() ? 'inicio' : 'login';
}

if (in_array($ruta, $privadas, true)) {
    $u = requiere_sesion();
    if ($u['rol'] === 'coordinador' && !in_array($ruta, $deCoordinador, true)) {
        redirigir('inicio');
    }
    // Prueba vencida, plan vencido o cuenta suspendida: solo puede ver cómo pagar
    if (!in_array($ruta, ['prueba_terminada', 'salir', 'planes'], true)
        && !planes()->alDia($u) && !(!empty($_SESSION['admin_viendo']) && superadmin())) {
        redirigir('prueba_terminada');
    }
}

try {
    require RAIZ . "/app/paginas/$ruta.php";
} catch (Throwable $e) {
    error_log('TuPlanner: ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) http_response_code(500);
    if (in_array($ruta, ['disponible', 'buscar'], true)) {
        header('Content-Type: application/json');
        echo json_encode(['error' => true]);
    } elseif (in_array($ruta, $publicas, true)) {
        vista_publica('error_publico', ['mensaje' => 'Algo falló al abrir esta página. Intenta de nuevo en un momento.'], 'TuPlanner');
    } else {
        vista('error', ['mensaje' => 'Algo falló al procesar esta página. Intenta de nuevo; si sigue pasando, escríbenos por WhatsApp.']);
    }
}
