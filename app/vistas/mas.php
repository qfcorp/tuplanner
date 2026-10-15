<div class="cabecera"><h1>Más</h1></div>
<nav class="lista-mas" aria-label="Todas las secciones">
    <a href="<?= e(url('notificaciones')) ?>">Avisos<small>Pagos, firmas y consultas nuevas</small></a>
    <?php if ($u['rol'] !== 'coordinador'): ?>
    <a href="<?= e(url('clientes')) ?>">Clientes<small>Fichas e historial</small></a>
    <a href="<?= e(url('seguimiento')) ?>">Seguimiento<small>Llamadas y visitas pendientes</small></a>
    <a href="<?= e(url('mensajes')) ?>">Mensajes por WhatsApp<small>Recordatorios listos para enviar</small></a>
    <a href="<?= e(url('catalogo')) ?>">Catálogo y precios<small>Menús, bebidas, decoración…</small></a>
    <a href="<?= e(url('paquetes')) ?>">Paquetes<small>Boda Oro, Quince años…</small></a>
    <a href="<?= e(url('extras')) ?>">Extras del portal<small>Lo que el cliente pide en línea: hora extra, fotocabina…</small></a>
    <?php endif; ?>
    <a href="<?= e(url('espacios')) ?>">Espacios<small>Salones, jardines, fechas cerradas</small></a>
    <a href="<?= e(url('personal')) ?>">Personal<small>Meseros, cocina, seguridad</small></a>
    <a href="<?= e(url('proveedores')) ?>">Proveedores<small>DJ, fotógrafos, floristas</small></a>
    <?php if ($u['rol'] !== 'coordinador'): ?><a href="<?= e(url('por_pagar')) ?>">Por pagar<small>Lo que debes a proveedores y cuándo vence</small></a><?php endif; ?>
    <a href="<?= e(url('incidencias')) ?>">Incidencias<small>Problemas de los eventos y cómo se resolvieron</small></a>
    <a href="<?= e(url('mobiliario')) ?>">Mobiliario<small>Sillas, mesas, mantelería</small></a>
    <?php if ($u['rol'] !== 'coordinador'): ?>
    <a href="<?= e(url('reportes')) ?>">Reportes<small>Ventas, cobranza, ocupación</small></a>
    <?php endif; ?>
    <?php if (es_dueno($u)): ?><a href="<?= e(url('metas')) ?>">Metas de ventas<small>Cuánto quieres vender cada mes</small></a><?php endif; ?>
    <a href="<?= e(url('plano')) ?>">Planos del salón<small>Distribución de mesas de cada espacio</small></a>
    <?php if (es_dueno($u)): ?>
    <a href="<?= e(url('configuracion')) ?>">Configuración<small>Datos, reglas, cobros, página pública</small></a>
    <a href="<?= e(url('contrato')) ?>">Plantillas de contrato<small>Cláusulas y variables del contrato</small></a>
    <a href="<?= e(url('cuestionarios')) ?>">Formularios del evento<small>Preguntas para el cliente por tipo de evento</small></a>
    <a href="<?= e(url('usuarios')) ?>">Usuarios<small>Vendedores y coordinadores</small></a>
    <a href="<?= e(url('planes')) ?>">Mi plan<small>Planes y pagos de TuPlanner</small></a>
    <?php endif; ?>
    <a href="<?= e(url('mi_cuenta')) ?>">Mi cuenta<small>Contraseña y Telegram</small></a>
    <a href="<?= e(url('salir')) ?>">Cerrar sesión</a>
</nav>
