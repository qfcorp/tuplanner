<?php
declare(strict_types=1);

namespace TuPlanner;

use PDO;
use RuntimeException;

require_once __DIR__ . '/TukanCancelarPlan.php';

/**
 * Pagar el plan SOLO, con tarjeta, desde «Mi plan» (10 nov 2026, pedido de Dimitry: «¿el cliente puede solicitar y pagar
 * desde su mismo panel… o debo hacerlo yo?»).
 *
 * TuPlanner NO habla con Plux. Le pide el cobro al Panel Tukán Central (plux_api.php, con la misma firma de «Cancelar mi plan»:
 * \TukanCancelarPlan::llamarPanel). El Panel lo crea en Tukán Pagos y devuelve la dirección de la página de pago. Cuando Plux
 * aprueba, el Panel registra el pago aquí con «registrar_pago» (conector v2) y emite la factura: la cuenta queda activa sola,
 * también si era una demo.
 *
 *  - El precio es el que TuPlanner ya calcula (Planes::cotizarNegocio: plan + módulos extra + Asistente IA, o el precio
 *    acordado), SIN IVA: el IVA lo suma Tukán Pagos. Semestral paga 5 y recibe 6; anual paga 10 y recibe 12.
 *  - Por defecto es suscripción (se cobra sola cada período). También se puede pagar una sola vez.
 *  - Si el Panel no contesta o el cobro con tarjeta está apagado para TuPlanner, «Mi plan» queda como siempre.
 *  - En la app de Google Play no se usa (lo decide la página: en_app_android()).
 *  - (10 nov 2026, segunda parte, decisión de Dimitry) Si cambian los módulos o el plan DENTRO de TuPlanner (Panel Tukán del
 *    programa) y el negocio tiene una suscripción con tarjeta ya pagada: si el valor sube se manda «cambiar» al Panel (cobra el
 *    valor nuevo desde el próximo cobro y el proporcional aparte); quitar módulos contratados o bajar el valor NO se permite
 *    mientras dure el período pagado (revisarBaja). Los cambios hechos desde el Panel Tukán Central los ajusta el Panel.
 */
final class PagoPlan
{
    /** Estados de una suscripción con tarjeta que sigue viva (no se puede crear otra). */
    public const VIVAS = ['pendiente', 'rechazado', 'activa', 'en_gracia'];
    public const PERIODOS_TEXTO = ['mensual' => 'cada mes', 'semestral' => 'cada 6 meses', 'anual' => 'cada año'];

    /** @var callable fn(array $datos, string $email): array{0:int,1:?array} */
    private $llamar;
    private array $estados = [];

    /** $llamar: quién le habla al Panel (en las pruebas, un Panel simulado). */
    public function __construct(private PDO $db, ?callable $llamar = null)
    {
        $this->llamar = $llamar ?? [\TukanCancelarPlan::class, 'llamarPanel'];
    }

    /** Lo que dice el Panel de los pagos con tarjeta de este negocio (una vez por página). null = no contesta o no está conectado. */
    public function estado(int $negocioId, string $email = ''): ?array
    {
        if (!array_key_exists($negocioId, $this->estados)) {
            [$http, $j] = ($this->llamar)(['accion' => 'estado', 'cliente_id' => (string) $negocioId], $email);
            $this->estados[$negocioId] = $http === 200 && is_array($j) && !empty($j['ok']) && array_key_exists('activo', $j) ? $j : null;
        }
        return $this->estados[$negocioId];
    }

    /** ¿El Panel tiene prendido el cobro con tarjeta para TuPlanner? */
    public function activo(int $negocioId, string $email = ''): bool
    {
        return !empty($this->estado($negocioId, $email)['activo']);
    }

    /** ¿Está en modo de prueba (no se cobra de verdad)? */
    public function pruebas(int $negocioId): bool
    {
        return !empty($this->estado($negocioId)['pruebas']);
    }

