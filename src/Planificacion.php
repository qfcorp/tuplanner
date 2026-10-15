<?php
declare(strict_types=1);

namespace TuPlanner;

use DateTimeImmutable;
use PDO;
use RuntimeException;

/**
 * Lo propio del organizador de bodas y eventos (migración 100):
 *  - Lista de tareas por meses (para el organizador o para el cliente).
 *  - Presupuesto del evento por partidas (una por proveedor o cosa que hay que contratar):
 *      paga = 'cliente' → el cliente le paga DIRECTO al proveedor (aquí se anotan esos pagos para llevar la cuenta)
 *      paga = 'planner' → va en el contrato del organizador (reventa): se cobra con la cotización y se paga con Compras
 *  - Comisión que el proveedor le paga al organizador (porcentaje o valor fijo) y lo ya cobrado.
 *  - Honorarios del organizador: tarifa fija (se escribe en la cotización) o porcentaje del presupuesto (se calcula aquí).
 *
 * Cuentas (una sola regla, la usan el sistema y el portal):
 *  - Valor de una partida = contratado si ya tiene precio final; si no, lo estimado. Las descartadas no cuentan.
 *  - Total del evento = lo que cobra el organizador (eventos.total, de la cotización aceptada)
 *                       + lo que el cliente paga directo a proveedores (partidas paga='cliente').
 *    Las partidas paga='planner' ya están dentro de la cotización: se muestran, pero no se suman otra vez.
 *  - Comisión de una partida = comision_monto si lo escribieron; si no, contratado × comision_pct (solo si ya está contratada).
 */
final class Planificacion
{
    public const ESTADOS = ['idea' => 'Idea', 'cotizando' => 'Pidiendo precios', 'contratado' => 'Contratado', 'cancelado' => 'Descartado'];
    public const PAGA = ['cliente' => 'El cliente le paga directo', 'planner' => 'Va en nuestro contrato'];
    public const ASIGNADO = ['planner' => 'Organizador', 'cliente' => 'Cliente'];
    public const MODOS_HONORARIO = ['fija' => 'Tarifa fija', 'porcentaje' => 'Porcentaje del presupuesto'];
    public const METODOS = ['transferencia' => 'Transferencia', 'efectivo' => 'Efectivo', 'deuna' => 'DeUna', 'tarjeta' => 'Tarjeta',
                            'cheque' => 'Cheque', 'otro' => 'Otro'];
    public const ICONOS = [
        'honorarios' => '📋', 'espacio' => '🏛️', 'menu' => '🍽️', 'bebidas' => '🥂', 'pasteleria' => '🎂', 'decoracion' => '🎀',
        'flores' => '💐', 'musica' => '🎶', 'foto_video' => '📸', 'vestuario' => '👗', 'belleza' => '💄', 'invitaciones' => '💌',
        'transporte' => '🚗', 'mobiliario' => '🪑', 'personal' => '🤵', 'transmision' => '🔴', 'otros' => '✨',
    ];
    public const MAX_PARTIDAS = 120;
    public const MAX_TAREAS = 250;

    /**
     * Reparto típico del presupuesto en Ecuador (porcentajes que suman 100). Sirve para arrancar:
     * el organizador los cambia después. tipo => [categoria, concepto, %]
     */
    public const REPARTO = [
        'boda' => [
            ['espacio', 'Lugar de la recepción', 15], ['espacio', 'Iglesia o ceremonia civil', 2], ['menu', 'Banquete', 28],
            ['bebidas', 'Bar y bebidas', 7], ['pasteleria', 'Pastel y mesa de postres', 3], ['decoracion', 'Decoración y ambientación', 8],
            ['flores', 'Flores y ramo', 4], ['musica', 'DJ, orquesta o banda', 6], ['foto_video', 'Fotografía y video', 10],
            ['vestuario', 'Vestido de novia y traje', 8], ['belleza', 'Maquillaje y peinado', 2], ['invitaciones', 'Invitaciones y recuerdos', 2],
            ['transporte', 'Auto de los novios', 1], ['otros', 'Imprevistos', 4],
        ],
        'quince' => [
            ['espacio', 'Lugar de la fiesta', 15], ['espacio', 'Misa de acción de gracias', 2], ['menu', 'Banquete o bocaditos', 25],
            ['bebidas', 'Bebidas', 6], ['pasteleria', 'Pastel y mesa de dulces', 5], ['decoracion', 'Decoración y temática', 12],
            ['musica', 'DJ, hora loca y coreografía', 10], ['foto_video', 'Fotografía y video', 9], ['vestuario', 'Vestido y cambio de zapatos', 8],
            ['belleza', 'Maquillaje y peinado', 2], ['invitaciones', 'Invitaciones y recuerdos', 2], ['otros', 'Imprevistos', 4],
        ],
        'otro' => [
            ['espacio', 'Lugar', 20], ['menu', 'Comida', 30], ['bebidas', 'Bebidas', 8], ['decoracion', 'Decoración', 12],
            ['musica', 'Música y sonido', 10], ['foto_video', 'Fotografía y video', 10], ['otros', 'Imprevistos', 10],
        ],
    ];

