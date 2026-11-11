<?php
/** Cifra del inicio: pagos a proveedores vencidos y de esta semana (solo para quien ve el dinero). Usa $u y $hoy. */
$pvInicio = (new \TuPlanner\Compras(db()))->resumenInicio((int) $u['negocio_id'], $hoy ?? null);
if ($pvInicio['total'] > 0): ?>
<a class="tk-cifra <?= $pvInicio['vencidas'] ? 'mal' : ($pvInicio['semana'] ? 'te-ambar' : '') ?>" href="<?= e(url('por_pagar')) ?>">
    <div class="tk-cifra-titulo">Pagos a proveedores</div>
    <div class="tk-cifra-valor"><?= dinero($pvInicio['vencidas'] ? $pvInicio['vencido'] : $pvInicio['semana_valor']) ?></div>
    <div class="tk-cifra-detalle">Pagos a proveedores: <?= (int) $pvInicio['vencidas'] ?> <?= $pvInicio['vencidas'] === 1 ? 'vencido' : 'vencidos' ?>, <?= (int) $pvInicio['semana'] ?> esta semana</div></a>
<?php endif;