    /** Pagos con tarjeta que siguen abiertos (esperando el pago, suscripción activa…). */
    public function vivos(int $negocioId): array
    {
        $cobros = $this->estado($negocioId)['cobros'] ?? [];
        return array_values(array_filter(is_array($cobros) ? $cobros : [],
            fn($c) => is_array($c) && in_array($c['estado'] ?? '', [...self::VIVAS, 'vencida'], true)));
    }

    /** La suscripción con tarjeta viva del negocio (si tiene): mientras exista no se ofrece crear otra. */
    public function suscripcion(int $negocioId): ?array
    {
        foreach ($this->vivos($negocioId) as $c) {
            if (($c['tipo'] ?? '') === 'suscripcion' && in_array($c['estado'], self::VIVAS, true)) return $c;
        }
        return null;
    }

    /**
     * Planes que este negocio puede pagar solo: [codigo => nombre]. Con precio acordado con Tukán solo paga su plan
     * (el acuerdo es por ese plan; para cambiar, se conversa con Tukán).
     */
    public function planes(int $negocioId): array
    {
        $st = $this->db->prepare('SELECT plan_codigo, precio_acordado FROM negocios WHERE id = ?');
        $st->execute([$negocioId]);
        $n = $st->fetch();
        if (!$n) return [];
        $r = [];
        foreach ((new Planes($this->db))->lista() as $pl) {
            if ($n['precio_acordado'] !== null && $pl['codigo'] !== $n['plan_codigo']) continue;
            $r[(string) $pl['codigo']] = (string) $pl['nombre'];
        }
        return $r;
    }

    /**
     * Lo que pagaría este negocio por ese plan en cada período (lo calcula Planes::cotizarNegocio, sin IVA):
     * [periodo => cotización]. Vacío si ese plan no se puede pagar aquí o el valor no llega al mínimo de la tarjeta.
     */
    public function opciones(int $negocioId, string $plan): array
    {
        if (!isset($this->planes($negocioId)[$plan])) return [];
        $planes = new Planes($this->db);
        $r = [];
        foreach (array_keys($planes->periodos()) as $per) {
            if (!isset(self::PERIODOS_TEXTO[$per])) continue;
            $c = $planes->cotizarNegocio($negocioId, $plan, $per);
            if ($c['monto'] >= 0.5) $r[$per] = $c;
        }
        return $r;
    }

