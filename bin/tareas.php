<?php
declare(strict_types=1);

/**
 * Tareas automáticas de TuPlanner. Se corre CADA HORA con cron:
 *   7 * * * * php /var/www/tuplanner/bin/tareas.php >> /var/log/tuplanner-tareas.log 2>&1   (crontab de www-data)
 *
 * Hace: vence cotizaciones, libera fechas retenidas, avisa cuotas por cobrar (8:00), revisar precio,
 * eventos en 7 días, pasa a "realizado" los eventos que ya pasaron (y avisa devolver la garantía),
 * el reporte del mes (día 1 desde las 7:00), los seguimientos del día por Telegram (8:00) y la papelera del Panel Tukán Central
 * (borra de verdad los negocios con más de 30 días en la papelera y las copias de más de 1 año).
 * Cada cosa se envía una sola vez aunque el cron corra muchas veces.
 *
 * Para probar con otra fecha:  php bin/tareas.php "2026-11-01 08:00"
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

define('RAIZ', dirname(__DIR__));
date_default_timezone_set('America/Guayaquil');

$envFile = RAIZ . '/config/.env';
if (is_file($envFile)) {
    foreach (parse_ini_file($envFile, false, INI_SCANNER_RAW) ?: [] as $k => $v) {
        if (getenv($k) === false) putenv("$k=$v");
    }
}
spl_autoload_register(function (string $c): void {
    if (str_starts_with($c, 'TuPlanner\\') && is_file($f = RAIZ . '/src/' . substr($c, 10) . '.php')) require $f;
});
if (is_file(RAIZ . '/vendor/autoload.php')) require RAIZ . '/vendor/autoload.php';

// Evita que dos tareas corran a la vez
$candado = fopen(sys_get_temp_dir() . '/tuplanner-tareas-' . md5(RAIZ . (getenv('TUPLANNER_DB_NAME') ?: '')) . '.lock', 'c');
if (!$candado || !flock($candado, LOCK_EX | LOCK_NB)) { echo "Ya hay una tarea corriendo.\n"; exit(0); }

try {
    $ahora = new DateTimeImmutable($argv[1] ?? 'now');
} catch (Exception $e) {
    fwrite(STDERR, "Fecha no valida. Ejemplo: php bin/tareas.php \"2026-11-01 08:00\"\n");
    exit(1);
}
try {
    $db = TuPlanner\Db::conectar();
    $r = (new TuPlanner\Automaticas($db))->correr($ahora);
} catch (Throwable $e) {
    fwrite(STDERR, $ahora->format('Y-m-d H:i') . ' ERROR: ' . $e->getMessage() . "\n");
    exit(1);
}
echo TuPlanner\Automaticas::resumen($ahora, $r) . "\n";
exit($r['errores'] ? 1 : 0);