    /**
     * Lista de tareas típica por tipo de evento: [título, días antes del evento, asignado].
     * Días negativos = después del evento. Se copia al negocio al crearlo (plantilla_tareas) y él la cambia.
     */
    public const LISTA_TIPICA = [
        'boda' => [
            ['Definir presupuesto total y lista de invitados preliminar', 365, 'cliente'],
            ['Elegir y separar el lugar de la recepción', 330, 'planner'],
            ['Reservar iglesia o ceremonia civil', 330, 'cliente'],
            ['Contratar fotografía y video', 300, 'planner'],
            ['Contratar música (DJ, orquesta o banda)', 270, 'planner'],
            ['Elegir el vestido de novia', 240, 'cliente'],
            ['Contratar el banquete y agendar la degustación', 240, 'planner'],
            ['Armar el tablero de ideas: temática y colores', 210, 'cliente'],
            ['Contratar decoración y flores', 180, 'planner'],
            ['Enviar "reserva la fecha" (save the date)', 180, 'cliente'],
            ['Elegir padrinos y damas', 150, 'cliente'],
            ['Diseñar y pedir las invitaciones', 120, 'planner'],
            ['Prueba de maquillaje y peinado', 90, 'cliente'],
            ['Pedir el pastel y la mesa de postres', 90, 'planner'],
            ['Contratar transporte de los novios', 90, 'planner'],
            ['Entregar las invitaciones', 60, 'cliente'],
            ['Degustación del menú', 60, 'cliente'],
            ['Primera prueba del vestido y del traje', 60, 'cliente'],
            ['Trámites del registro civil y de la iglesia', 45, 'cliente'],
            ['Confirmar la lista final de invitados', 30, 'cliente'],
            ['Armar el plano de mesas', 21, 'planner'],
            ['Cronograma minuto a minuto del día', 21, 'planner'],
            ['Confirmar horarios con todos los proveedores', 14, 'planner'],
            ['Pagar saldos pendientes a proveedores', 10, 'cliente'],
            ['Última prueba del vestido', 10, 'cliente'],
            ['Ensayo de la ceremonia', 2, 'planner'],
            ['Entregar recuerdos, detalles y documentos al organizador', 2, 'cliente'],
            ['Devolver lo alquilado y cerrar cuentas con proveedores', -3, 'planner'],
        ],
        'quince' => [
            ['Definir presupuesto, temática e invitados', 270, 'cliente'],
            ['Separar el lugar de la fiesta', 240, 'planner'],
            ['Reservar la misa de acción de gracias', 240, 'cliente'],
            ['Contratar fotografía y video', 210, 'planner'],
            ['Contratar DJ y hora loca', 180, 'planner'],
            ['Elegir el vestido', 150, 'cliente'],
            ['Elegir la corte de honor y empezar ensayos de coreografía', 120, 'cliente'],
            ['Contratar banquete y degustación', 120, 'planner'],
            ['Contratar decoración y mesa de dulces', 90, 'planner'],
            ['Pedir invitaciones', 75, 'planner'],
            ['Entregar las invitaciones', 45, 'cliente'],
            ['Prueba de maquillaje y peinado', 30, 'cliente'],
            ['Confirmar invitados', 21, 'cliente'],
            ['Cronograma del día (vals, cambio de zapatos, brindis)', 14, 'planner'],
            ['Confirmar horarios con todos los proveedores', 10, 'planner'],
            ['Ensayo general del vals', 3, 'cliente'],
            ['Cerrar cuentas con proveedores', -3, 'planner'],
        ],
        'otro' => [
            ['Definir presupuesto, fecha e invitados', 120, 'cliente'],
            ['Separar el lugar', 90, 'planner'],
            ['Contratar comida y bebidas', 60, 'planner'],
            ['Contratar música, foto y decoración', 45, 'planner'],
            ['Enviar invitaciones', 30, 'cliente'],
            ['Confirmar invitados', 10, 'cliente'],
            ['Cronograma del día y confirmación de proveedores', 7, 'planner'],
            ['Cerrar cuentas con proveedores', -3, 'planner'],
        ],
    ];

    public function __construct(private PDO $db) {}

    private function q(string $sql, array $p = []): \PDOStatement
    {
        $st = $this->db->prepare($sql);
        $st->execute($p);
        return $st;
    }

