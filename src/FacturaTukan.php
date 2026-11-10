<?php
declare(strict_types=1);

namespace TuPlanner;

use PDO;
use Throwable;

require_once __DIR__ . '/TukanFactura.php';

/**
 * Factura electrónica con TuFactura (módulo «facturacion», APAGADO de fábrica y solo a pedido: ni «*» ni la demo lo traen; se
 * enciende desde el Panel Tukán Central cuando el negocio compra TuFactura). (9 nov 2026)
 * La pieza común está en src/TukanFactura.php (copia de tufactura/docs/enlace/TukanFactura.php: no se cambia aquí).
 *  - Cada pago CONFIRMADO de un evento (tipo «evento»; la garantía no, porque se devuelve) se puede facturar con un toque desde
 *    «Factura electrónica», o sale solo al registrarlo o confirmarlo si el negocio eligió «automático».
 *  - La factura va a nombre del cliente del evento (cédula o RUC); sin identificación válida, a consumidor final.
 *  - La clave tf_ de cada negocio se guarda cifrada con TUKAN_FACTURA_LLAVE (o, si no está, con la clave de la base de datos).
 */
final class FacturaTukan
{
    public const PROGRAMA = 'TuPlanner';
    public const ORIGEN = 'pago';
    private static ?PDO $pdo = null;

    public static function db(?PDO $db = null): PDO
    {
        if ($db !== null) self::$pdo = $db;
        return self::$pdo ??= function_exists('db') ? \db() : Db::conectar();
    }

    public static function enlace(?PDO $db = null): \TukanFactura
    {
        static $t = [];
        $db = self::db($db);
        $llave = getenv('TUKAN_FACTURA_LLAVE') ?: ('TuPlanner|' . (getenv('TUPLANNER_DB_PASS') ?: '') . '|' . (getenv('TUPLANNER_DB_NAME') ?: 'tuplanner'));
        return $t[spl_object_id($db)] ??= new \TukanFactura($db, $llave, self::PROGRAMA);
    }

    public static function activo(int $negocio): bool
    {
        return (new Modulos(self::db()))->activo($negocio, \TukanFactura::MODULO);
    }

    /** Lo que se manda a TuFactura de un pago: [cliente, items, extra], o null si no existe, no está confirmado o no es del evento. */
    public static function datos(int $negocio, string $pagoId): ?array
    {
        if (!ctype_digit($pagoId)) return null;
        $st = self::db()->prepare("SELECT p.*, e.nombre AS evento, e.codigo, e.fecha AS fecha_evento,
                                          c.nombre AS c_nombre, c.identificacion AS c_ident, c.email AS c_email, c.telefono AS c_tel, c.direccion AS c_dir
                                     FROM pagos p JOIN eventos e ON e.id = p.evento_id JOIN clientes c ON c.id = e.cliente_id
                                    WHERE p.id = ? AND p.negocio_id = ? AND p.tipo = 'evento' AND p.estado = 'confirmado'");
        $st->execute([(int) $pagoId, $negocio]);
        $p = $st->fetch(PDO::FETCH_ASSOC);
        if (!$p) return null;
        $cliente = ['identificacion' => (string) $p['c_ident'], 'nombre' => (string) $p['c_nombre'], 'correo' => (string) $p['c_email'],
                    'telefono' => (string) $p['c_tel'], 'direccion' => (string) $p['c_dir']];
        $items = [['descripcion' => 'Evento: ' . $p['evento'], 'detalle' => $p['codigo'] . ' · ' . date('d/m/Y', strtotime((string) $p['fecha_evento'])), 'cantidad' => 1, 'precio' => (float) $p['monto']]];
        return [$cliente, $items, ['forma' => (string) $p['metodo'], 'info' => array_filter(['Evento' => (string) $p['codigo'], 'Recibo' => $p['numero_recibo'] ? 'N.º ' . $p['numero_recibo'] : ''])]];
    }

    /** Los últimos pagos confirmados para facturar a mano: [id, fecha, cliente, monto, detalle]. */
    public static function cobros(int $negocio, int $limite = 25): array
    {
        $st = self::db()->prepare("SELECT p.id, p.fecha, c.nombre, p.monto, e.nombre AS evento, e.codigo
                                     FROM pagos p JOIN eventos e ON e.id = p.evento_id JOIN clientes c ON c.id = e.cliente_id
                                    WHERE p.negocio_id = ? AND p.tipo = 'evento' AND p.estado = 'confirmado'
                                    ORDER BY p.fecha DESC, p.id DESC LIMIT " . max(1, min(100, $limite)));
        $st->execute([$negocio]);
        return array_map(fn($r) => [(string) $r['id'], (string) $r['fecha'], (string) $r['nombre'], (float) $r['monto'], $r['evento'] . ' (' . $r['codigo'] . ')'], $st->fetchAll(PDO::FETCH_ASSOC));
    }

    /** Al registrar o confirmar un pago (Pagos): si el negocio eligió «automático», sale la factura sola. Nunca tumba el pago. */
    public static function alCobrar(PDO $db, int $negocio, int $pagoId): void
    {
        try {
            self::db($db);
            if (!self::activo($negocio)) return;
            self::enlace($db)->alCobrar($negocio, true, self::ORIGEN, (string) $pagoId, fn() => self::datos($negocio, (string) $pagoId));
        } catch (Throwable $e) {
            error_log(self::PROGRAMA . ' TuFactura: ' . $e->getMessage());
        }
    }
}
