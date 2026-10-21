<?php
declare(strict_types=1);

/**
 * Arranque de TuPlanner: configuración, sesión, conexión y funciones de ayuda.
 * La configuración vive en config/.env (fuera de la carpeta pública y fuera de GitHub).
 * Misma forma de trabajar que TuSalón (familia Tukán).
 */

define('RAIZ', dirname(__DIR__));
date_default_timezone_set('America/Guayaquil');

// 1) Configuración: config/.env -> variables de entorno
$envFile = RAIZ . '/config/.env';
if (is_file($envFile)) {
    foreach (parse_ini_file($envFile, false, INI_SCANNER_RAW) ?: [] as $k => $v) {
        if (getenv($k) === false) {
            putenv("$k=$v");
        }
    }
}

// 2) Cargar clases de src/ y librerías de composer (TCPDF para los PDF)
spl_autoload_register(function (string $clase): void {
    if (str_starts_with($clase, 'TuPlanner\\')) {
        $archivo = RAIZ . '/src/' . substr($clase, 10) . '.php';
        if (is_file($archivo)) {
            require $archivo;
        }
    }
});
if (is_file(RAIZ . '/vendor/autoload.php')) {
    require RAIZ . '/vendor/autoload.php';
}

// 3) Sesión segura (el estado para el Panel Tukán Central no la necesita: define TUKAN_SIN_SESION)
if (PHP_SAPI !== 'cli') {
    if (!defined('TUKAN_SIN_SESION')) {
        session_name('tuplanner');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => es_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
}

// ---------------------------------------------------------------------
// Funciones de ayuda
// ---------------------------------------------------------------------

function es_https(): bool
{
    return (($_SERVER['HTTPS'] ?? '') === 'on') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function db(): PDO
{
    static $pdo = null;
    return $pdo ??= \TuPlanner\Db::conectar();
}

/** Escapa texto para mostrarlo en HTML. */
function e(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $ruta, array $params = []): string
{
    $q = http_build_query(array_merge(['r' => $ruta], $params));
    return '/?' . $q;
}

/** Dirección completa (para enlaces que se mandan por WhatsApp o van en un QR). */
function url_completa(string $ruta, array $params = []): string
{
    $base = rtrim((string) (getenv('TUPLANNER_URL') ?: ''), '/');
    if ($base === '') {
        $host = $_SERVER['HTTP_HOST'] ?? 'tuplanner.tukanec.com';
        $base = (es_https() || !str_starts_with($host, '127.') ? 'https://' : 'http://') . $host;
        if (str_starts_with($host, '127.') || str_starts_with($host, 'localhost')) $base = 'http://' . $host;
    }
    return $base . url($ruta, $params);
}

function redirigir(string $ruta, array $params = []): never
{
    header('Location: ' . url($ruta, $params));
    exit;
}

/** Dinero al estilo ecuatoriano: $1.234,50 */
function dinero(float|string|null $v): string
{
    $v = (float) $v;
    return ($v < 0 ? '−$' : '$') . number_format(abs($v), 2, ',', '.');
}

/** Fecha corta en español: "sáb 14 nov 2026". */
function fecha_corta(?string $fecha, bool $conAnio = true): string
{
    if (!$fecha) return '';
    $d = new DateTimeImmutable($fecha);
    $dias = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];
    $meses = ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    return $dias[(int) $d->format('w')] . ' ' . $d->format('j') . ' ' . $meses[(int) $d->format('n')] . ($conAnio ? ' ' . $d->format('Y') : '');
}

/** Fecha larga: "sábado 14 de noviembre de 2026". */
function fecha_larga(?string $fecha): string
{
    if (!$fecha) return '';
    $d = new DateTimeImmutable($fecha);
    $dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    $meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    return $dias[(int) $d->format('w')] . ' ' . $d->format('j') . ' de ' . $meses[(int) $d->format('n')] . ' de ' . $d->format('Y');
}

function hora_corta(?string $hora): string
{
    return $hora ? substr($hora, 0, 5) : '';
}

function aviso(string $texto, string $tipo = 'ok'): void
{
    $_SESSION['avisos'][] = ['texto' => $texto, 'tipo' => $tipo];
}

function tomar_avisos(): array
{
    $a = $_SESSION['avisos'] ?? [];
    unset($_SESSION['avisos']);
    return $a;
}

function token_csrf(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function campo_csrf(): string
{
    return '<input type="hidden" name="csrf" value="' . e(token_csrf()) . '">';
}

function verificar_csrf(): void
{
    if (!hash_equals(token_csrf(), (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('La página caducó. Vuelve atrás y recarga antes de enviar.');
    }
}

function es_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** Usuario con sesión iniciada (o null), con los datos de su negocio. */
function usuario(): ?array
{
    static $u = false;
    if ($u !== false) {
        return $u;
    }
    if (empty($_SESSION['usuario_id'])) {
        return $u = null;
    }
    $st = db()->prepare(
        'SELECT u.id, u.nombre, u.email, u.rol, u.negocio_id, u.telefono,
                n.nombre AS negocio, n.slug, n.plan_codigo, n.estado, n.prueba_hasta, n.activo_hasta, n.color, n.iva_pct,
                n.pausa_tipo, n.pausa_motivo, pl.nombre AS plan_nombre
           FROM usuarios u JOIN negocios n ON n.id = u.negocio_id JOIN planes pl ON pl.codigo = n.plan_codigo
          WHERE u.id = ? AND u.activo AND n.eliminado_en IS NULL'
    );
    $st->execute([$_SESSION['usuario_id']]);
    return $u = ($st->fetch() ?: null);
}

function requiere_sesion(): array
{
    $u = usuario();
    if (!$u) {
        redirigir('login');
    }
    return $u;
}

/** ¿El usuario puede cambiar cosas? (consulta solo mira) */
function puede_editar(array $u): bool
{
    return $u['rol'] !== 'consulta';
}

/** Dueño, administrador o vendedor: ventas, cotizaciones, contratos y cobros. */
function es_comercial(array $u): bool
{
    return in_array($u['rol'], ['dueno', 'admin', 'vendedor'], true);
}

/**
 * Dueño o administrador: el nivel más alto dentro del negocio (menú, Usuarios, Configuración, Mi plan, dinero…).
 * Úsalo para TODOS los permisos. Los administradores (máx. 4) tienen el mismo nivel que el dueño.
 */
function es_admin_total(array $u): bool
{
    return in_array($u['rol'] ?? '', ['dueno', 'admin'], true);
}

/**
 * Solo el dueño original (la cuenta registrada ante Tukán). NO es un permiso: los permisos van con es_admin_total().
 * Exclusivo del dueño: ser el contacto del negocio ante el Panel Tukán Central y no poder ser cambiado ni desactivado.
 */
function es_dueno(array $u): bool
{
    return ($u['rol'] ?? '') === 'dueno';
}

/** Corta la página si el usuario no tiene permiso. */
function exigir(bool $permitido, string $mensaje = 'Tu usuario no tiene permiso para esta acción.'): void
{
    if (!$permitido) {
        http_response_code(403);
        vista('error', ['mensaje' => $mensaje], 'Sin permiso · TuPlanner');
        exit;
    }
}

/** Días que le quedan a la demo (null si ya pagó). */
function dias_prueba(array $u): ?int
{
    if ($u['estado'] !== 'prueba' || !$u['prueba_hasta']) {
        return null;
    }
    $hoy = new DateTimeImmutable('today');
    $fin = new DateTimeImmutable($u['prueba_hasta']);
    return $hoy > $fin ? -1 : (int) $hoy->diff($fin)->days;
}

/** Muestra una vista dentro del diseño general. */
function vista(string $nombre, array $datos = [], string $titulo = 'TuPlanner'): void
{
    extract($datos, EXTR_SKIP);
    $u = usuario();
    ob_start();
    require RAIZ . "/app/vistas/$nombre.php";
    $contenido = ob_get_clean();
    require RAIZ . '/app/vistas/_diseno.php';
}

/** Vista del panel del super administrador (Tukán). */
function vista_admin(string $nombre, array $datos = [], string $titulo = 'Panel Tukán'): void
{
    extract($datos, EXTR_SKIP);
    $admin = superadmin();
    ob_start();
    require RAIZ . "/app/vistas/$nombre.php";
    $contenido = ob_get_clean();
    require RAIZ . '/app/vistas/_admin.php';
}

/**
 * Vista pública (página del negocio, portal del cliente, invitación, firma), sin menú del sistema.
 * $marca = datos del negocio para pintar su nombre y color.
 */
function vista_publica(string $nombre, array $datos = [], string $titulo = 'TuPlanner', ?array $marca = null): void
{
    extract($datos, EXTR_SKIP);
    ob_start();
    require RAIZ . "/app/vistas/$nombre.php";
    $contenido = ob_get_clean();
    require RAIZ . '/app/vistas/_publico.php';
}

function planes(): \TuPlanner\Planes
{
    static $p = null;
    return $p ??= new \TuPlanner\Planes(db());
}

/** WhatsApp de ventas de Tukán (se cambia en el panel del super administrador). */
function whatsapp_ventas(): string
{
    return planes()->ajuste('whatsapp_ventas') ?: '593996408397';
}

function precio_plan(string $codigo): string
{
    foreach (planes()->lista() as $p) {
        if ($p['codigo'] === $codigo) return dinero($p['precio_mensual']);
    }
    return '';
}

/** Super administrador con sesión (o null). */
function superadmin(): ?array
{
    static $a = false;
    if ($a !== false) return $a;
    if (empty($_SESSION['admin_id'])) return $a = null;
    $st = db()->prepare('SELECT id, nombre, email FROM superadmins WHERE id = ? AND activo');
    $st->execute([$_SESSION['admin_id']]);
    return $a = ($st->fetch() ?: null);
}

function registrar_admin(string $accion, ?int $negocioId = null): void
{
    $a = superadmin();
    db()->prepare('INSERT INTO registro_admin (admin_id, negocio_id, accion) VALUES (?,?,?)')
        ->execute([$a['id'] ?? null, $negocioId, mb_substr($accion, 0, 300)]);
}

/** Ayuda e IA del programa (src/Ia.php con la librería compartida src/tukan_ia.php). */
function ia(): \TuPlanner\Ia
{
    static $i = null;
    return $i ??= new \TuPlanner\Ia(db(), modulos());
}

function modulos(): \TuPlanner\Modulos
{
    static $m = null;
    return $m ??= new \TuPlanner\Modulos(db());
}

/** Módulos encendidos de un negocio (se calcula una vez por página; modulos_olvidar() lo vuelve a leer). */
function modulos_de(int $negocioId, bool $olvidar = false): array
{
    static $cache = [];
    if ($olvidar) { $cache = []; return []; }
    return $cache[$negocioId] ??= modulos()->activos($negocioId);
}

function modulos_olvidar(): void
{
    modulos_de(0, true);
}

/**
 * ¿El negocio tiene encendido este módulo? (Anexo B: la única pregunta que se hace en todo el programa.)
 * $negocio: el usuario (negocio_id), una fila de negocios (id) o el id. $id = '' es la base: siempre true.
 */
function modulo_activo(array|int|null $negocio, string $id): bool
{
    if ($id === '') return true;
    $nid = is_int($negocio) ? $negocio : (int) ($negocio['negocio_id'] ?? $negocio['id'] ?? 0);
    if ($nid <= 0) return false;
    return in_array($id, modulos_de($nid), true);
}

/** Si el módulo está apagado, muestra "Este módulo no está en tu plan" (403) y devuelve false. Los datos no se tocan. */
function requiere_modulo(array $u, string $id): bool
{
    if (modulo_activo($u, $id)) return true;
    http_response_code(403);
    vista('modulo_apagado', ['modulo' => $id], \TuPlanner\Modulos::nombre($id) . ' · TuPlanner');
    return false;
}

/** Número escrito con coma o punto -> float (o null si está vacío). */
function numero(mixed $v): ?float
{
    $v = trim((string) $v);
    if ($v === '') return null;
    $v = str_replace(' ', '', $v);
    // 1.234,50 -> 1234.50 ; 1234,50 -> 1234.50 ; 1234.50 -> 1234.50
    if (str_contains($v, ',') && str_contains($v, '.')) $v = str_replace('.', '', $v);
    elseif (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $v)) $v = str_replace('.', '', $v);   // 12.000 = doce mil
    return (float) str_replace(',', '.', $v);
}

/** Texto de un campo del formulario, sin espacios a los lados. */
function campo(string $nombre, string $defecto = ''): string
{
    return trim((string) ($_POST[$nombre] ?? $defecto));
}

/** Formas de pago que se muestran en las listas. */
function metodos_pago(): array
{
    return \TuPlanner\Pagos::METODOS;
}

/** Guarda en el historial del evento quién hizo qué. */
function registrar_cambio(int $negocioId, ?int $eventoId, string $accion, ?array $quien = null, ?string $nombre = null): void
{
    $quien ??= usuario();
    db()->prepare('INSERT INTO registro_cambios (negocio_id, evento_id, usuario_id, quien, accion) VALUES (?,?,?,?,?)')
        ->execute([$negocioId, $eventoId, $quien['id'] ?? null, $nombre ?? ($quien['nombre'] ?? 'Sistema'), mb_substr($accion, 0, 400)]);
}

/** IP del visitante (detrás del túnel de Cloudflare). */
function ip_visitante(): string
{
    return substr((string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}