    /**
     * Pide al Panel el cobro del plan. $u = usuario() (quien paga). $auto = suscripción que se cobra sola cada período.
     * Devuelve el cobro (con url_pago: ahí se manda al cliente) o lanza el error para mostrarlo.
     */
    public function crear(array $u, string $plan, string $periodo, bool $auto, string $volver): array
    {
        $nid = (int) $u['negocio_id'];
        if (!$this->activo($nid, (string) ($u['email'] ?? ''))) throw new RuntimeException('El pago con tarjeta no está disponible por ahora. Escríbenos por WhatsApp y te ayudamos a pagar.');
        if ($auto && $this->suscripcion($nid)) throw new RuntimeException('Ya tienes una suscripción con tarjeta. Para cambiarla, primero cancela la que tienes.');
        $c = $this->opciones($nid, $plan)[$periodo] ?? null;
        if (!$c) throw new RuntimeException('Elige el plan y cada cuánto quieres pagar.');
        if (($mj = $this->mejora($nid)) && $mj['plan'] === $plan) throw new RuntimeException('Para pasarte al plan ' . $mj['plan_nombre'] . ' pagas solo la diferencia de los días que te faltan: usa «Pasar al Completo» en esta página.');
        $nombrePlan = $this->planes($nid)[$plan];
        $st = $this->db->prepare('SELECT nombre, razon_social, ruc, telefono FROM negocios WHERE id = ?');
        $st->execute([$nid]);
        $n = $st->fetch();
        $ruc = preg_replace('/\D/', '', (string) ($n['ruc'] ?? ''));
        $desc = 'TuPlanner plan ' . $nombrePlan . ' · ' . ($auto ? 'suscripción ' . self::PERIODOS_TEXTO[$periodo] : mb_strtolower(Planes::PERIODOS_NOMBRE[$periodo]) . ' (' . $c['meses_recibidos'] . ($c['meses_recibidos'] === 1 ? ' mes)' : ' meses)'))
            . ' · ' . $n['nombre'];
        [$http, $j] = ($this->llamar)([
            'accion' => 'crear', 'tipo' => $auto ? 'suscripcion' : 'cobro', 'cliente_id' => (string) $nid, 'nombre' => (string) $n['nombre'],
            'plan' => $plan, 'plan_nombre' => $nombrePlan, 'periodo' => $periodo, 'meses' => (int) $c['meses_recibidos'],
            'subtotal' => round((float) $c['monto'], 2), 'descripcion' => mb_substr($desc, 0, 90),
            'comprador' => ['nombre' => (string) ($u['nombre'] ?? ''), 'correo' => (string) ($u['email'] ?? ''), 'telefono' => (string) (($u['telefono'] ?? '') ?: ($n['telefono'] ?? ''))],
            'fiscal' => in_array(strlen($ruc), [10, 13], true) ? ['identificacion' => $ruc, 'razon_social' => (string) (($n['razon_social'] ?? '') ?: $n['nombre']), 'correo' => (string) ($u['email'] ?? '')] : [],
            'volver' => $volver,
        ], (string) ($u['email'] ?? ''));
        unset($this->estados[$nid]);
        if ($http === 0 || !is_array($j)) throw new RuntimeException('No se pudo conectar con el sistema de pagos. Intenta en unos minutos o escríbenos por WhatsApp.');
        if (empty($j['ok'])) throw new RuntimeException((string) ($j['error'] ?? 'No se pudo crear el pago.'));
        $cobro = is_array($j['cobro'] ?? null) ? $j['cobro'] : [];
        if (!is_string($cobro['url_pago'] ?? null) || !preg_match('#^https?://#', $cobro['url_pago'])) {
            throw new RuntimeException('El sistema de pagos no devolvió la página de pago. Intenta otra vez.');
        }
        return $cobro;
    }

    /** Cancela un pago con tarjeta que todavía no se pagó (para poder elegir otro plan u otro período). */
    public function cancelar(int $negocioId, int $cobroId, string $email = ''): void
    {
        $mio = null;
        foreach ($this->vivos($negocioId) as $c) if ((int) ($c['id'] ?? 0) === $cobroId) $mio = $c;
        if (!$mio || ($mio['tipo'] ?? '') !== 'suscripcion' || !in_array($mio['estado'], ['pendiente', 'rechazado'], true)) throw new RuntimeException('Ese pago ya no se puede cancelar aquí. Usa «Cancelar mi plan», al final de esta página.');
        [$http, $j] = ($this->llamar)(['accion' => 'cancelar', 'cliente_id' => (string) $negocioId, 'id' => $cobroId], $email);
        unset($this->estados[$negocioId]);
        if ($http === 0 || !is_array($j)) throw new RuntimeException('No se pudo conectar con el sistema de pagos. Intenta en unos minutos.');
        if (empty($j['ok'])) throw new RuntimeException((string) ($j['error'] ?? 'No se pudo cancelar.'));
    }
    /**
     * La suscripción con tarjeta YA PAGADA (activa o en gracia) del negocio, o null. Si el Panel no contesta o tiene apagado el
     * cobro con tarjeta, mira el último pago con Plux registrado aquí que cubre hoy (para no dejar quitar módulos por error).
     */
    public function suscripcionPagada(int $negocioId, string $email = ''): ?array
    {
        $e = $this->estado($negocioId, $email);
        if ($e !== null && !empty($e['activo'])) {
            foreach ($this->vivos($negocioId) as $c) {
                if (($c['tipo'] ?? '') === 'suscripcion' && in_array($c['estado'], ['activa', 'en_gracia'], true)) return $c;
            }
            return null;
        }
        $st = $this->db->prepare("SELECT plan_codigo, periodo, monto, fin FROM suscripciones
                                   WHERE negocio_id = ? AND metodo_pago = 'plux' AND NOT anulada AND fin >= CURRENT_DATE ORDER BY fin DESC LIMIT 1");
        $st->execute([$negocioId]);
        $s = $st->fetch();
        return $s ? ['tipo' => 'suscripcion', 'estado' => 'activa', 'plan' => $s['plan_codigo'], 'periodo' => $s['periodo'],
                     'subtotal' => (float) $s['monto'], 'pagado_hasta' => $s['fin'], 'local' => true] : null;
    }

    /**
     * ¿Se puede hacer este cambio? Con suscripción con tarjeta pagada, durante el período pagado no se quitan módulos contratados
     * ni baja el valor (tampoco bajar de plan). $antes / $despues = Modulos::estado() antes y después del cambio. Lanza el motivo.
     */
    public static function revisarBaja(?array $sus, array $antes, array $despues, ?string $hastaNegocio = null, bool $aCompleto = false): void
    {
        if (!$sus) return;
        // (10 nov 2026, tercera parte) Pasarse al Completo SÍ se permite aunque el valor baje: no se quita nada (trae todo).
        if ($aCompleto && !array_diff($antes['pagados'], $despues['pagados'])) return;
        $hasta = substr((string) (($sus['pagado_hasta'] ?? '') ?: ($hastaNegocio ?? '')), 0, 10);
        $fecha = preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta) ? ' (hasta el ' . date('d/m/Y', strtotime($hasta)) . ')' : '';
        $quitados = array_diff($antes['pagados'], $despues['pagados']);
        if ($quitados) {
            throw new RuntimeException('Los módulos ya contratados no se pueden quitar mientras dure el período pagado' . $fecha . ': '
                . implode(', ', array_map([Modulos::class, 'nombre'], $quitados)) . '. Encender o subir de plan sí se puede.');
        }
        if ($despues['mensual'] < $antes['mensual'] - 0.004) {
            throw new RuntimeException('Con este cambio el valor bajaría de ' . self::dinero($antes['mensual']) . ' a ' . self::dinero($despues['mensual'])
                . ' al mes: no puede bajar mientras dure el período pagado' . $fecha . ' (tampoco se puede bajar de plan).');
        }
    }

