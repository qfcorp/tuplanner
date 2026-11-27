<?php
declare(strict_types=1);

namespace TuPlanner;

/**
 * País y ciudad de los datos del cliente (27 nov 2026, regla de Dimitry «exterior = sin IVA»).
 *  - El país por defecto es Ecuador. Con cédula o RUC ecuatoriano válido siempre se factura con IVA; con otro país y sin
 *    cédula/RUC válido, la factura y el cobro con Plux salen SIN IVA (la decisión la toma TukanFactura::esExterior).
 *  - Pasaporte: si el país no es Ecuador, la identificación puede ser un pasaporte (letras y números, 5 a 20).
 */
final class Paises
{
    public const DEFECTO = 'Ecuador';

    /** Los países del selector (Ecuador primero, luego por orden alfabético). Un país guardado que no esté aquí se conserva y se muestra igual. */
    public const LISTA = ['Ecuador', 'Alemania', 'Argentina', 'Australia', 'Austria', 'Bélgica', 'Bolivia', 'Brasil', 'Canadá', 'Chile', 'China', 'Colombia',
        'Corea del Sur', 'Costa Rica', 'Cuba', 'Dinamarca', 'El Salvador', 'España', 'Estados Unidos', 'Finlandia', 'Francia', 'Grecia', 'Guatemala', 'Haití',
        'Honduras', 'India', 'Irlanda', 'Israel', 'Italia', 'Japón', 'México', 'Nicaragua', 'Noruega', 'Países Bajos', 'Panamá', 'Paraguay', 'Perú', 'Polonia',
        'Portugal', 'Puerto Rico', 'Reino Unido', 'República Dominicana', 'Rusia', 'Suecia', 'Suiza', 'Turquía', 'Ucrania', 'Uruguay', 'Venezuela'];

    /** País limpio para guardar: sin valor = Ecuador. */
    public static function normalizar(?string $pais): string
    {
        $p = trim(preg_replace('/\s+/', ' ', (string) $pais) ?? '');
        return $p === '' ? self::DEFECTO : mb_substr($p, 0, 60);
    }

    public static function esEcuador(?string $pais): bool
    {
        $p = mb_strtolower(trim((string) $pais));
        return in_array($p, ['', 'ec', 'ecu', 'ecuador', 'república del ecuador', 'republica del ecuador'], true);
    }

    /**
     * Identificación del cliente según su país: en Ecuador, cédula (10 dígitos) o RUC (13); en otro país también vale un pasaporte
     * (letras y números, 5 a 20). Devuelve la identificación limpia ('' si no hay) o lanza el mensaje para mostrar.
     */
    public static function identificacion(?string $valor, ?string $pais): string
    {
        $v = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $valor) ?? '');
        if ($v === '') return '';
        if (self::esEcuador($pais)) {
            $n = preg_replace('/\D+/', '', (string) $valor) ?? '';
            if (!in_array(strlen($n), [10, 13], true)) throw new \RuntimeException('La cédula debe tener 10 dígitos o el RUC 13. Si es del exterior, elige su país.');
            return $n;
        }
        if (!preg_match('/^[A-Z0-9]{5,20}$/', $v)) throw new \RuntimeException('Escribe el pasaporte o la identificación del exterior (5 a 20 letras y números).');
        return $v;
    }

    /** Código SRI para TuFactura: «08» (identificación del exterior) si es un pasaporte, es decir con letras; si no, vacío (TuFactura lo decide). */
    public static function tipoId(?string $identificacion): string
    {
        $v = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $identificacion) ?? '');
        return $v !== '' && preg_match('/[A-Z]/', $v) ? '08' : '';
    }

    /** Lo que se manda a TuFactura y a Tukán Pagos sobre el origen del cliente: identificacion, tipo_id (solo pasaporte), pais, ciudad y direccion. */
    public static function paraFactura(?string $identificacion, ?string $pais, ?string $ciudad, ?string $direccion): array
    {
        return array_filter([
            'identificacion' => (string) $identificacion, 'tipo_id' => self::tipoId($identificacion),
            'pais' => self::normalizar($pais), 'ciudad' => trim((string) $ciudad), 'direccion' => trim((string) $direccion),
        ], fn($v) => $v !== '');
    }
}
