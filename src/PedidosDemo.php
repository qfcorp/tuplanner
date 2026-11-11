<?php
declare(strict_types=1);

namespace TuPlanner;

use PDO;
use RuntimeException;

/**
 * "Todo se pide demo": ya no hay registro abierto con periodo de prueba. Quien quiere conocer TuPlanner llena
 * el formulario de /?r=registro (pedido de demo, con sus 6 datos obligatorios: CAMPOS); Tukán lo ve en el Panel Tukán Central (solicitud demo_pedida) o en
 * el panel de Tukán del programa y, si corresponde, crea la demo (crear_demo) con los módulos que le interesan.
 * Estados: nueva → contactada → demo_creada (o descartada).
 */
final class PedidosDemo
{
    public const ESTADOS = ['nueva' => 'Nueva', 'contactada' => 'Contactada', 'demo_creada' => 'Demo creada', 'descartada' => 'Descartada'];
    public const MAX_POR_HORA = 5;   // pedidos por IP en una hora (anti-abuso)

    public function __construct(private PDO $db) {}

    /**
     * Los 6 datos obligatorios del pedido (contrato de las demos automáticas, 10 nov 2026), con estas claves exactas.
     * (19:45, Dimitry: «No hay que poner teléfono fijo; con un teléfono es suficiente») Un solo teléfono: el celular.
     */
    public const CAMPOS = ['nombre', 'nombre_comercial', 'celular', 'direccion', 'ciudad', 'email'];

    /**
     * Deja los datos listos: acepta también los nombres de antes (negocio = nombre_comercial, whatsapp = celular),
     * quita espacios, pasa el correo a minúsculas y deja el celular solo con números y +.
     */
    public static function limpiar(array $d): array
    {
        $t = fn(string $k, string $viejo = '') => trim((string) ($d[$k] ?? ($viejo !== '' ? ($d[$viejo] ?? '') : '')));
        $tel = fn(string $v) => (string) preg_replace('/[\s\-().]+/', '', $v);
        $mods = $d['modulos'] ?? [];
        return [
            'nombre' => $t('nombre'), 'nombre_comercial' => $t('nombre_comercial', 'negocio'), 'celular' => $tel($t('celular', 'whatsapp')), 'direccion' => $t('direccion'), 'ciudad' => $t('ciudad'),
            'email' => strtolower((string) preg_replace('/\s+/', '', $t('email'))), 'necesita' => $t('necesita'),
            'modulos' => array_values(array_filter(is_array($mods) ? $mods : [], fn($m) => is_string($m) && Modulos::existe($m))),
        ];
    }

