<?php
declare(strict_types=1);

/**
 * Base común de las pruebas de lógica de TuPlanner (entorno simulado).
 * Borra y recrea la base de prueba con db/schema.sql + db/migraciones/*.sql.
 * Uso: TUPLANNER_DB_NAME=tuplanner_prueba TUPLANNER_DB_USER=tuplanner TUPLANNER_DB_PASS=prueba php tests/run_tests.php
 */

define('RAIZ', dirname(__DIR__));
date_default_timezone_set('America/Guayaquil');
spl_autoload_register(function (string $clase): void {
    if (str_starts_with($clase, 'TuPlanner\\')) {
        $archivo = RAIZ . '/src/' . substr($clase, 10) . '.php';
        if (is_file($archivo)) require $archivo;
    }
});
if (is_file(RAIZ . '/vendor/autoload.php')) require RAIZ . '/vendor/autoload.php';

// Archivos subidos de las pruebas: carpeta temporal
if (getenv('TUPLANNER_UPLOADS') === false) {
    $tmp = sys_get_temp_dir() . '/tuplanner_pruebas_' . getmypid();
    putenv("TUPLANNER_UPLOADS=$tmp");
}

// IA y correo de Tukán: las pruebas NUNCA leen /etc/tukan-ia.env ni /etc/tukan-correo.env (no gastan ni mandan correos de verdad)
if (getenv('TUKAN_IA_ENV') === false) putenv('TUKAN_IA_ENV=' . sys_get_temp_dir() . '/tuplanner_pruebas_sin_ia.env');
if (getenv('TUKAN_CORREO_ENV') === false) putenv('TUKAN_CORREO_ENV=' . sys_get_temp_dir() . '/tuplanner_pruebas_sin_correo.env');

$GLOBALS['ok'] = 0;
$GLOBALS['fallos'] = [];

function check(string $nombre, mixed $esperado, mixed $real): void
{
    $igual = is_float($esperado) || is_float($real)
        ? abs((float) $esperado - (float) $real) < 0.001
        : $esperado === $real;
    if ($igual) {
        $GLOBALS['ok']++;
        echo "  OK    $nombre\n";
    } else {
        $GLOBALS['fallos'][] = $nombre;
        echo "  FALLA $nombre — esperado " . var_export($esperado, true) . ', salió ' . var_export($real, true) . "\n";
    }
}

/** Verifica que la función lance un error cuyo mensaje contenga $texto. */
function check_error(string $nombre, string $texto, callable $f): void
{
    try {
        $f();
        $GLOBALS['fallos'][] = $nombre;
        echo "  FALLA $nombre — esperaba un error con [$texto] y no hubo error\n";
    } catch (\Throwable $e) {
        if (str_contains($e->getMessage(), $texto)) {
            $GLOBALS['ok']++;
            echo "  OK    $nombre\n";
        } else {
            $GLOBALS['fallos'][] = $nombre;
            echo "  FALLA $nombre — el error fue [" . $e->getMessage() . "]\n";
        }
    }
}

function titulo(string $t): void
{
    echo "\n$t\n";
}

function db_prueba(): PDO
{
    static $db = null;
    if ($db) return $db;
    if (!str_contains((string) getenv('TUPLANNER_DB_NAME'), 'prueba')) {
        exit("Por seguridad, las pruebas solo corren en una base cuyo nombre contenga 'prueba'.\n");
    }
    $db = \TuPlanner\Db::conectar();
    return $db;
}

/** Deja la base vacía con todas las tablas. */
function base_limpia(): PDO
{
    $db = db_prueba();
    $db->exec('DROP SCHEMA public CASCADE; CREATE SCHEMA public;');
    $db->exec(file_get_contents(RAIZ . '/db/schema.sql'));
    foreach (glob(RAIZ . '/db/migraciones/*.sql') ?: [] as $m) {
        $db->exec(file_get_contents($m));
    }
    return $db;
}

/** Crea un negocio de prueba (plan quinta por defecto) con sus datos de ejemplo. Devuelve [negocio_id, usuario (array)]. */
function negocio_prueba(PDO $db, string $plan = 'quinta', string $nombre = 'Quinta Los Arrayanes'): array
{
    $alta = (new \TuPlanner\Altas($db))->crearNegocioConDueno($nombre, 'Dimitry Prueba', '0990000000',
        'dueno' . bin2hex(random_bytes(3)) . '@prueba.ec', 'claveSegura1', $plan);
    $u = $db->query('SELECT * FROM usuarios WHERE id = ' . (int) $alta['usuario_id'])->fetch();
    $db->prepare("UPDATE negocios SET razon_social = 'Arrayanes Eventos S.A.', ruc = '1790000000001', direccion = 'Vía a Tumbaco km 5', horas_montaje = 3, horas_desmontaje = 1 WHERE id = ?")
       ->execute([$alta['negocio_id']]);
    return [$alta['negocio_id'], $u];
}

function espacio_id(PDO $db, int $negocioId, string $nombre): int
{
    $st = $db->prepare('SELECT id FROM espacios WHERE negocio_id = ? AND nombre = ?');
    $st->execute([$negocioId, $nombre]);
    return (int) $st->fetchColumn();
}

/** PNG de una firma dibujada (para probar la firma del contrato). */
function firma_png(bool $vacia = false): string
{
    $img = imagecreatetruecolor(400, 150);
    imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
    if (!$vacia) {
        $negro = imagecolorallocate($img, 10, 10, 40);
        imagesetthickness($img, 4);
        for ($i = 0; $i < 6; $i++) imageline($img, 20 + $i * 50, 120 - ($i % 2) * 80, 70 + $i * 50, 40 + ($i % 2) * 80, $negro);
    }
    ob_start();
    imagepng($img);
    imagedestroy($img);
    return 'data:image/png;base64,' . base64_encode((string) ob_get_clean());
}

/** Archivo de foto simulado para subir (como $_FILES). */
function foto_subida(): array
{
    $ruta = tempnam(sys_get_temp_dir(), 'foto') . '.jpg';
    $img = imagecreatetruecolor(300, 200);
    imagefill($img, 0, 0, imagecolorallocate($img, 200, 220, 240));
    imagejpeg($img, $ruta);
    imagedestroy($img);
    return ['name' => 'comprobante.jpg', 'type' => 'image/jpeg', 'tmp_name' => $ruta, 'error' => UPLOAD_ERR_OK, 'size' => filesize($ruta)];
}
