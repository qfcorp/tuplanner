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
}
