<?php
if (usuario()) {
    redirigir('inicio');
}
$errores = [];
$d = ['negocio' => '', 'nombre' => '', 'telefono' => '', 'email' => '', 'ciudad' => 'Quito', 'plan' => 'quinta'];
if (es_post()) {
    verificar_csrf();
    foreach ($d as $k => $_) $d[$k] = trim((string) ($_POST[$k] ?? ''));
    $d['email'] = strtolower($d['email']);
    $clave = (string) ($_POST['clave'] ?? '');
    if (!empty($_POST['sitio_web'])) $errores[] = 'No se pudo crear la cuenta.';   // trampa para robots
    if (mb_strlen($d['negocio']) < 3) $errores[] = 'Escribe el nombre de tu salón, quinta o hacienda.';
    if (mb_strlen($d['nombre']) < 2) $errores[] = 'Escribe tu nombre.';
    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $errores[] = 'El correo no es válido.';
    if (strlen($clave) < 8) $errores[] = 'La contraseña debe tener al menos 8 caracteres.';
    if (!in_array($d['plan'], ['salon', 'quinta'], true)) $errores[] = 'Elige un plan.';
    if (!$errores) {
        try {
            $alta = (new \TuPlanner\Altas(db()))->crearNegocioConDueno($d['negocio'], $d['nombre'], $d['telefono'], $d['email'], $clave, $d['plan'], $d['ciudad']);
            session_regenerate_id(true);
            $_SESSION['usuario_id'] = $alta['usuario_id'];
            $dias = (int) planes()->ajuste('dias_prueba');
            aviso("¡Listo! Tu negocio está creado con datos de ejemplo (espacios, catálogo, paquetes y contrato). Tienes $dias días gratis para probar todo.");
            redirigir('inicio');
        } catch (RuntimeException $e) {
            $errores[] = $e->getMessage();
        }
    }
}
vista('registro', ['errores' => $errores, 'd' => $d], 'Prueba gratis · TuPlanner');
