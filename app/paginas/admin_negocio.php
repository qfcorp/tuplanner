<?php
use TuPlanner\{PanelTukan, Planes};

$id = (int) ($_GET['id'] ?? 0);
$panel = new PanelTukan(db());
$planes = planes();
$negocio = $panel->negocio($id);
if (!$negocio) { aviso('Negocio no encontrado.', 'error'); redirigir('admin_negocios'); }
$claveNueva = null;
if (!empty($_SESSION['clave_nueva_' . $id])) {   // recién creado desde "Nuevo negocio": se muestra una vez
    $claveNueva = $_SESSION['clave_nueva_' . $id];
    unset($_SESSION['clave_nueva_' . $id]);
}

if (es_post()) {
    verificar_csrf();
    $accion = (string) ($_GET['accion'] ?? ($_POST['accion'] ?? ''));
    try {
        switch ($accion) {
            case 'pago':
                $r = $panel->registrarPago($id, $_POST);
                registrar_admin('Pago registrado: ' . $r['plan'] . ' ' . $r['periodo'] . ' ' . dinero($r['monto']) . ' (' . (PanelTukan::METODOS[$r['metodo']] ?? $r['metodo']) . ')', $id);
                aviso('Pago registrado. El negocio quedó activo.');
                break;
            case 'anular_pago':
                $pagoId = (int) ($_POST['pago'] ?? 0);
                $st = db()->prepare('SELECT 1 FROM suscripciones WHERE id = ? AND negocio_id = ?');
                $st->execute([$pagoId, $id]);
                if (!$st->fetchColumn()) throw new RuntimeException('Ese pago no es de este negocio.');
                $planes->anularPago($pagoId);
                registrar_admin('Pago anulado N.º ' . $pagoId, $id);
                aviso('Pago anulado.');
                break;
            case 'plan':
                $panel->cambiarPlan($id, (string) ($_POST['plan'] ?? ''));
                registrar_admin('Plan cambiado a ' . $_POST['plan'], $id);
                aviso('Plan cambiado.');
                break;
            case 'estado':
                $panel->cambiarEstado($id, (string) ($_POST['estado'] ?? ''));
                registrar_admin('Estado cambiado a ' . $_POST['estado'], $id);
                aviso('Estado cambiado.');
                break;
            case 'prueba':
                $hasta = $panel->extenderPrueba($id, (int) ($_POST['dias'] ?? 0));
                registrar_admin('Prueba extendida hasta ' . $hasta, $id);
                aviso('Prueba gratis hasta el ' . date('d/m/Y', strtotime($hasta)) . '.');
                break;
            case 'notas':
                $panel->guardarNotas($id, (string) ($_POST['notas'] ?? ''));
                registrar_admin('Notas internas actualizadas', $id);
                aviso('Notas guardadas.');
                break;
            case 'clave':
                $_SESSION['clave_nueva_' . $id] = $panel->nuevaClaveDueno($id);
                registrar_admin('Nueva contraseña para el dueño', $id);
                aviso('Contraseña nueva creada. La anterior ya no funciona.');
                break;
            default:
                throw new RuntimeException('Acción no válida.');
        }
    } catch (RuntimeException $e) {
        aviso($e->getMessage(), 'error');
    }
    redirigir('admin_negocio', ['id' => $id]);
}
$pagos = $panel->pagos($id);
$registro = $panel->registro($id, 20);
$listaPlanes = $planes->lista();
$cotizaciones = [];
foreach ($listaPlanes as $p) foreach (array_keys(Planes::PERIODOS_NOMBRE) as $per) $cotizaciones[$p['codigo']][$per] = $planes->cotizar($p['codigo'], $per);
$inicioSugerido = $planes->inicioSugerido($id)->format('Y-m-d');
vista_admin('admin_negocio', compact('negocio', 'pagos', 'registro', 'listaPlanes', 'cotizaciones', 'inicioSugerido', 'claveNueva'),
            $negocio['nombre'] . ' · Panel Tukán');
