<?php
use TuPlanner\{Altas, Usuarios};

/** El super administrador crea un negocio para un cliente (con prueba gratis; si ya pagó, se registra el pago en su ficha). */
$d = ['negocio' => '', 'nombre' => '', 'telefono' => '', 'email' => '', 'ciudad' => 'Quito', 'plan' => 'quinta'];
$error = null;
if (es_post()) {
    verificar_csrf();
    foreach ($d as $k => $_) $d[$k] = trim((string) ($_POST[$k] ?? ''));
    $clave = Usuarios::claveTemporal();
    try {
        $alta = (new Altas(db()))->crearNegocioConDueno($d['negocio'], $d['nombre'], $d['telefono'], $d['email'], $clave, $d['plan'], $d['ciudad'], 'super');
        db()->prepare('UPDATE usuarios SET clave_temporal = true WHERE id = ?')->execute([$alta['usuario_id']]);
        registrar_admin('Negocio creado desde el panel (' . $d['plan'] . ')', $alta['negocio_id']);
        $_SESSION['clave_nueva_' . $alta['negocio_id']] = ['email' => strtolower($d['email']), 'clave' => $clave, 'nombre' => $d['nombre']];
        aviso('Negocio creado con prueba gratis y datos de ejemplo. Si ya pagó, registra el pago abajo.');
        redirigir('admin_negocio', ['id' => $alta['negocio_id']]);
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    }
}
$listaPlanes = planes()->lista();
vista_admin('admin_nuevo', compact('d', 'error', 'listaPlanes'), 'Nuevo negocio · Panel Tukán');
