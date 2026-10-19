<?php
use TuPlanner\Planes;

$planes = planes();
if (es_post()) {
    verificar_csrf();
    try {
        if (($_POST['accion'] ?? '') === 'plan') {
            $maxE = trim((string) ($_POST['max_espacios'] ?? ''));
            $maxU = trim((string) ($_POST['max_usuarios'] ?? ''));
            $precioTxt = trim((string) ($_POST['precio_mensual'] ?? ''));
            if ($precioTxt === '' || !is_numeric(str_replace(',', '.', $precioTxt))) throw new RuntimeException('Escribe el precio al mes con números.');
            foreach ([$maxE, $maxU] as $m) if ($m !== '' && !ctype_digit($m)) throw new RuntimeException('Los máximos deben ser números enteros (vacío = sin límite).');
            $precio = (float) str_replace(',', '.', $precioTxt);
            $planes->guardarPlan((string) ($_POST['codigo'] ?? ''), (string) ($_POST['nombre'] ?? ''), $precio,
                $maxE === '' ? null : (int) $maxE, $maxU === '' ? null : (int) $maxU, (string) ($_POST['descripcion'] ?? ''));
            registrar_admin('Plan ' . $_POST['codigo'] . ' actualizado: ' . dinero($precio) . ' al mes · lugares ' . ($maxE ?: 'sin límite') . ' · usuarios ' . ($maxU ?: 'sin límite'));
            aviso('Plan guardado. Los precios nuevos se aplican a los próximos pagos.');
        } elseif (($_POST['accion'] ?? '') === 'catalogo') {   // Anexo B: precio, descripción y orden de cada módulo y qué trae cada plan
            $mods = [];
            foreach (TuPlanner\Modulos::CATALOGO as $mid => $_) {
                $mods[$mid] = ['precio_mensual' => (string) ($_POST['precio'][$mid] ?? ''), 'descripcion' => (string) ($_POST['descripcion'][$mid] ?? ''),
                               'orden' => (string) ($_POST['orden'][$mid] ?? '0')];
            }
            $porPlan = [];
            foreach (modulos()->planes() as $codigo => $_) {
                $porPlan[$codigo] = !empty($_POST['plan_todos'][$codigo]) ? '*' : array_keys((array) ($_POST['plan_mod'][$codigo] ?? []));
            }
            $n = modulos()->guardarCatalogo($mods, $porPlan);
            registrar_admin('Catálogo de módulos guardado (' . $n . ' cambios)');
            aviso($n ? "Catálogo guardado ($n cambios). Los precios nuevos se aplican a los próximos pagos." : 'Sin cambios en el catálogo.');
        } else {
            $planes->guardarAjustes($_POST);
            registrar_admin('Ajustes de demo y promociones actualizados');
            aviso('Ajustes guardados.');
        }
    } catch (RuntimeException $e) {
        aviso($e->getMessage(), 'error');
    }
    redirigir('admin_planes');
}
$lista = $planes->lista();
$a = $planes->ajustes();
$tabla = [];
foreach ($lista as $p) foreach (array_keys(Planes::PERIODOS_NOMBRE) as $per) $tabla[$p['codigo']][$per] = $planes->cotizar($p['codigo'], $per);
$registro = (new TuPlanner\PanelTukan(db()))->registro(null, 40);
$catalogo = modulos()->catalogo();
$planesModulos = modulos()->planes();
vista_admin('admin_planes', compact('lista', 'a', 'tabla', 'registro', 'catalogo', 'planesModulos'), 'Planes y precios · Panel Tukán');
