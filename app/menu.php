<?php
declare(strict_types=1);

/**
 * Menú de TuPlanner (identidad gráfica común Tukán, docs/LINEA_GRAFICA.md). UNA sola definición para el menú de la izquierda
 * y la pantalla «Más». Devuelve los grupos: [título, [[ruta, texto, emoji, ayuda corta], …]].
 * Ninguna opción va sin emoji. Los módulos apagados y los permisos del usuario quitan opciones, igual que antes.
 */
function tk_menu(array $u): array
{
    $coord = $u['rol'] === 'coordinador';
    $admin = es_admin_total($u);
    $mod = fn(string $id): bool => modulo_activo($u, $id);

    $principal = [['inicio', 'Inicio', '📊', 'Lo de hoy y las cifras del mes'], ['calendario', 'Calendario', '📅', 'Fechas libres y ocupadas'],
                  ['eventos', 'Eventos', '🎉', 'Consultas, cotizados y vendidos'], ['notificaciones', 'Avisos', '🔔', 'Pagos, firmas y consultas nuevas']];

    $ventas = [];
    if (!$coord) {
        $ventas[] = ['clientes', 'Clientes', '👥', 'Fichas e historial'];
        $ventas[] = ['seguimiento', 'Seguimiento', '📞', 'Llamadas y visitas pendientes'];
        $ventas[] = ['mensajes', 'Mensajes por WhatsApp', '💬', 'Recordatorios listos para enviar'];
        $ventas[] = ['catalogo', 'Catálogo y precios', '🏷️', 'Tus servicios y lo que revendes'];
        if ($mod('paquetes')) $ventas[] = ['paquetes', 'Paquetes', '📦', 'Organización total, coordinación del día…'];
        if ($mod('cuestionarios')) $ventas[] = ['extras', 'Extras del portal', '🎁', 'Lo que el cliente pide en línea: hora extra, fotocabina…'];
        if ($mod('metas') && $admin) $ventas[] = ['metas', 'Metas de ventas', '🎯', 'Cuánto quieres vender cada mes'];
    }

    $dinero = [];
    if (!$coord) {
        $dinero[] = ['cobros', 'Cobros', '💳', 'Lo que te deben y lo que ya cobraste'];
        if ($mod('proveedores')) $dinero[] = ['por_pagar', 'Por pagar', '🧾', 'Lo que debes a proveedores y cuándo vence'];
        if ($mod('comisiones') && es_comercial($u)) $dinero[] = ['comisiones', 'Comisiones', '🤝', 'Lo que te pagan los proveedores por llevarles clientes'];
        $dinero[] = ['reportes', 'Reportes', '📈', 'Ventas, cobranza, ocupación'];
    }

    $operacion = [['espacios', 'Lugares', '🏛️', 'Quintas, haciendas, iglesias y fechas cerradas']];
    if ($mod('plano')) $operacion[] = ['plano', 'Planos del salón', '🗺️', 'Distribución de mesas de cada lugar'];
    if ($mod('dia_evento')) $operacion[] = ['personal', 'Personal', '🧑‍🍳', 'Coordinadores, asistentes, meseros'];
    if ($mod('proveedores')) $operacion[] = ['proveedores', 'Proveedores', '🚚', 'DJ, fotógrafos, floristas'];
    if ($mod('incidencias')) $operacion[] = ['incidencias', 'Incidencias', '⚠️', 'Problemas de los eventos y cómo se resolvieron'];
    if ($mod('mobiliario')) $operacion[] = ['mobiliario', 'Decoración y menaje', '🪑', 'Lo tuyo que llevas a los eventos: bases, velas, mantelería'];

    $config = [];
    if ($admin) {
        $config[] = ['configuracion', 'Configuración', '⚙️', 'Datos, reglas, cobros, página pública'];
        if ($mod('contratos')) $config[] = ['contrato', 'Plantillas de contrato', '✍️', 'Cláusulas y variables del contrato'];
        if ($mod('cuestionarios')) $config[] = ['cuestionarios', 'Formularios del evento', '📋', 'Preguntas para el cliente por tipo de evento'];
        $config[] = ['usuarios', 'Usuarios', '🧑‍💼', 'Vendedores y coordinadores'];
        $config[] = ['tufactura', 'Factura electrónica', '🧾', 'Factura del SRI de cada pago, con TuFactura'];
        $config[] = ['planes', 'Mi plan', '⭐', en_app_android() ? 'Tu plan y tus módulos' : 'Tus módulos, lo que pagas y lo que puedes agregar'];
    }
    $config[] = ['mi_cuenta', 'Mi cuenta', '👤', 'Contraseña y Telegram'];
    $config[] = ['ayuda', 'Ayuda', '❓', 'Pregunte cómo hacer algo y le respondemos al instante'];

    return array_values(array_filter([['Principal', $principal], ['Ventas', $ventas], ['Dinero', $dinero], ['Operación', $operacion], ['Configuración', $config]], fn($g) => $g[1]));
}

/** A qué opción del menú pertenece la pantalla actual (las pantallas de adentro marcan a su «madre»). */
function tk_menu_actual(string $ruta, bool $conId): string
{
    static $madre = [
        'evento' => 'eventos', 'evento_nuevo' => 'eventos', 'evento_editar' => 'eventos', 'cotizacion' => 'eventos', 'evento_pagos' => 'eventos',
        'evento_dia' => 'eventos', 'invitados' => 'eventos', 'evento_formulario' => 'eventos', 'ia_cotizacion' => 'eventos',
        'ia_proveedor' => 'eventos', 'evento_plan' => 'eventos', 'evento_ideas' => 'eventos',
        'cliente' => 'clientes', 'proveedor' => 'proveedores', 'compras' => 'proveedores', 'compra' => 'proveedores', 'puntaje_proveedores' => 'proveedores',
    ];
    if (in_array($ruta, ['contrato', 'plano'], true) && $conId) return 'eventos';   // el contrato y el plano DE un evento
    return $madre[$ruta] ?? $ruta;
}

/** Iniciales de un nombre: «Dimitry Quishpe» → «DQ». */
function tk_iniciales(string $nombre): string
{
    $p = preg_split('/\s+/', trim($nombre)) ?: [];
    $i = mb_substr($p[0] ?? '', 0, 1) . mb_substr($p[1] ?? '', 0, 1);
    return mb_strtoupper($i !== '' ? $i : '?');
}
