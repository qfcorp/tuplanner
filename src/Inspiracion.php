<?php
declare(strict_types=1);

namespace TuPlanner;

use PDO;
use RuntimeException;

/**
 * Ideas del evento (tablero de inspiración, migración 100): fotos que suben el organizador y el cliente,
 * con categoría, nota y "me encanta / no va"; más la temática y la paleta de colores del evento.
 * Las fotos se guardan con Fotos (limpias, sin datos ocultos) en uploads/ideas.
 */
final class Inspiracion
{
    public const CARPETA = 'ideas';
    public const MAX_FOTOS = 60;            // por evento
    public const MAX_FOTOS_CLIENTE = 30;    // de esas, las que sube el cliente
    public const MAX_COLORES = 6;

    public function __construct(private PDO $db) {}

    private function q(string $sql, array $p = []): \PDOStatement
    {
        $st = $this->db->prepare($sql);
        $st->execute($p);
        return $st;
    }

    public function ideas(int $negocioId, int $eventoId): array
    {
        return $this->q('SELECT * FROM evento_ideas WHERE negocio_id = ? AND evento_id = ? ORDER BY aprobada IS FALSE, creado_en DESC, id DESC',
                        [$negocioId, $eventoId])->fetchAll();
    }

    public function idea(int $negocioId, int $ideaId): ?array
    {
        return $this->q('SELECT * FROM evento_ideas WHERE id = ? AND negocio_id = ?', [$ideaId, $negocioId])->fetch() ?: null;
    }

    /** Guarda una foto nueva. $archivo = elemento de $_FILES. $origen = 'planner' o 'cliente'. */
    public function agregar(int $negocioId, int $eventoId, array $archivo, string $categoria = 'decoracion', string $nota = '',
                            string $origen = 'planner'): int
    {
        if (!in_array($origen, ['planner', 'cliente'], true)) $origen = 'planner';
        $ev = $this->q('SELECT id FROM eventos WHERE id = ? AND negocio_id = ?', [$eventoId, $negocioId])->fetch();
        if (!$ev) throw new RuntimeException('No se encontró el evento.');
        $cuantas = $this->q('SELECT count(*) AS total, count(*) FILTER (WHERE origen = \'cliente\') AS cliente FROM evento_ideas WHERE evento_id = ?',
                            [$eventoId])->fetch();
        if ((int) $cuantas['total'] >= self::MAX_FOTOS) throw new RuntimeException('Ya hay ' . self::MAX_FOTOS . ' fotos. Borra las que ya no van.');
        if ($origen === 'cliente' && (int) $cuantas['cliente'] >= self::MAX_FOTOS_CLIENTE) {
            throw new RuntimeException('Ya subiste ' . self::MAX_FOTOS_CLIENTE . ' fotos. Borra alguna para subir otra.');
        }
        if (!isset(Cotizaciones::CATEGORIAS[$categoria]) || $categoria === 'honorarios') $categoria = 'decoracion';
        $nombre = Fotos::guardarImagen($archivo, Fotos::carpeta(self::CARPETA), 1400);
        return (int) $this->q('INSERT INTO evento_ideas (negocio_id, evento_id, archivo, categoria, nota, origen) VALUES (?,?,?,?,?,?) RETURNING id',
                              [$negocioId, $eventoId, $nombre, $categoria, ($n = trim($nota)) === '' ? null : mb_substr($n, 0, 300), $origen])->fetchColumn();
    }

    /** Me encanta (true), no va (false) o sin decidir (null). */
    public function marcar(int $negocioId, int $eventoId, int $ideaId, ?bool $aprobada): void
    {
        $st = $this->q('UPDATE evento_ideas SET aprobada = ? WHERE id = ? AND negocio_id = ? AND evento_id = ?',
                       [$aprobada === null ? null : ($aprobada ? 'true' : 'false'), $ideaId, $negocioId, $eventoId]);
        if ($st->rowCount() !== 1) throw new RuntimeException('No se encontró la foto.');
    }

    public function editarNota(int $negocioId, int $eventoId, int $ideaId, string $nota, ?string $categoria = null): void
    {
        $i = $this->idea($negocioId, $ideaId);
        if (!$i || (int) $i['evento_id'] !== $eventoId) throw new RuntimeException('No se encontró la foto.');
        $cat = $categoria !== null && isset(Cotizaciones::CATEGORIAS[$categoria]) && $categoria !== 'honorarios' ? $categoria : $i['categoria'];
        $this->q('UPDATE evento_ideas SET nota = ?, categoria = ? WHERE id = ?', [($n = trim($nota)) === '' ? null : mb_substr($n, 0, 300), $cat, $ideaId]);
    }

    /** Borra una foto. El cliente solo borra las que subió él. */
    public function borrar(int $negocioId, int $eventoId, int $ideaId, string $quien = 'planner'): void
    {
        $i = $this->idea($negocioId, $ideaId);
        if (!$i || (int) $i['evento_id'] !== $eventoId) throw new RuntimeException('No se encontró la foto.');
        if ($quien === 'cliente' && $i['origen'] !== 'cliente') throw new RuntimeException('Esa foto la subió tu organizador: solo él la puede borrar.');
        $this->q('DELETE FROM evento_ideas WHERE id = ?', [$ideaId]);
        Fotos::borrar(self::CARPETA, $i['archivo']);
    }

    /** Ruta del archivo de una foto del evento (o null). */
    public function ruta(int $negocioId, int $eventoId, int $ideaId): ?string
    {
        $i = $this->idea($negocioId, $ideaId);
        if (!$i || (int) $i['evento_id'] !== $eventoId) return null;
        return Fotos::ruta(self::CARPETA, $i['archivo']);
    }

    /** Lee una lista de colores (#rrggbb separados por coma o en un arreglo). Ignora lo que no es color. */
    public static function leerPaleta(array|string $colores): array
    {
        $lista = is_array($colores) ? $colores : preg_split('/[\s,;]+/', $colores);
        $res = [];
        foreach ($lista as $c) {
            $c = strtolower(trim((string) $c));
            if (preg_match('/^#?([0-9a-f]{6})$/', $c, $m)) $res[] = '#' . $m[1];
        }
        return array_slice(array_values(array_unique($res)), 0, self::MAX_COLORES);
    }

    public function guardarEstilo(int $negocioId, int $eventoId, array|string $colores, string $tematica, string $quien = 'Sistema', ?int $usuarioId = null): void
    {
        $paleta = self::leerPaleta($colores);
        $tem = trim(preg_replace('/\s+/', ' ', $tematica) ?? '');
        $st = $this->q('UPDATE eventos SET paleta = ?, tematica = ?, actualizado_en = now() WHERE id = ? AND negocio_id = ?',
                       [$paleta ? implode(',', $paleta) : null, $tem === '' ? null : mb_substr($tem, 0, 150), $eventoId, $negocioId]);
        if ($st->rowCount() !== 1) throw new RuntimeException('No se encontró el evento.');
        $this->q('INSERT INTO registro_cambios (negocio_id, evento_id, usuario_id, quien, accion) VALUES (?,?,?,?,?)',
                 [$negocioId, $eventoId, $usuarioId, mb_substr($quien, 0, 120),
                  'Estilo del evento: ' . ($tem !== '' ? $tem : 'sin temática') . ($paleta ? ' · colores ' . implode(' ', $paleta) : '')]);
    }

    /** Paleta guardada como lista. */
    public static function paleta(?string $guardada): array
    {
        return $guardada ? self::leerPaleta($guardada) : [];
    }
}
