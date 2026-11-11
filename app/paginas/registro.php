<?php
/**
 * "Todo se pide demo": /?r=registro ya no crea una cuenta. Es el formulario para PEDIR UNA DEMO
 * (nombre, nombre comercial, celular, dirección, ciudad y correo, todos obligatorios; qué necesita y los módulos que le interesan). Queda como solicitud
 * demo_pedida en el Panel Tukán Central; Tukán crea la demo y le manda los accesos.
 */
use TuPlanner\{Modulos, PedidosDemo};

if (usuario()) {
    redirigir('inicio');
}
// (10 nov 2026) Demos automáticas de 3 días: los 6 datos son obligatorios (un solo teléfono: el celular) (PedidosDemo::CAMPOS), la clave llega al correo y,
// apenas se guarda el pedido, se avisa al Panel Tukán Central (demo_aviso.php) para que cree la demo en segundos.
$errores = [];      // errores generales (arriba del formulario)
$porCampo = [];     // campo => qué corregir (junto a cada campo)
$listo = false;
$d = PedidosDemo::limpiar([]);
if (es_post()) {
    verificar_csrf();
    $d = PedidosDemo::limpiar($_POST);
    if (!empty($_POST['sitio_web'])) {
        $errores[] = 'No se pudo enviar el pedido.';   // trampa para robots
    } elseif ($porCampo = PedidosDemo::validar($_POST)) {
        $errores[] = 'Revisa los datos marcados: todos son obligatorios.';
    } else {
        try {
            $id = (new PedidosDemo(db()))->crear($_POST, ip_visitante());
            PedidosDemo::avisarPanel($id);   // si el Panel no contesta no pasa nada: lo verá en su revisión de cada 15 minutos
            $listo = true;
        } catch (RuntimeException $e) {
            $errores[] = $e->getMessage();
        }
    }
}
$diasDemo = max(1, (int) planes()->ajuste('dias_prueba'));
vista('registro', ['errores' => $errores, 'porCampo' => $porCampo, 'd' => $d, 'listo' => $listo, 'diasDemo' => $diasDemo, 'catalogo' => modulos()->catalogo()], 'Solicitar demo · TuPlanner');
