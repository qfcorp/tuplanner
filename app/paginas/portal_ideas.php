<?php
use TuPlanner\{Eventos, Fotos, Inspiracion};

/**
 * Tablero de ideas para el cliente (público, con el enlace del portal): /?r=portal_ideas&t=<token_cliente>
 *  - Ve las fotos del organizador y las suyas, la temática y los colores.
 *  - Sube fotos (hasta Inspiracion::MAX_FOTOS_CLIENTE), marca "me encanta / no va" y borra las que subió él.
 *  - &foto=<idea>: la imagen (solo de este evento).
 */
$token = (string) ($_GET['t'] ?? '');
$ev = (new Eventos(db()))->porToken('token_cliente', $token);
$negocio = null;
if ($ev) {
    $st = db()->prepare('SELECT * FROM negocios WHERE id = ?');
    $st->execute([$ev['negocio_id']]);
    $negocio = $st->fetch();
}
if (!$ev || $negocio['estado'] === 'cancelado') {
    http_response_code(404);
    if (isset($_GET['foto'])) { header('Content-Type: text/plain; charset=utf-8'); echo 'Foto no encontrada.'; return; }
    vista_publica('error_publico', ['mensaje' => 'Este enlace no es válido o ya no existe. Pide uno nuevo a quien organiza tu evento.'], 'Ideas · TuPlanner');
    return;
}
$nid = (int) $ev['negocio_id'];
$eid = (int) $ev['id'];
$ins = new Inspiracion(db());

if (isset($_GET['foto'])) {
    $ruta = $ins->ruta($nid, $eid, (int) $_GET['foto']);
    if ($ruta === null) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Foto no encontrada.';
        return;
    }
    Fotos::enviar($ruta);
    return;
}

$marca = ['nombre' => $negocio['nombre'], 'color' => $negocio['color'], 'slug' => $negocio['slug'], 'logo' => $negocio['logo']];
$activo = !in_array($ev['estado'], ['perdido', 'cancelado'], true);

if (es_post()) {
    verificar_csrf();
    $accion = (string) ($_POST['accion'] ?? '');
    $ancla = 'ideas';
    try {
        if (!$activo) throw new RuntimeException('Este evento ya no está activo.');
        if ($negocio['estado'] === 'suspendido') throw new RuntimeException('Por ahora no se pueden guardar cambios. Escríbele a ' . $negocio['nombre'] . '.');
        switch ($accion) {
            case 'subir':
                $archivos = $_FILES['fotos'] ?? null;
                if (!$archivos || !is_array($archivos['name'] ?? null)) throw new RuntimeException('Elige una o varias fotos.');
                $n = 0;
                $errores = [];
                foreach (array_keys($archivos['name']) as $i) {
                    if (($archivos['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
                    $uno = ['name' => $archivos['name'][$i], 'type' => $archivos['type'][$i], 'tmp_name' => $archivos['tmp_name'][$i],
                            'error' => $archivos['error'][$i], 'size' => $archivos['size'][$i]];
                    try {
                        $ins->agregar($nid, $eid, $uno, (string) ($_POST['categoria'] ?? 'decoracion'), (string) ($_POST['nota'] ?? ''), 'cliente');
                        $n++;
                    } catch (RuntimeException $e) {
                        if ($e instanceof PDOException) throw $e;
                        $errores[] = $e->getMessage();
                    }
                    if ($n >= 10) break;
                }
                if (!$n && !$errores) throw new RuntimeException('Elige una o varias fotos.');
                if ($n) {
                    aviso($n === 1 ? '¡Foto agregada! ' . $negocio['nombre'] . ' ya la puede ver.' : "¡$n fotos agregadas! " . $negocio['nombre'] . ' ya las puede ver.');
                    db()->prepare("INSERT INTO registro_cambios (negocio_id, evento_id, quien, accion) VALUES (?,?, 'Cliente', ?)")
                        ->execute([$nid, $eid, 'Subió ' . $n . ' foto' . ($n === 1 ? '' : 's') . ' al tablero de ideas']);
                }
                if ($errores) aviso(implode(' ', array_unique($errores)), 'error');
                break;
            case 'marcar':
                $v = (string) ($_POST['valor'] ?? '');
                $ins->marcar($nid, $eid, (int) ($_POST['idea_id'] ?? 0), $v === 'si' ? true : ($v === 'no' ? false : null));
                $ancla = 'idea-' . (int) ($_POST['idea_id'] ?? 0);
                break;
            case 'borrar':
                $ins->borrar($nid, $eid, (int) ($_POST['idea_id'] ?? 0), 'cliente');
                aviso('Foto borrada.');
                break;
            default:
                throw new RuntimeException('Acción no válida.');
        }
    } catch (RuntimeException $e) {
        if ($e instanceof PDOException) throw $e;
        aviso($e->getMessage(), 'error');
    }
    header('Location: ' . url('portal_ideas', ['t' => $token]) . '#' . $ancla);
    exit;
}

$ideas = $ins->ideas($nid, $eid);
$paleta = Inspiracion::paleta($ev['paleta']);
$propias = count(array_filter($ideas, fn($i) => $i['origen'] === 'cliente'));
$primerNombre = trim(explode(' ', trim((string) $ev['cliente']))[0]);
vista_publica('portal_ideas', compact('ev', 'negocio', 'token', 'activo', 'ideas', 'paleta', 'propias', 'primerNombre'), 'Ideas · ' . $ev['nombre'], $marca);