    /**
     * Después de un cambio de módulos o de plan hecho en TuPlanner: si el negocio tiene suscripción con tarjeta pagada y el valor
     * cambió, se lo dice al Panel («cambiar») con el valor nuevo del mismo período (sin IVA). Devuelve el aviso para mostrar, o
     * null si no hay nada que avisar (sin suscripción o el valor no cambió). $sus = suscripcionPagada() de ANTES del cambio.
     */
    public function ajustar(int $negocioId, ?array $sus, string $email = '', bool $paraDueno = false): ?string
    {
        if (!$sus || !isset(self::PERIODOS_TEXTO[$sus['periodo'] ?? ''])) return null;
        $plan = (string) $this->db->query('SELECT plan_codigo FROM negocios WHERE id = ' . $negocioId)->fetchColumn();
        $nuevo = round((float) (new Planes($this->db))->cotizarNegocio($negocioId, $plan, (string) $sus['periodo'])['monto'], 2);
        if (abs($nuevo - (float) ($sus['subtotal'] ?? 0)) < 0.005) return null;   // el cambio no mueve el valor
        $datos = ['accion' => 'cambiar', 'cliente_id' => (string) $negocioId, 'subtotal' => $nuevo];
        $completo = (bool) ((new Modulos($this->db))->planes()[$plan]['todos'] ?? false);
        if ($completo) $datos['pasa_a_completo'] = true;   // el Panel acepta que baje: rige desde el próximo cobro
        if (!empty($sus['id'])) $datos['id'] = (int) $sus['id'];
        $this->ultimoOk = false;
        [$http, $j] = ($this->llamar)($datos, $email);
        unset($this->estados[$negocioId]);
        if ($http === 0 || !is_array($j)) return 'El Panel Tukán no respondió: la suscripción con tarjeta se ajustará a ' . self::dinero($nuevo) . ' + IVA cuando el Panel responda (avísale a Tukán).';
        if (empty($j['ok'])) return 'Suscripción con tarjeta: ' . (string) ($j['error'] ?? 'el Panel no aceptó el cambio.');
        if (empty($j['cambio'])) return null;
        $this->ultimoOk = true;
        if (!empty($j['baja_al_renovar'])) {   // pasó al Completo y el valor baja: rige desde el próximo cobro
            $hasta = substr((string) ($sus['pagado_hasta'] ?? ''), 0, 10);
            return 'Pasaste al plan Completo. Desde el próximo cobro' . (preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta) ? ' (' . date('d/m/Y', strtotime($hasta)) . ')' : '')
                . ' la suscripción con tarjeta queda en ' . self::dinero($nuevo) . ' + IVA ' . self::PERIODOS_TEXTO[$sus['periodo']] . '. Lo ya pagado no se devuelve.';
        }
        $m = 'La suscripción con tarjeta queda en ' . self::dinero($nuevo) . ' + IVA ' . self::PERIODOS_TEXTO[$sus['periodo']] . ' desde el próximo cobro.';
        $prop = is_array($j['proporcional'] ?? null) ? $j['proporcional'] : null;
        if ($prop && $paraDueno) {   // el dueño lo paga desde su Mi plan («Pagar ahora» en «Pagar con tarjeta»)
            $m .= ' Por los días que faltan se cobra aparte ' . self::dinero((float) ($prop['total'] ?? 0)) . ' con IVA: toca «Pagar ahora» en «Pagar con tarjeta».';
        } elseif ($prop) {
            $m .= ' Por los días que faltan del período pagado se cobra aparte ' . self::dinero((float) ($prop['subtotal'] ?? 0)) . ' + IVA'
                . (!empty($prop['url_pago']) ? ': ' . $prop['url_pago'] : '') . ' (el enlace también sale en Mi plan del cliente).';
        }
        return $m;
    }

    /**
     * ¿Le conviene el Completo? Solo para el Básico (sin precio acordado): compara lo que paga (o pagaría, con $sumar módulos más)
     * con lo que le costaría el Completo. null = no hay nada que sugerir. tipo «mayor» (igual o más caro que el Completo) o
     * «cerca» (a $5 o menos). Devuelve ['tipo', 'actual', 'completo', 'diferencia', 'plan' (código del Completo)].
     */
    public function sugerenciaCompleto(int $negocioId, array $sumar = []): ?array
    {
        $mods = new Modulos($this->db);
        $st = $this->db->prepare('SELECT plan_codigo, precio_acordado FROM negocios WHERE id = ?');
        $st->execute([$negocioId]);
        $n = $st->fetch();
        if (!$n || $n['precio_acordado'] !== null) return null;
        $planes = $mods->planes();
        if (!empty($planes[$n['plan_codigo']]['todos'])) return null;   // ya tiene el Completo
        $completo = null;
        foreach ($planes as $cod => $pl) if (!empty($pl['todos'])) $completo = (string) $cod;
        if ($completo === null) return null;
        $actual = $mods->estado($negocioId, null, $sumar)['mensual'];
        $precioCompleto = $mods->estado($negocioId, $completo)['mensual'];
        $dif = round($precioCompleto - $actual, 2);
        if ($dif > self::CERCA_DEL_COMPLETO + 0.004) return null;
        return ['tipo' => $dif <= 0.004 ? 'mayor' : 'cerca', 'actual' => $actual, 'completo' => $precioCompleto, 'diferencia' => max(0.0, $dif), 'plan' => $completo];
    }

    // ------------------------------------------------------------------
    // Subir de plan SIN suscripción con tarjeta: se paga el prorrateo (10 nov 2026, 18:26, decisión de Dimitry)
    // ------------------------------------------------------------------

    /** IVA que se suma al prorrateo para mostrarlo (el cobro lo hace Tukán Pagos con el suyo). */
    public const IVA_PCT = 15;

    /**
     * Lo que le cuesta a un CLIENTE con el período pagado vigente pasar al plan Completo hoy: solo la diferencia por los días
     * que le faltan. null = no aplica (demo, período vencido, ya tiene el Completo o tiene precio acordado): esos pagan el
     * plan nuevo completo, como siempre.
     * Prorrateo sin IVA = (Completo al mes − lo que paga hoy al mes) × días que faltan ÷ 30. Si su período es de 6 meses o
     * 1 año, se usa lo que vale ese período ÷ los días del período (paga 5 de 6 o 10 de 12).
     * Devuelve ['plan', 'plan_nombre', 'dias', 'hasta', 'actual', 'nuevo', 'subtotal', 'total'].
     */
    public function mejora(int $negocioId, ?\DateTimeImmutable $hoy = null): ?array
    {
        $hoy ??= new \DateTimeImmutable('today');
        $st = $this->db->prepare('SELECT plan_codigo, estado, activo_hasta, precio_acordado FROM negocios WHERE id = ?');
        $st->execute([$negocioId]);
        $n = $st->fetch();
        if (!$n || $n['estado'] !== 'activo' || $n['precio_acordado'] !== null || empty($n['activo_hasta']) || $n['activo_hasta'] < $hoy->format('Y-m-d')) return null;
        $mods = new Modulos($this->db);
        $planes = $mods->planes();
        if (!empty($planes[$n['plan_codigo']]['todos'])) return null;
        $completo = null;
        foreach ($planes as $cod => $pl) if (!empty($pl['todos'])) $completo = (string) $cod;
        if ($completo === null) return null;
        $actual = $mods->estado($negocioId)['mensual'];
        $nuevo = $mods->estado($negocioId, $completo)['mensual'];
        $dias = (int) $hoy->diff(new \DateTimeImmutable((string) $n['activo_hasta']))->days;
        // Su período de hoy (el último pago que cubre hoy): mensual = por 30 días; 6 meses o 1 año = lo pagado ÷ los días del período
        $st = $this->db->prepare("SELECT periodo, meses_pagados, inicio, fin FROM suscripciones WHERE negocio_id = ? AND NOT anulada AND fin >= ?::date ORDER BY inicio LIMIT 1");
        $st->execute([$negocioId, $hoy->format('Y-m-d')]);
        $p = $st->fetch();
        $porDia = ($nuevo - $actual) / 30;
        if ($p && $p['periodo'] !== 'mensual') {
            $diasPeriodo = (int) (new \DateTimeImmutable((string) $p['inicio']))->diff(new \DateTimeImmutable((string) $p['fin']))->days + 1;
            if ($diasPeriodo > 0) $porDia = ($nuevo - $actual) * (int) $p['meses_pagados'] / $diasPeriodo;
        }
        $subtotal = round(max(0, $porDia * $dias), 2);
        return ['plan' => $completo, 'plan_nombre' => (string) $planes[$completo]['nombre'], 'dias' => $dias, 'hasta' => (string) $n['activo_hasta'],
                'actual' => $actual, 'nuevo' => $nuevo, 'subtotal' => $subtotal, 'total' => round($subtotal * (1 + self::IVA_PCT / 100), 2)];
    }

    /** El texto que se manda por WhatsApp a Tukán para pagar el cambio por transferencia. */
    public static function textoWhatsAppMejora(array $m, string $negocio): string
    {
        return 'Hola, soy ' . $negocio . ' (TuPlanner). Quiero pasar al plan ' . $m['plan_nombre'] . ' pagando por transferencia. Me faltan ' . $m['dias']
            . ($m['dias'] === 1 ? ' día' : ' días') . ' (hasta el ' . date('d/m/Y', strtotime($m['hasta'])) . '). Valor del cambio: ' . self::dinero($m['total']) . ' con IVA.';
    }

    /**
     * «Pagar el cambio con tarjeta»: le pide al Panel el cobro del prorrateo (acción «mejora»). Devuelve ['url_pago' => …] para
     * mandar al cliente a pagar, o ['sin_cobro' => true] si la diferencia es menor a $1 (no se cobra: el plan ya quedó cambiado).
     * El plan NO cambia aquí cuando hay cobro: cambia cuando el Panel dice que quedó pagado (aplicarMejoras).
     */
    public function pedirMejora(array $u, string $volver, ?\DateTimeImmutable $hoy = null): array
    {
        $nid = (int) $u['negocio_id'];
        $m = $this->mejora($nid, $hoy) ?? throw new RuntimeException('Este cambio de plan se paga completo, no por días.');
        $st = $this->db->prepare('SELECT nombre, razon_social, ruc, telefono FROM negocios WHERE id = ?');
        $st->execute([$nid]);
        $n = $st->fetch();
        $ruc = preg_replace('/\D/', '', (string) ($n['ruc'] ?? ''));
        [$http, $j] = ($this->llamar)([
            'accion' => 'mejora', 'cliente_id' => (string) $nid, 'nombre' => (string) $n['nombre'], 'plan' => $m['plan'], 'plan_nombre' => $m['plan_nombre'],
            'subtotal' => $m['subtotal'], 'hasta' => $m['hasta'],
            'comprador' => ['nombre' => (string) ($u['nombre'] ?? ''), 'correo' => (string) ($u['email'] ?? ''), 'telefono' => (string) (($u['telefono'] ?? '') ?: ($n['telefono'] ?? ''))],
            'fiscal' => in_array(strlen($ruc), [10, 13], true) ? ['identificacion' => $ruc, 'razon_social' => (string) (($n['razon_social'] ?? '') ?: $n['nombre']), 'correo' => (string) ($u['email'] ?? '')] : [],
            'volver' => $volver,
        ], (string) ($u['email'] ?? ''));
        unset($this->estados[$nid]);
        if ($http === 0 || !is_array($j)) throw new RuntimeException('No se pudo conectar con el sistema de pagos. Tu plan sigue igual: intenta en unos minutos o envíalo por WhatsApp.');
        if (empty($j['ok']) && ($j['codigo'] ?? '') === 'minimo') {   // menos de $1: no se cobra y el plan cambia
            $this->ponerPlan($nid, $m['plan'], 0, 0.0, 'la diferencia era menor a $1 y no se cobró');
            return ['sin_cobro' => true];
        }
        if (empty($j['ok'])) throw new RuntimeException((string) ($j['error'] ?? 'No se pudo crear el pago.') . ' Tu plan sigue igual.');
        $cobro = is_array($j['cobro'] ?? null) ? $j['cobro'] : [];
        if (!is_string($cobro['url_pago'] ?? null) || !preg_match('#^https?://#', $cobro['url_pago'])) throw new RuntimeException('El sistema de pagos no devolvió la página de pago. Tu plan sigue igual: intenta otra vez.');
        // Queda anotado que este negocio espera un pago: la tarea de cada hora revisa si ya quedó pagado (aplicarPendientes)
        $this->db->prepare('INSERT INTO plan_mejoras_pedidas (negocio_id) VALUES (?) ON CONFLICT (negocio_id) DO UPDATE SET pedida = now()')->execute([$nid]);
        return ['url_pago' => $cobro['url_pago']];
    }

    /**
     * (10 nov 2026, 19:12) Si la diferencia es menor a $1 (también $0: en el Básico ya paga igual o más que el Completo), el
     * cliente pasa DIRECTO al Completo, sin cobro y sin preguntarle al Panel (sirve con el cobro con tarjeta apagado o el Panel
     * caído). El vencimiento no cambia. Lanza el motivo si no aplica.
     */
    public function pasarSinCobro(int $negocioId, ?\DateTimeImmutable $hoy = null): void
    {
        $m = $this->mejora($negocioId, $hoy);
        if (!$m || $m['subtotal'] >= 1) throw new RuntimeException('Este cambio de plan tiene un valor: hay que pagarlo.');
        $this->ponerPlan($negocioId, $m['plan'], 0, 0.0, 'la diferencia era menor a $1 y no se cobró');
    }

    /**
     * Tarea de cada hora (bin/tareas.php): para los negocios que pidieron pagar un cambio de plan con tarjeta, le pregunta al
     * Panel y pone el plan si el cobro ya está pagado (así no depende de que el dueño abra Mi plan). Los pedidos de más de
     * 7 días se olvidan. Devuelve cuántos planes puso. Si el Panel no contesta, no pasa nada: se revisa en la hora siguiente.
     */
    public function aplicarPendientes(): int
    {
        $this->db->exec("DELETE FROM plan_mejoras_pedidas WHERE pedida < now() - interval '7 days'");
        $n = 0;
        foreach ($this->db->query('SELECT negocio_id FROM plan_mejoras_pedidas ORDER BY pedida')->fetchAll(PDO::FETCH_COLUMN) as $nid) {
            try {
                if ($this->aplicarMejoras((int) $nid) !== null) $n++;
            } catch (\Throwable) {
                // un negocio con problemas no detiene a los demás
            }
        }
        return $n;
    }

    /**
     * Mira en el Panel si hay un cobro de cambio de plan (origen «mejora») ya PAGADO y sin aplicar, y pone ese plan. Cada cobro
     * se aplica una sola vez (tabla plan_mejoras). El vencimiento no cambia. Devuelve el nombre del plan puesto, o null.
     */
    public function aplicarMejoras(int $negocioId, string $email = ''): ?string
    {
        $cobros = $this->estado($negocioId, $email)['cobros'] ?? [];
        $planes = (new Modulos($this->db))->planes();
        $puesto = null;
        foreach (is_array($cobros) ? $cobros : [] as $c) {
            if (!is_array($c) || ($c['origen'] ?? '') !== 'mejora' || ($c['estado'] ?? '') !== 'pagado' || (int) ($c['id'] ?? 0) < 1) continue;
            $plan = (string) ($c['plan'] ?? '');
            if (empty($planes[$plan]['todos'])) continue;   // solo se sube al Completo
            if ($this->ponerPlan($negocioId, $plan, (int) $c['id'], (float) ($c['subtotal'] ?? 0), 'pagó el prorrateo con tarjeta, cobro N.º ' . (int) $c['id'])) $puesto = (string) $planes[$plan]['nombre'];
        }
        if ($puesto !== null) $this->db->prepare('DELETE FROM plan_mejoras_pedidas WHERE negocio_id = ?')->execute([$negocioId]);
        return $puesto;
    }

    /** Pone el plan y lo anota (una sola vez por cobro). El vencimiento y los pagos no se tocan. */
    private function ponerPlan(int $negocioId, string $plan, int $cobroId, float $monto, string $porque): bool
    {
        if ($cobroId > 0) {
            $st = $this->db->prepare('INSERT INTO plan_mejoras (negocio_id, cobro_id, plan_codigo, monto) VALUES (?,?,?,?) ON CONFLICT DO NOTHING');
            $st->execute([$negocioId, $cobroId, $plan, round($monto, 2)]);
            if ($st->rowCount() !== 1) return false;   // ese cobro ya se aplicó
        }
        $this->db->prepare('UPDATE negocios SET plan_codigo = ? WHERE id = ?')->execute([$plan, $negocioId]);
        $this->db->prepare('INSERT INTO registro_admin (admin_id, negocio_id, accion) VALUES (NULL, ?, ?)')
                 ->execute([$negocioId, mb_substr('El dueño pasó al plan ' . $plan . ' desde Mi plan: ' . $porque, 0, 300)]);
        return true;
    }

    /** «Pasarme al Completo» sin cobro con tarjeta disponible: el plan no cambia (corrección de Dimitry, 10 nov 2026). */
    public const SIN_TARJETA = 'Para pasarte al Completo necesitas pagar con tarjeta. En este momento el pago con tarjeta no está disponible: escríbenos y te ayudamos.';

    /** Desde cuántos dólares de diferencia se muestra «Por $Z más tienes el Completo». */
    public const CERCA_DEL_COMPLETO = 5.0;

    /** ¿El último ajustar() salió bien (aviso verde) o no (aviso rojo)? */
    public bool $ultimoOk = false;

    private static function dinero(float $v): string
    {
        return '$' . number_format($v, 2, ',', '.');
    }
}