    private function evento(int $negocioId, int $eventoId): array
    {
        $e = $this->q('SELECT e.*, n.comision_pct AS negocio_comision_pct FROM eventos e JOIN negocios n ON n.id = e.negocio_id
                        WHERE e.id = ? AND e.negocio_id = ?', [$eventoId, $negocioId])->fetch();
        if (!$e) throw new RuntimeException('No se encontró el evento.');
        return $e;
    }

    private static function anotar(PDO $db, int $negocioId, int $eventoId, string $accion, ?int $usuarioId, string $quien): void
    {
        $db->prepare('INSERT INTO registro_cambios (negocio_id, evento_id, usuario_id, quien, accion) VALUES (?,?,?,?,?)')
           ->execute([$negocioId, $eventoId, $usuarioId, mb_substr($quien, 0, 120), mb_substr($accion, 0, 400)]);
    }

    /** Lista típica que corresponde a un tipo de evento (boda, quince o la general). */
    public static function claveTipo(string $tipo): string
    {
        return match ($tipo) { 'boda', 'pedida' => 'boda', 'quince' => 'quince', default => 'otro' };
    }

    // ------------------------------------------------------------------
    // Tareas por meses
    // ------------------------------------------------------------------

    public function tareas(int $eventoId, ?string $asignado = null): array
    {
        $sql = 'SELECT t.*, u.nombre AS responsable FROM evento_tareas t LEFT JOIN usuarios u ON u.id = t.responsable_id WHERE t.evento_id = ?';
        $p = [$eventoId];
        if ($asignado !== null) { $sql .= ' AND t.asignado = ?'; $p[] = $asignado; }
        return $this->q($sql . ' ORDER BY t.vence NULLS LAST, t.orden, t.id', $p)->fetchAll();
    }

    /** Avance: total, hechas, % y vencidas (sin hacer con fecha pasada). Por quien: null = todas. */
    public function avance(int $eventoId, ?string $asignado = null, ?DateTimeImmutable $hoy = null): array
    {
        $hoyTxt = ($hoy ?? new DateTimeImmutable('today'))->format('Y-m-d');
        $r = $this->q('SELECT count(*) AS total, count(*) FILTER (WHERE hecha) AS hechas,
                              count(*) FILTER (WHERE NOT hecha AND vence < ?::date) AS vencidas
                         FROM evento_tareas WHERE evento_id = ?' . ($asignado ? ' AND asignado = ?' : ''),
                      array_merge([$hoyTxt, $eventoId], $asignado ? [$asignado] : []))->fetch();
        $total = (int) $r['total'];
        return ['total' => $total, 'hechas' => (int) $r['hechas'], 'vencidas' => (int) $r['vencidas'],
                'pct' => $total ? (int) floor((int) $r['hechas'] * 100 / $total) : 0];
    }

    /**
     * Agrupa las tareas por cuánto falta para el evento: "12 meses antes o más", "6 meses antes", …, "El último mes",
     * "Después del evento" y "Sin fecha". Devuelve [titulo => tareas], en orden.
     */
    public static function porMeses(array $tareas, string $fechaEvento): array
    {
        $ev = new DateTimeImmutable($fechaEvento);
        $grupos = [];
        $orden = [];
        foreach ($tareas as $t) {
            if (empty($t['vence'])) { $k = 'Sin fecha'; $o = -9999; }
            else {
                $v = new DateTimeImmutable($t['vence']);
                $dias = (int) $v->diff($ev)->format('%r%a');
                if ($dias < 0) { $k = 'Después del evento'; $o = -1000; }
                elseif ($dias <= 30) { $k = 'El último mes'; $o = 0; }
                else {
                    $meses = (int) floor($dias / 30.4);
                    if ($meses >= 12) { $k = '12 meses antes o más'; $o = 12; }
                    else { $k = $meses === 1 ? '1 mes antes' : $meses . ' meses antes'; $o = $meses; }
                }
            }
            $grupos[$k][] = $t;
            $orden[$k] = $o;
        }
        uksort($grupos, fn($a, $b) => $orden[$b] <=> $orden[$a]);
        return $grupos;
    }

    public function agregarTarea(int $negocioId, int $eventoId, string $titulo, ?string $vence, string $asignado = 'planner',
                                 ?int $usuarioId = null, string $quien = 'Sistema'): int
    {
        $ev = $this->evento($negocioId, $eventoId);
        $titulo = trim(preg_replace('/\s+/', ' ', $titulo) ?? '');
        if (mb_strlen($titulo) < 2) throw new RuntimeException('Escribe la tarea.');
        if (!isset(self::ASIGNADO[$asignado])) throw new RuntimeException('Elige para quién es la tarea.');
        if ($vence !== null && $vence !== '') {
            $f = DateTimeImmutable::createFromFormat('!Y-m-d', $vence);
            if (!$f || $f->format('Y-m-d') !== $vence) throw new RuntimeException('Revisa la fecha de la tarea.');
        } else {
            $vence = null;
        }
        if ((int) $this->q('SELECT count(*) FROM evento_tareas WHERE evento_id = ?', [$eventoId])->fetchColumn() >= self::MAX_TAREAS) {
            throw new RuntimeException('Este evento ya tiene ' . self::MAX_TAREAS . ' tareas. Borra las que ya no sirven.');
        }
        $id = (int) $this->q('INSERT INTO evento_tareas (evento_id, titulo, vence, asignado, orden) VALUES (?,?,?,?,999) RETURNING id',
                             [$eventoId, mb_substr($titulo, 0, 150), $vence, $asignado])->fetchColumn();
        self::anotar($this->db, $negocioId, (int) $ev['id'], 'Tarea nueva (' . self::ASIGNADO[$asignado] . '): ' . $titulo, $usuarioId, $quien);
        return $id;
    }

    /**
     * Marca o desmarca una tarea. $quien = 'planner' (sistema) o 'cliente' (portal: solo sus tareas).
     */
    public function marcarTarea(int $negocioId, int $eventoId, int $tareaId, bool $hecha, string $quien = 'planner', ?int $usuarioId = null,
                                string $nombre = 'Sistema'): void
    {
        $t = $this->q('SELECT t.* FROM evento_tareas t JOIN eventos e ON e.id = t.evento_id
                        WHERE t.id = ? AND t.evento_id = ? AND e.negocio_id = ?', [$tareaId, $eventoId, $negocioId])->fetch();
        if (!$t) throw new RuntimeException('No se encontró la tarea.');
        if ($quien === 'cliente' && $t['asignado'] !== 'cliente') throw new RuntimeException('Esa tarea la marca tu organizador.');
        if ((bool) $t['hecha'] === $hecha) return;
        $this->q('UPDATE evento_tareas SET hecha = ?, hecha_en = CASE WHEN ? THEN now() END, hecha_por = CASE WHEN ? THEN ? END WHERE id = ?',
                 [$hecha ? 'true' : 'false', $hecha ? 'true' : 'false', $hecha ? 'true' : 'false', $quien, $tareaId]);
        self::anotar($this->db, $negocioId, $eventoId, ($hecha ? 'Tarea hecha: ' : 'Tarea pendiente otra vez: ') . $t['titulo'], $usuarioId,
                     $quien === 'cliente' ? 'Cliente' : $nombre);
    }

    public function editarTarea(int $negocioId, int $eventoId, int $tareaId, string $titulo, ?string $vence, string $asignado, ?string $notas = null): void
    {
        $titulo = trim(preg_replace('/\s+/', ' ', $titulo) ?? '');
        if (mb_strlen($titulo) < 2) throw new RuntimeException('Escribe la tarea.');
        if (!isset(self::ASIGNADO[$asignado])) throw new RuntimeException('Elige para quién es la tarea.');
        if ($vence !== null && $vence !== '') {
            $f = DateTimeImmutable::createFromFormat('!Y-m-d', $vence);
            if (!$f || $f->format('Y-m-d') !== $vence) throw new RuntimeException('Revisa la fecha de la tarea.');
        } else {
            $vence = null;
        }
        $st = $this->q('UPDATE evento_tareas t SET titulo = ?, vence = ?, asignado = ?, notas = ? FROM eventos e
                         WHERE e.id = t.evento_id AND t.id = ? AND t.evento_id = ? AND e.negocio_id = ?',
                       [mb_substr($titulo, 0, 150), $vence, $asignado, ($n = trim((string) $notas)) === '' ? null : mb_substr($n, 0, 300),
                        $tareaId, $eventoId, $negocioId]);
        if ($st->rowCount() !== 1) throw new RuntimeException('No se encontró la tarea.');
    }

    public function borrarTarea(int $negocioId, int $eventoId, int $tareaId): void
    {
        $st = $this->q('DELETE FROM evento_tareas t USING eventos e WHERE e.id = t.evento_id AND t.id = ? AND t.evento_id = ? AND e.negocio_id = ?',
                       [$tareaId, $eventoId, $negocioId]);
        if ($st->rowCount() !== 1) throw new RuntimeException('No se encontró la tarea.');
    }

    /**
     * Carga la lista típica del tipo de evento (sin repetir títulos que ya estén). Las fechas se cuentan
     * hacia atrás desde el evento; las que ya pasaron quedan para hoy. Devuelve cuántas agregó.
     */
    public function cargarListaTipica(int $negocioId, int $eventoId, ?DateTimeImmutable $hoy = null): int
    {
        $ev = $this->evento($negocioId, $eventoId);
        $hoy ??= new DateTimeImmutable('today');
        $fecha = new DateTimeImmutable($ev['fecha']);
        $ya = array_map(fn($t) => mb_strtolower($t), $this->q('SELECT titulo FROM evento_tareas WHERE evento_id = ?', [$eventoId])->fetchAll(PDO::FETCH_COLUMN));
        $n = 0;
        $ins = $this->db->prepare('INSERT INTO evento_tareas (evento_id, titulo, vence, asignado, orden) VALUES (?,?,?,?,?)');
        foreach (self::LISTA_TIPICA[self::claveTipo($ev['tipo'])] as $i => [$titulo, $dias, $asig]) {
            if (in_array(mb_strtolower($titulo), $ya, true)) continue;
            $vence = $fecha->modify(($dias >= 0 ? '-' : '+') . abs($dias) . ' days');
            if ($dias >= 0 && $vence < $hoy) $vence = min($hoy, $fecha);
            $ins->execute([$eventoId, $titulo, $vence->format('Y-m-d'), $asig, $i]);
            $n++;
        }
        return $n;
    }

    // ------------------------------------------------------------------
    // Presupuesto: partidas
    // ------------------------------------------------------------------

    /** Valor que cuenta de una partida (contratado o estimado). */
    public static function valor(array $p): float
    {
        if ($p['estado'] === 'cancelado') return 0.0;
        return round((float) ($p['contratado'] ?? $p['estimado']), 2);
    }

    /** Comisión total de una partida (solo si está contratada). */
    public static function comision(array $p): float
    {
        if ($p['estado'] !== 'contratado') return 0.0;
        if ($p['comision_monto'] !== null && $p['comision_monto'] !== '') return round((float) $p['comision_monto'], 2);
        return round((float) ($p['contratado'] ?? $p['estimado']) * (float) $p['comision_pct'] / 100, 2);
    }

    /** Partidas del evento con lo pagado, el saldo y la comisión. */
    public function partidas(int $negocioId, int $eventoId, bool $soloVisibles = false): array
    {
        $rows = $this->q('SELECT p.*, pr.telefono AS proveedor_telefono,
                                 COALESCE((SELECT sum(monto) FROM presupuesto_pagos WHERE partida_id = p.id), 0) AS pagado
                            FROM presupuesto_partidas p LEFT JOIN proveedores pr ON pr.id = p.proveedor_id
                           WHERE p.negocio_id = ? AND p.evento_id = ?' . ($soloVisibles ? ' AND p.visible_cliente' : '') . '
                        ORDER BY p.estado = \'cancelado\', p.orden, p.id', [$negocioId, $eventoId])->fetchAll();
        $pos = array_flip(array_keys(Cotizaciones::CATEGORIAS));
        foreach ($rows as &$r) {
            $r['valor'] = self::valor($r);
            $r['pagado'] = round((float) $r['pagado'], 2);
            $r['saldo'] = $r['paga'] === 'cliente' ? round(max(0, $r['valor'] - $r['pagado']), 2) : 0.0;
            $r['comision_total'] = self::comision($r);
            $r['comision_por_cobrar'] = round(max(0, $r['comision_total'] - (float) $r['comision_cobrada']), 2);
        }
        unset($r);
        usort($rows, fn($a, $b) => [$a['estado'] === 'cancelado', $pos[$a['categoria']] ?? 99, (int) $a['orden'], (int) $a['id']]
                                   <=> [$b['estado'] === 'cancelado', $pos[$b['categoria']] ?? 99, (int) $b['orden'], (int) $b['id']]);
        return $rows;
    }

    public function partida(int $negocioId, int $partidaId): ?array
    {
        $p = $this->q('SELECT p.*, COALESCE((SELECT sum(monto) FROM presupuesto_pagos WHERE partida_id = p.id), 0) AS pagado
                         FROM presupuesto_partidas p WHERE p.id = ? AND p.negocio_id = ?', [$partidaId, $negocioId])->fetch();
        if (!$p) return null;
        $p['valor'] = self::valor($p);
        $p['pagado'] = round((float) $p['pagado'], 2);
        $p['saldo'] = $p['paga'] === 'cliente' ? round(max(0, $p['valor'] - $p['pagado']), 2) : 0.0;
        $p['comision_total'] = self::comision($p);
        $p['comision_por_cobrar'] = round(max(0, $p['comision_total'] - (float) $p['comision_cobrada']), 2);
        return $p;
    }

    /**
     * Crea o cambia una partida. $d: categoria, concepto, proveedor_id o proveedor_nombre, estado, paga, estimado, contratado,
     * vence_pago, comision_pct, comision_monto, visible_cliente, notas.
     */
    public function guardarPartida(int $negocioId, int $eventoId, array $d, ?int $id = null, ?int $usuarioId = null, string $quien = 'Sistema'): int
    {
        $ev = $this->evento($negocioId, $eventoId);
        $cat = (string) ($d['categoria'] ?? 'otros');
        if (!isset(Cotizaciones::CATEGORIAS[$cat])) throw new RuntimeException('Elige la categoría.');
        $concepto = trim(preg_replace('/\s+/', ' ', (string) ($d['concepto'] ?? '')) ?? '');
        if (mb_strlen($concepto) < 2) throw new RuntimeException('Escribe qué es (ej. "Fotografía y video").');
        $estado = (string) ($d['estado'] ?? 'idea');
        if (!isset(self::ESTADOS[$estado])) throw new RuntimeException('Elige el estado.');
        $paga = (string) ($d['paga'] ?? 'cliente');
        if (!isset(self::PAGA[$paga])) throw new RuntimeException('Elige quién le paga al proveedor.');
        $num = function (mixed $v, string $que, bool $nulo = false): ?float {
            if ($v === null || trim((string) $v) === '') return $nulo ? null : 0.0;
            $n = Cotizaciones::num($v);
            if ($n < 0 || $n > 9999999) throw new RuntimeException("Revisa $que.");
            return round($n, 2);
        };
        $estimado = $num($d['estimado'] ?? '', 'lo estimado');
        $contratado = $num($d['contratado'] ?? '', 'el precio contratado', true);
        if ($estado === 'contratado' && $contratado === null) $contratado = $estimado;
        $pct = $num($d['comision_pct'] ?? '', 'el porcentaje de comisión');
        if ($pct > 100) throw new RuntimeException('La comisión no puede pasar del 100 %.');
        $monto = $num($d['comision_monto'] ?? '', 'el valor de la comisión', true);
        $vence = trim((string) ($d['vence_pago'] ?? ''));
        if ($vence !== '') {
            $f = DateTimeImmutable::createFromFormat('!Y-m-d', $vence);
            if (!$f || $f->format('Y-m-d') !== $vence) throw new RuntimeException('Revisa la fecha de pago al proveedor.');
        }
        $provId = !empty($d['proveedor_id']) ? (int) $d['proveedor_id'] : null;
        $provNombre = trim((string) ($d['proveedor_nombre'] ?? ''));
        if ($provId) {
            $pr = $this->q('SELECT nombre FROM proveedores WHERE id = ? AND negocio_id = ?', [$provId, $negocioId])->fetch();
            if (!$pr) throw new RuntimeException('No se encontró el proveedor.');
            $provNombre = $pr['nombre'];
        }
        $notas = trim((string) ($d['notas'] ?? ''));
        $visible = !array_key_exists('visible_cliente', $d) || !empty($d['visible_cliente']);
        $vals = [$cat, mb_substr($concepto, 0, 150), $provId, $provNombre === '' ? null : mb_substr($provNombre, 0, 150), $estado, $paga,
                 $estimado, $contratado, $vence === '' ? null : $vence, $pct, $monto, $visible ? 'true' : 'false',
                 $notas === '' ? null : mb_substr($notas, 0, 500)];
        if ($id) {
            $antes = $this->partida($negocioId, $id);
            if (!$antes || (int) $antes['evento_id'] !== $eventoId) throw new RuntimeException('No se encontró la partida.');
            if ($paga === 'planner' && $antes['paga'] === 'cliente' && $antes['pagado'] > 0) {
                throw new RuntimeException('Esta partida ya tiene pagos directos del cliente. Bórralos antes de pasarla a "Va en nuestro contrato".');
            }
            $this->q('UPDATE presupuesto_partidas SET categoria = ?, concepto = ?, proveedor_id = ?, proveedor_nombre = ?, estado = ?, paga = ?,
                             estimado = ?, contratado = ?, vence_pago = ?, comision_pct = ?, comision_monto = ?, visible_cliente = ?, notas = ?,
                             actualizado_en = now()
                       WHERE id = ? AND negocio_id = ?', [...$vals, $id, $negocioId]);
            $nuevo = $this->partida($negocioId, $id);
            if ((float) $nuevo['comision_cobrada'] > $nuevo['comision_total'] && $nuevo['comision_total'] >= 0) {
                $this->q('UPDATE presupuesto_partidas SET comision_cobrada = ? WHERE id = ?', [$nuevo['comision_total'], $id]);
            }
            $cambio = $antes['estado'] !== $estado ? ' (' . self::ESTADOS[$antes['estado']] . ' → ' . self::ESTADOS[$estado] . ')' : '';
            self::anotar($this->db, $negocioId, $eventoId, 'Presupuesto: cambió «' . $concepto . '»' . $cambio, $usuarioId, $quien);
            return $id;
        }
        $cuantas = (int) $this->q('SELECT count(*) FROM presupuesto_partidas WHERE evento_id = ?', [$eventoId])->fetchColumn();
        if ($cuantas >= self::MAX_PARTIDAS) throw new RuntimeException('Este evento ya tiene ' . self::MAX_PARTIDAS . ' partidas.');
        $nuevoId = (int) $this->q('INSERT INTO presupuesto_partidas (categoria, concepto, proveedor_id, proveedor_nombre, estado, paga, estimado, contratado,
                                          vence_pago, comision_pct, comision_monto, visible_cliente, notas, negocio_id, evento_id, orden)
                                   VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) RETURNING id', [...$vals, $negocioId, (int) $ev['id'], $cuantas])->fetchColumn();
        self::anotar($this->db, $negocioId, $eventoId, 'Presupuesto: nueva partida «' . $concepto . '» (' . number_format($estimado, 2, ',', '.') . ' dólares)', $usuarioId, $quien);
        return $nuevoId;
    }

    public function borrarPartida(int $negocioId, int $eventoId, int $partidaId, ?int $usuarioId = null, string $quien = 'Sistema'): void
    {
        $p = $this->partida($negocioId, $partidaId);
        if (!$p || (int) $p['evento_id'] !== $eventoId) throw new RuntimeException('No se encontró la partida.');
        if ($p['pagado'] > 0) throw new RuntimeException('Tiene pagos anotados. Márcala como "Descartado" o borra primero sus pagos.');
        if ((float) $p['comision_cobrada'] > 0) throw new RuntimeException('Ya cobraste comisión de esta partida. Márcala como "Descartado".');
        $this->q('DELETE FROM presupuesto_partidas WHERE id = ? AND negocio_id = ?', [$partidaId, $negocioId]);
        self::anotar($this->db, $negocioId, $eventoId, 'Presupuesto: borró «' . $p['concepto'] . '»', $usuarioId, $quien);
    }

    /**
     * Arma las partidas repartiendo un presupuesto con los porcentajes típicos (solo si el evento todavía no tiene partidas).
     * Devuelve cuántas creó.
     */
    public function repartir(int $negocioId, int $eventoId, float $presupuesto, ?int $usuarioId = null, string $quien = 'Sistema'): int
    {
        $ev = $this->evento($negocioId, $eventoId);
        if ($presupuesto <= 0) throw new RuntimeException('Escribe el presupuesto total del cliente.');
        if ($presupuesto > 9999999) throw new RuntimeException('Revisa el presupuesto.');
        if ((int) $this->q('SELECT count(*) FROM presupuesto_partidas WHERE evento_id = ?', [$eventoId])->fetchColumn() > 0) {
            throw new RuntimeException('Este evento ya tiene partidas. Agrega o cambia una por una.');
        }
        $reparto = self::REPARTO[self::claveTipo($ev['tipo'])];
        $ins = $this->db->prepare("INSERT INTO presupuesto_partidas (negocio_id, evento_id, categoria, concepto, estimado, comision_pct, orden)
                                   VALUES (?,?,?,?,?,?,?)");
        $suma = 0.0;
        $propia = !$this->db->inTransaction();
        if ($propia) $this->db->beginTransaction();
        try {
            foreach ($reparto as $i => [$cat, $concepto, $pct]) {
                $monto = $i === count($reparto) - 1 ? round($presupuesto - $suma, 2) : round($presupuesto * $pct / 100, 2);
                $suma += $monto;
                $ins->execute([$negocioId, $eventoId, $cat, $concepto, max(0, $monto), $cat === 'otros' ? 0 : (float) $ev['negocio_comision_pct'], $i]);
            }
            $this->q('UPDATE eventos SET presupuesto_cliente = ?, actualizado_en = now() WHERE id = ?', [round($presupuesto, 2), $eventoId]);
            self::anotar($this->db, $negocioId, $eventoId, 'Presupuesto repartido: ' . number_format($presupuesto, 2, ',', '.') . ' dólares en ' . count($reparto) . ' partidas',
                         $usuarioId, $quien);
            if ($propia) $this->db->commit();
        } catch (\Throwable $e) {
            if ($propia && $this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
        return count($reparto);
    }

    // ------------------------------------------------------------------
    // Pagos directos del cliente a proveedores
    // ------------------------------------------------------------------

    public function pagos(int $negocioId, int $eventoId): array
    {
        return $this->q('SELECT pp.*, p.concepto, p.proveedor_nombre FROM presupuesto_pagos pp
                           JOIN presupuesto_partidas p ON p.id = pp.partida_id
                          WHERE pp.negocio_id = ? AND p.evento_id = ? ORDER BY pp.fecha DESC, pp.id DESC', [$negocioId, $eventoId])->fetchAll();
    }

    /** Anota que el cliente le pagó a un proveedor. $origen = 'planner' (sistema) o 'cliente' (portal). */
    public function registrarPago(int $negocioId, int $eventoId, int $partidaId, mixed $monto, string $fecha, string $metodo = 'transferencia',
                                  string $nota = '', string $origen = 'planner', ?int $usuarioId = null, string $quien = 'Sistema',
                                  ?DateTimeImmutable $hoy = null): int
    {
        $propia = !$this->db->inTransaction();
        if ($propia) $this->db->beginTransaction();
        try {
            $this->q('SELECT id FROM presupuesto_partidas WHERE id = ? AND negocio_id = ? FOR UPDATE', [$partidaId, $negocioId]);
            $p = $this->partida($negocioId, $partidaId);
            if (!$p || (int) $p['evento_id'] !== $eventoId) throw new RuntimeException('No se encontró la partida.');
            if ($origen === 'cliente' && !$p['visible_cliente']) throw new RuntimeException('No se encontró la partida.');
            if ($p['paga'] !== 'cliente') throw new RuntimeException('Esta partida va en el contrato del organizador: se paga en "Tus pagos".');
            if ($p['estado'] === 'cancelado') throw new RuntimeException('Esta partida está descartada.');
            $m = round(Cotizaciones::num($monto), 2);
            if ($m <= 0) throw new RuntimeException('Escribe cuánto se pagó.');
            if ($p['valor'] > 0 && $m > $p['saldo'] + 0.001) {
                throw new RuntimeException('Es más de lo que falta pagarle (' . number_format($p['saldo'], 2, ',', '.') . ' dólares). Revisa el valor o cambia el precio contratado.');
            }
            $f = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
            if (!$f || $f->format('Y-m-d') !== $fecha) throw new RuntimeException('Revisa la fecha del pago.');
            if ($f > ($hoy ?? new DateTimeImmutable('today'))) throw new RuntimeException('La fecha del pago no puede ser futura.');
            if (!isset(self::METODOS[$metodo])) $metodo = 'otro';
            $id = (int) $this->q('INSERT INTO presupuesto_pagos (negocio_id, partida_id, fecha, monto, metodo, nota, origen, registrado_por)
                                  VALUES (?,?,?,?,?,?,?,?) RETURNING id',
                                 [$negocioId, $partidaId, $fecha, $m, $metodo, ($n = trim($nota)) === '' ? null : mb_substr($n, 0, 200), $origen, $usuarioId])->fetchColumn();
            self::anotar($this->db, $negocioId, $eventoId, 'Pago directo a proveedor: ' . number_format($m, 2, ',', '.') . ' dólares · ' . $p['concepto']
                         . ($p['proveedor_nombre'] ? ' (' . $p['proveedor_nombre'] . ')' : ''), $usuarioId, $origen === 'cliente' ? 'Cliente' : $quien);
            if ($propia) $this->db->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($propia && $this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    /** Borra un pago anotado (por error). El cliente solo borra los que él anotó. */
    public function borrarPago(int $negocioId, int $eventoId, int $pagoId, string $quien = 'planner', ?int $usuarioId = null, string $nombre = 'Sistema'): void
    {
        $p = $this->q('SELECT pp.*, p.concepto FROM presupuesto_pagos pp JOIN presupuesto_partidas p ON p.id = pp.partida_id
                        WHERE pp.id = ? AND pp.negocio_id = ? AND p.evento_id = ?', [$pagoId, $negocioId, $eventoId])->fetch();
        if (!$p) throw new RuntimeException('No se encontró el pago.');
        if ($quien === 'cliente' && $p['origen'] !== 'cliente') throw new RuntimeException('Ese pago lo anotó tu organizador: pídele que lo corrija.');
        $this->q('DELETE FROM presupuesto_pagos WHERE id = ?', [$pagoId]);
        self::anotar($this->db, $negocioId, $eventoId, 'Borró el pago directo de ' . number_format((float) $p['monto'], 2, ',', '.') . ' dólares · ' . $p['concepto'],
                     $usuarioId, $quien === 'cliente' ? 'Cliente' : $nombre);
    }

    // ------------------------------------------------------------------
    // Comisiones de proveedores
    // ------------------------------------------------------------------

    /** Anota comisión cobrada al proveedor (no pasa de la comisión total). */
    public function cobrarComision(int $negocioId, int $partidaId, mixed $monto, ?string $fecha = null, ?int $usuarioId = null, string $quien = 'Sistema'): float
    {
        $p = $this->partida($negocioId, $partidaId);
        if (!$p) throw new RuntimeException('No se encontró la partida.');
        if ($p['comision_total'] <= 0) throw new RuntimeException('Esta partida no tiene comisión (o todavía no está contratada).');
        $m = round(Cotizaciones::num($monto), 2);
        if ($m <= 0) throw new RuntimeException('Escribe cuánto te pagó el proveedor.');
        if ($m > $p['comision_por_cobrar'] + 0.001) {
            throw new RuntimeException('Es más de lo que falta cobrar (' . number_format($p['comision_por_cobrar'], 2, ',', '.') . ' dólares).');
        }
        $fecha = $fecha ?: date('Y-m-d');
        $f = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
        if (!$f || $f->format('Y-m-d') !== $fecha) throw new RuntimeException('Revisa la fecha.');
        $this->q('UPDATE presupuesto_partidas SET comision_cobrada = comision_cobrada + ?, comision_cobrada_en = ?, actualizado_en = now() WHERE id = ?',
                 [$m, $fecha, $partidaId]);
        self::anotar($this->db, $negocioId, (int) $p['evento_id'], 'Comisión cobrada: ' . number_format($m, 2, ',', '.') . ' dólares · ' . $p['concepto']
                     . ($p['proveedor_nombre'] ? ' (' . $p['proveedor_nombre'] . ')' : ''), $usuarioId, $quien);
        return round((float) $p['comision_cobrada'] + $m, 2);
    }

    /** Vuelve a cero lo cobrado de una partida (si se anotó por error). */
    public function anularComision(int $negocioId, int $partidaId, ?int $usuarioId = null, string $quien = 'Sistema'): void
    {
        $p = $this->partida($negocioId, $partidaId);
        if (!$p) throw new RuntimeException('No se encontró la partida.');
        $this->q('UPDATE presupuesto_partidas SET comision_cobrada = 0, comision_cobrada_en = NULL WHERE id = ?', [$partidaId]);
        self::anotar($this->db, $negocioId, (int) $p['evento_id'], 'Comisión cobrada borrada · ' . $p['concepto'], $usuarioId, $quien);
    }

    /**
     * Comisiones de todos los eventos activos o realizados.
     * $filtro: 'por_cobrar' (falta cobrar algo) o 'cobradas' (ya cobradas, entre $desde y $hasta).
     */
    public function comisiones(int $negocioId, string $filtro = 'por_cobrar', ?string $desde = null, ?string $hasta = null): array
    {
        $sql = "SELECT p.*, e.codigo, e.nombre AS evento, e.fecha AS evento_fecha, e.estado AS evento_estado, pr.telefono AS proveedor_telefono
                  FROM presupuesto_partidas p JOIN eventos e ON e.id = p.evento_id LEFT JOIN proveedores pr ON pr.id = p.proveedor_id
                 WHERE p.negocio_id = ? AND p.estado = 'contratado' AND (p.comision_pct > 0 OR p.comision_monto > 0)
                   AND e.estado IN ('separado','confirmado','realizado')";
        $par = [$negocioId];
        if ($filtro === 'cobradas') {
            $sql .= ' AND p.comision_cobrada > 0';
            if ($desde) { $sql .= ' AND p.comision_cobrada_en >= ?::date'; $par[] = $desde; }
            if ($hasta) { $sql .= ' AND p.comision_cobrada_en <= ?::date'; $par[] = $hasta; }
        }
        $rows = $this->q($sql . ' ORDER BY e.fecha, p.orden', $par)->fetchAll();
        $res = [];
        foreach ($rows as $r) {
            $r['comision_total'] = self::comision($r);
            $r['comision_por_cobrar'] = round(max(0, $r['comision_total'] - (float) $r['comision_cobrada']), 2);
            if ($filtro === 'por_cobrar' && $r['comision_por_cobrar'] <= 0) continue;
            $res[] = $r;
        }
        return $res;
    }

    /** Comisión cobrada de un evento (para la ganancia real). */
    public function comisionCobradaEvento(int $eventoId): float
    {
        return round((float) $this->q("SELECT COALESCE(sum(comision_cobrada), 0) FROM presupuesto_partidas WHERE evento_id = ?", [$eventoId])->fetchColumn(), 2);
    }

    /** Total de comisiones cobradas en un periodo (reportes). */
    public function comisionesCobradas(int $negocioId, string $desde, string $hasta): float
    {
        return round((float) $this->q('SELECT COALESCE(sum(comision_cobrada), 0) FROM presupuesto_partidas
                                        WHERE negocio_id = ? AND comision_cobrada_en BETWEEN ?::date AND ?::date', [$negocioId, $desde, $hasta])->fetchColumn(), 2);
    }

    // ------------------------------------------------------------------
    // Honorarios y resumen
    // ------------------------------------------------------------------

    public function guardarHonorario(int $negocioId, int $eventoId, string $modo, mixed $pct, ?int $usuarioId = null, string $quien = 'Sistema'): void
    {
        $this->evento($negocioId, $eventoId);
        if (!isset(self::MODOS_HONORARIO[$modo])) throw new RuntimeException('Elige cómo cobras tus honorarios.');
        $p = round(Cotizaciones::num($pct), 2);
        if ($modo === 'porcentaje' && ($p <= 0 || $p > 100)) throw new RuntimeException('El porcentaje debe ser entre 1 y 100.');
        $this->q('UPDATE eventos SET honorario_modo = ?, honorario_pct = ?, actualizado_en = now() WHERE id = ?', [$modo, $p > 0 ? $p : 10, $eventoId]);
        self::anotar($this->db, $negocioId, $eventoId, 'Honorarios: ' . self::MODOS_HONORARIO[$modo] . ($modo === 'porcentaje' ? ' (' . $p . ' %)' : ''), $usuarioId, $quien);
    }

    /**
     * Honorario por porcentaje: % × lo que suman las partidas (sin las descartadas ni las de honorarios).
     * @return array{base: float, pct: float, monto: float}
     */
    public function honorarioSugerido(int $negocioId, int $eventoId): array
    {
        $ev = $this->evento($negocioId, $eventoId);
        $base = 0.0;
        foreach ($this->partidas($negocioId, $eventoId) as $p) {
            if ($p['categoria'] === 'honorarios') continue;
            $base += $p['valor'];
        }
        $pct = (float) $ev['honorario_pct'];
        return ['base' => round($base, 2), 'pct' => $pct, 'monto' => round($base * $pct / 100, 2)];
    }

    /**
     * Pone los honorarios en una cotización en borrador o enviada (si ya hay una línea de honorarios, la reemplaza).
     * Devuelve el id de la línea.
     */
    public function honorarioACotizacion(int $negocioId, int $eventoId, int $cotizacionId, float $monto, ?int $usuarioId = null, string $quien = 'Sistema'): int
    {
        $ev = $this->evento($negocioId, $eventoId);
        $c = $this->q('SELECT id, estado FROM cotizaciones WHERE id = ? AND negocio_id = ? AND evento_id = ?', [$cotizacionId, $negocioId, $eventoId])->fetch();
        if (!$c) throw new RuntimeException('No se encontró la cotización.');
        if ($monto <= 0) throw new RuntimeException('Los honorarios deben ser mayores que 0. Revisa las partidas del presupuesto.');
        $propia = !$this->db->inTransaction();
        if ($propia) $this->db->beginTransaction();
        try {
            $cot = new Cotizaciones($this->db);
            if (in_array($c['estado'], ['borrador', 'enviada'], true)) {
                $this->q("DELETE FROM cotizacion_items WHERE cotizacion_id = ? AND categoria = 'honorarios' AND producto_id IS NULL", [$cotizacionId]);
            }
            $desc = $ev['honorario_modo'] === 'porcentaje'
                ? 'Organización y coordinación integral del evento (' . rtrim(rtrim(number_format((float) $ev['honorario_pct'], 2, ',', ''), '0'), ',') . ' % del presupuesto)'
                : 'Organización y coordinación integral del evento';
            $id = $cot->agregarLineaLibre($negocioId, $cotizacionId, ['categoria' => 'honorarios', 'descripcion' => $desc, 'unidad' => 'evento',
                                                                      'cantidad' => 1, 'precio_unitario' => round($monto, 2), 'con_iva' => true]);
            self::anotar($this->db, $negocioId, $eventoId, 'Honorarios a la cotización: ' . number_format($monto, 2, ',', '.') . ' dólares', $usuarioId, $quien);
            if ($propia) $this->db->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($propia && $this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Todo el dinero del evento en un solo lugar (lo ven el organizador y el cliente).
     */
    public function resumen(int $negocioId, int $eventoId, bool $soloVisibles = false): array
    {
        $ev = $this->evento($negocioId, $eventoId);
        $partidas = $this->partidas($negocioId, $eventoId, $soloVisibles);
        $r = ['directo' => 0.0, 'directo_pagado' => 0.0, 'directo_saldo' => 0.0, 'en_contrato' => 0.0, 'estimado' => 0.0, 'contratado' => 0.0,
              'comision' => 0.0, 'comision_cobrada' => 0.0, 'comision_por_cobrar' => 0.0, 'partidas' => 0, 'contratadas' => 0,
              'por_categoria' => []];
        foreach ($partidas as $p) {
            if ($p['estado'] === 'cancelado') continue;
            $r['partidas']++;
            $r['estimado'] += (float) $p['estimado'];
            if ($p['estado'] === 'contratado') { $r['contratadas']++; $r['contratado'] += $p['valor']; }
            if ($p['paga'] === 'cliente') {
                $r['directo'] += $p['valor'];
                $r['directo_pagado'] += $p['pagado'];
                $r['directo_saldo'] += $p['saldo'];
            } else {
                $r['en_contrato'] += $p['valor'];
            }
            $r['comision'] += $p['comision_total'];
            $r['comision_cobrada'] += (float) $p['comision_cobrada'];
            $r['comision_por_cobrar'] += $p['comision_por_cobrar'];
            $r['por_categoria'][$p['categoria']] = ($r['por_categoria'][$p['categoria']] ?? 0) + $p['valor'];
        }
        $pagos = (new Pagos($this->db))->resumen($eventoId);
        $r['organizador'] = $pagos['total'];
        $r['organizador_pagado'] = $pagos['pagado'];
        $r['organizador_saldo'] = $pagos['saldo'];
        $r['total'] = round($r['organizador'] + $r['directo'], 2);
        $r['pagado'] = round($r['organizador_pagado'] + $r['directo_pagado'], 2);
        $r['falta'] = round($r['organizador_saldo'] + $r['directo_saldo'], 2);
        $r['presupuesto_cliente'] = $ev['presupuesto_cliente'] !== null ? (float) $ev['presupuesto_cliente'] : null;
        // Si todavía no hay cotización aceptada, la comparación usa lo estimado (lo que va en el contrato más lo directo).
        // Para el cliente ($soloVisibles) nunca se suma el costo de lo que el organizador revende: solo su contrato y lo directo.
        $r['proyectado'] = round($r['organizador'] > 0 ? $r['total'] : ($soloVisibles ? $r['directo'] : $r['en_contrato'] + $r['directo']), 2);
        if ($soloVisibles) { $r['en_contrato'] = 0.0; $r['comision'] = $r['comision_cobrada'] = $r['comision_por_cobrar'] = 0.0; $r['por_categoria'] = []; }
        $r['diferencia'] = $r['presupuesto_cliente'] !== null ? round($r['presupuesto_cliente'] - $r['proyectado'], 2) : null;
        $r['pct_pagado'] = $r['total'] > 0 ? min(100, (int) floor($r['pagado'] * 100 / $r['total'])) : 0;
        foreach (['directo', 'directo_pagado', 'directo_saldo', 'en_contrato', 'estimado', 'contratado', 'comision', 'comision_cobrada', 'comision_por_cobrar'] as $k) {
            $r[$k] = round($r[$k], 2);
        }
        arsort($r['por_categoria']);
        return $r;
    }

    // ------------------------------------------------------------------
    // Capacidad del equipo
    // ------------------------------------------------------------------

    /**
     * Cuántos eventos asegurados (separado o confirmado) tiene el negocio ese día, sin contar $excluir, y su máximo.
     * @return array{eventos: list<array>, cuantos: int, maximo: int, lleno: bool}
     */
    public function cargaDelDia(int $negocioId, string $fecha, ?int $excluir = null): array
    {
        $max = (int) $this->q('SELECT max_eventos_dia FROM negocios WHERE id = ?', [$negocioId])->fetchColumn();
        $evs = $this->q("SELECT id, codigo, nombre, estado, hora_inicio FROM eventos
                          WHERE negocio_id = ? AND fecha = ?::date AND estado IN ('separado','confirmado') AND id <> ?
                       ORDER BY hora_inicio", [$negocioId, $fecha, $excluir ?? 0])->fetchAll();
        return ['eventos' => $evs, 'cuantos' => count($evs), 'maximo' => max(1, $max), 'lleno' => count($evs) >= max(1, $max)];
    }

    /** Disponibilidad para la página pública: libre, consultar (le queda un solo cupo) u ocupado (lleno o cerrado). */
    public function disponibilidadPublica(int $negocioId, string $fecha): string
    {
        $cerrado = (int) $this->q('SELECT count(*) FROM fechas_cerradas WHERE negocio_id = ? AND fecha = ?::date AND espacio_id IS NULL',
                                  [$negocioId, $fecha])->fetchColumn();
        if ($cerrado) return 'ocupado';
        $c = $this->cargaDelDia($negocioId, $fecha);
        if ($c['lleno']) return 'ocupado';
        return $c['cuantos'] > 0 && $c['cuantos'] === $c['maximo'] - 1 ? 'consultar' : 'libre';
    }

    // ------------------------------------------------------------------
    // Reuniones con el cliente (del seguimiento: tipo "reunion")
    // ------------------------------------------------------------------

    public function proximasReuniones(int $eventoId, ?DateTimeImmutable $ahora = null): array
    {
        $desde = ($ahora ?? new DateTimeImmutable())->format('Y-m-d 00:00:00');
        return $this->q("SELECT texto, programada_para FROM actividades WHERE evento_id = ? AND tipo IN ('reunion','visita','degustacion')
                            AND NOT hecha AND programada_para >= ?::timestamp ORDER BY programada_para LIMIT 10", [$eventoId, $desde])->fetchAll();
    }
}