    /** Revisa el pedido campo por campo. Devuelve [campo => qué corregir] (vacío = todo bien). */
    public static function validar(array $d): array
    {
        $d = self::limpiar($d);
        $e = [];
        if (mb_strlen($d['nombre']) < 3 || mb_strlen($d['nombre']) > 80) $e['nombre'] = 'Escribe tu nombre y apellido.';
        if (mb_strlen($d['nombre_comercial']) < 3 || mb_strlen($d['nombre_comercial']) > 120) $e['nombre_comercial'] = 'Escribe el nombre de tu negocio de eventos.';
        if (!preg_match('/^\+?\d{9,15}$/', $d['celular']) || (str_starts_with($d['celular'], '0') && !preg_match('/^09\d{8}$/', $d['celular']))
            || (str_starts_with($d['celular'], '+593') && !preg_match('/^\+5939\d{8}$/', $d['celular']))) {
            $e['celular'] = 'Escribe tu celular o WhatsApp: 09XXXXXXXX o +5939XXXXXXXX.';
        }
        if (mb_strlen($d['direccion']) < 5 || mb_strlen($d['direccion']) > 250) $e['direccion'] = 'Escribe la dirección de tu negocio.';
        if (mb_strlen($d['ciudad']) < 2 || mb_strlen($d['ciudad']) > 80) $e['ciudad'] = 'Escribe tu ciudad.';
        if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($d['email']) > 120) $e['email'] = 'El correo no es válido. Revísalo: ahí te llega la clave de tu demo.';
        if (mb_strlen($d['necesita']) > 1000) $e['necesita'] = 'Cuéntanos en menos palabras qué necesitas (máximo 1000 letras).';
        return $e;
    }

    /**
     * Guarda un pedido. $d: los 6 datos de CAMPOS (todos obligatorios), más necesita y modulos (lista de ids), que son opcionales.
     * Devuelve el id. Si algo falta, lanza el error con todo lo que hay que corregir.
     */
    public function crear(array $d, string $ip = ''): int
    {
        $errores = self::validar($d);
        if ($errores) throw new RuntimeException(implode(' ', $errores));
        $d = self::limpiar($d);
        if ($ip !== '') {
            $st = $this->db->prepare("SELECT count(*) FROM pedidos_demo WHERE ip = ? AND creado > now() - interval '1 hour'");
            $st->execute([$ip]);
            if ((int) $st->fetchColumn() >= self::MAX_POR_HORA) throw new RuntimeException('Ya recibimos varios pedidos desde aquí. Escríbenos por WhatsApp y te ayudamos.');
        }
        // El mismo correo con un pedido abierto: se actualiza ese pedido (no se repite)
        $st = $this->db->prepare("SELECT id FROM pedidos_demo WHERE lower(email) = ? AND estado IN ('nueva','contactada') ORDER BY id DESC LIMIT 1");
        $st->execute([$d['email']]);
        $abierto = $st->fetchColumn();
        if ($abierto) {
            $this->db->prepare('UPDATE pedidos_demo SET nombre = ?, negocio = ?, ciudad = ?, whatsapp = ?, telefono = ?, direccion = ?, necesita = COALESCE(?, necesita), modulos = COALESCE(NULLIF(?, \'\'), modulos), actualizado = now() WHERE id = ?')
                     ->execute([$d['nombre'], $d['nombre_comercial'], $d['ciudad'], $d['celular'], $d['celular'], $d['direccion'], $d['necesita'] ?: null, Modulos::csv($d['modulos']), $abierto]);
            return (int) $abierto;
        }
        $st = $this->db->prepare('INSERT INTO pedidos_demo (nombre, negocio, ciudad, whatsapp, telefono, direccion, email, necesita, modulos, ip) VALUES (?,?,?,?,?,?,?,?,?,?) RETURNING id');
        $st->execute([$d['nombre'], $d['nombre_comercial'], $d['ciudad'], $d['celular'], $d['celular'], $d['direccion'], $d['email'], $d['necesita'] ?: null, Modulos::csv($d['modulos']), mb_substr($ip, 0, 45) ?: null]);
        return (int) $st->fetchColumn();
    }

    /**
     * Aviso al instante al Panel Tukán Central (demo_aviso.php) para que cree la demo en segundos y no en su revisión de cada
     * 15 minutos. Token v2 de tipo «demo» firmado con el secreto del Panel (el de conectar_central.sh). Máximo 5 segundos.
     * Si el Panel no contesta o TuPlanner no está conectado, no pasa nada: devuelve false y el pedido ya quedó guardado.
     * $enviar (pruebas): fn(string $url, string $cuerpo, string $token): int (código HTTP).
     */
    public static function avisarPanel(int $id, ?callable $enviar = null): bool
    {
        try {
            $c = TukanCentral::config();
            if (!$c) return false;
            $cuerpo = (string) json_encode(['accion' => 'demo_pedida', 'id' => 'demo_pedida:' . $id], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $ahora = time();
            $token = TukanCentral::firmar(['v' => 2, 'tipo' => 'demo', 'inst' => $c['id'], 'email' => 'sistema@tukanec.com', 'nombre' => 'Solicitar demo', 'rol' => 'dueno',
                                           'iat' => $ahora, 'exp' => $ahora + 60, 'nonce' => bin2hex(random_bytes(16)), 'bh' => TukanCentral::b64(hash('sha256', $cuerpo, true))], $c['secreto']);
            $url = TukanCentral::urlCentral() . '/demo_aviso.php';
            if ($enviar) return in_array($enviar($url, $cuerpo, $token), [200, 202], true);
            if (!function_exists('curl_init')) return false;
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $cuerpo, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 5,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json', 'X-Tukan-Token: ' . $token]]);
            $r = curl_exec($ch);
            $http = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            return $r !== false && in_array($http, [200, 202], true);
        } catch (\Throwable) {
            return false;   // nunca se le muestra un error al interesado por esto
        }
    }

    public function obtener(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM pedidos_demo WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    /** Pedidos abiertos (nueva, contactada) y los cerrados desde $desde. */
    public function lista(?string $desde = null): array
    {
        $st = $this->db->prepare("SELECT * FROM pedidos_demo WHERE estado IN ('nueva','contactada') OR actualizado >= ?::date ORDER BY creado DESC, id DESC");
        $st->execute([$desde ?? '1970-01-01']);
        return $st->fetchAll();
    }

    public function abiertos(): array
    {
        return $this->db->query("SELECT * FROM pedidos_demo WHERE estado IN ('nueva','contactada') ORDER BY creado DESC, id DESC")->fetchAll();
    }

    /** Cambia el estado (contactada / descartada / nueva). */
    public function marcar(int $id, string $estado, string $nota = ''): void
    {
        if (!isset(self::ESTADOS[$estado]) || $estado === 'demo_creada') throw new RuntimeException('Estado no válido.');
        $st = $this->db->prepare("UPDATE pedidos_demo SET estado = ?, nota = COALESCE(?, nota), actualizado = now() WHERE id = ? AND estado <> 'demo_creada'");
        $st->execute([$estado, mb_substr(trim($nota), 0, 300) ?: null, $id]);
        if ($st->rowCount() !== 1) throw new RuntimeException('Ese pedido no existe o ya tiene su demo.');
    }

    /** La demo ya se creó: queda unida al negocio. */
    public function demoCreada(int $id, int $negocioId): void
    {
        $this->db->prepare("UPDATE pedidos_demo SET estado = 'demo_creada', negocio_id = ?, actualizado = now() WHERE id = ?")->execute([$negocioId, $id]);
    }
}
