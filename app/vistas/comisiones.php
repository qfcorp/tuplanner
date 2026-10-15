<?php
use TuPlanner\WhatsApp;
/**
 * Comisiones de proveedores.
 * @var string $ver @var array $periodo @var array $porProveedor @var float $total
 */
$hoy = date('Y-m-d');
$num = fn(float $v) => number_format($v, 2, ',', '');
$puede = puede_editar($u);
?>
<div class="cabecera">
    <div><h1>Comisiones</h1>
        <p>Lo que te pagan los proveedores por llevarles clientes. Se anota en cada partida del presupuesto del evento.</p></div>
</div>
<nav class="pestanas" aria-label="Ver">
    <a href="<?= e(url('comisiones')) ?>" class="<?= $ver === 'por_cobrar' ? 'activa' : '' ?>" <?= $ver === 'por_cobrar' ? 'aria-current="page"' : '' ?>>Por cobrar</a>
    <a href="<?= e(url('comisiones', ['ver' => 'cobradas'])) ?>" class="<?= $ver === 'cobradas' ? 'activa' : '' ?>" <?= $ver === 'cobradas' ? 'aria-current="page"' : '' ?>>Cobradas</a>
</nav>
<?php if ($ver === 'cobradas'): ?>
<nav class="pn-periodos" aria-label="Periodo">
    <?php foreach (['mes' => 'Este mes', 'mes_pasado' => 'Mes pasado', 'anio' => 'Este año'] as $k => $t): ?>
        <a class="boton boton-chico <?= $periodo['clave'] === $k ? '' : 'boton-claro' ?>" href="<?= e(url('comisiones', ['ver' => 'cobradas', 'periodo' => $k])) ?>"><?= e($t) ?></a>
    <?php endforeach; ?>
</nav>
<?php endif; ?>
<section class="cifras" aria-label="Total">
    <div class="cifra-tarjeta <?= $ver === 'cobradas' ? 'bien' : '' ?>"><small><?= $ver === 'cobradas' ? 'Cobrado · ' . e($periodo['nombre']) : 'Te deben los proveedores' ?></small><strong><?= dinero($total) ?></strong></div>
</section>

<?php if (!$porProveedor): ?>
    <p class="vacio bloque"><?= $ver === 'cobradas' ? 'No cobraste comisiones en este periodo.' : 'No tienes comisiones por cobrar. Se calculan cuando una partida del presupuesto queda "Contratado" con un porcentaje o valor de comisión.' ?></p>
<?php endif; ?>

<?php foreach ($porProveedor as $g): ?>
<section class="bloque">
    <h2><?= e($g['nombre']) ?> <small class="suave"><?= dinero($g['total']) ?></small></h2>
    <?php if ($ver === 'por_cobrar' && $g['telefono']):
        $txt = "Hola, te escribo por las comisiones pendientes:\n" . implode("\n", array_map(fn($r) => '• ' . $r['evento'] . ' (' . fecha_corta($r['evento_fecha']) . '): ' . dinero($r['comision_por_cobrar']), $g['filas']))
             . "\nTotal: " . dinero($g['total']) . ". ¡Gracias!"; ?>
        <p><a class="boton boton-wa boton-chico" href="<?= e(WhatsApp::enlace($g['telefono'], $txt)) ?>" target="_blank" rel="noopener">Recordarle por WhatsApp</a></p>
    <?php endif; ?>
    <ul class="filas">
        <?php foreach ($g['filas'] as $r): ?>
        <li>
            <span><a href="<?= e(url('evento_plan', ['id' => $r['evento_id']])) ?>#partida-<?= (int) $r['id'] ?>"><?= e($r['evento']) ?></a>
                <br><small><?= e(fecha_corta($r['evento_fecha'])) ?> · <?= e($r['concepto']) ?> · contratado <?= dinero((float) ($r['contratado'] ?? $r['estimado'])) ?>
                <?= $r['comision_monto'] !== null ? '' : ' × ' . e(rtrim(rtrim($num((float) $r['comision_pct']), '0'), ',')) . ' %' ?>
                <?= $ver === 'cobradas' && $r['comision_cobrada_en'] ? ' · cobrada el ' . e(fecha_corta($r['comision_cobrada_en'])) : '' ?></small></span>
            <span class="derecha">
                <strong><?= dinero($ver === 'cobradas' ? (float) $r['comision_cobrada'] : $r['comision_por_cobrar']) ?></strong>
                <?php if ($ver === 'por_cobrar' && $puede): ?>
                <form method="post" action="<?= e(url('comisiones')) ?>" class="pn-cobrar">
                    <?= campo_csrf() ?><input type="hidden" name="accion" value="cobrar"><input type="hidden" name="partida_id" value="<?= (int) $r['id'] ?>">
                    <input type="hidden" name="monto" value="<?= e($num($r['comision_por_cobrar'])) ?>"><input type="hidden" name="fecha" value="<?= e($hoy) ?>">
                    <button class="boton boton-claro boton-chico" type="submit">Ya me pagó</button>
                </form>
                <?php endif; ?>
            </span>
        </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endforeach; ?>
