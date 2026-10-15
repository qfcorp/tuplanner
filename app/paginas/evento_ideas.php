<?php
use TuPlanner\{Eventos, Fotos, Inspiracion};

/**
 * Evento — pestaña "Ideas" (migración 100): /?r=evento_ideas&id=<evento>
 *  - Tablero de inspiración: fotos del organizador y del cliente, con categoría, nota y "me encanta / no va".
 *  - Temática y paleta de colores del evento (salen en el portal del cliente).
 *  - &foto=<idea>: la imagen (con sesión).
 * Acciones (POST accion=...): subir, marcar, nota, borrar, estilo. Consulta solo mira.
 */
$nid = (int) $u['negocio_id'];
$id = (int) ($_GET['id'] ?? 0);
$ev = (new Eventos(db()))->obtener($nid, $id);
if (!$ev) {
    http_response_code(404);
    vista('error', ['mensaje' => 'No encontramos ese evento. Puede que el enlace esté mal o que sea de otro negocio.'], 'Ideas · TuPlanner');
    return;
}
$tab = 'evento_ideas';
$ins = new Inspiracion(db());

if (isset($_GET['foto'])) {
    $ruta = $ins->ruta($nid, $id, (int) $_GET['foto']);
    if ($ruta === null) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Foto no encontrada.';
        return;
    }
    Fotos::enviar($ruta);
    return;
}

if (es_post()) {
    verificar_csrf();
    exigir(puede_editar($u), 'Tu usuario solo puede mirar.');
    $accion = (string) ($_POST['accion'] ?? '');
    $ancla = 'ideas';
    try {
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
                        $ins->agregar($nid, $id, $uno, (string) ($_POST['categoria'] ?? 'decoracion'), (string) ($_POST['nota'] ?? ''), 'planner');
                        $n++;
                    } catch (RuntimeException $e) {
                        if ($e instanceof PDOException) throw $e;
                        $errores[] = $e->getMessage();
                    }
                    if ($n >= 12) break;
                }
                if (!$n && !$errores) throw new RuntimeException('Elige una o varias fotos.');
                if ($n) aviso($n === 1 ? 'Foto agregada.' : "$n fotos agregadas.");
                if ($errores) aviso(implode(' ', array_unique($errores)), 'error');
                break;
            case 'marcar':
                $v = (string) ($_POST['valor'] ?? '');
                $ins->marcar($nid, $id, (int) ($_POST['idea_id'] ?? 0), $v === 'si' ? true : ($v === 'no' ? false : null));
                $ancla = 'idea-' . (int) ($_POST['idea_id'] ?? 0);
                break;
            case 'nota':
                $ins->editarNota($nid, $id, (int) ($_POST['idea_id'] ?? 0), (string) ($_POST['nota'] ?? ''), (string) ($_POST['categoria'] ?? ''));
                aviso('Nota guardada.');
                $ancla = 'idea-' . (int) ($_POST['idea_id'] ?? 0);
                break;
            case 'borrar':
                $ins->borrar($nid, $id, (int) ($_POST['idea_id'] ?? 0), 'planner');
                aviso('Foto borrada.');
                break;
            case 'estilo':
                $usar = (array) ($_POST['usar'] ?? []);
                $elegidos = array_filter((array) ($_POST['colores'] ?? []), fn($v, $k) => !empty($usar[$k]), ARRAY_FILTER_USE_BOTH);
                $ins->guardarEstilo($nid, $id, array_values(array_map('strval', $elegidos)), (string) ($_POST['tematica'] ?? ''), (string) $u['nombre'], (int) $u['id']);
                aviso('Temática y colores guardados.');
                $ancla = 'estilo';
                break;
            default:
                throw new RuntimeException('Acción no válida.');
        }
    } catch (RuntimeException $e) {
        if ($e instanceof PDOException) throw $e;
        aviso($e->getMessage(), 'error');
    }
    header('Location: ' . url('evento_ideas', ['id' => $id]) . '#' . $ancla);
    exit;
}

$ideas = $ins->ideas($nid, $id);
$paleta = Inspiracion::paleta($ev['paleta']);
vista('evento_ideas', compact('ev', 'tab', 'ideas', 'paleta'), 'Ideas · ' . $ev['nombre']);
