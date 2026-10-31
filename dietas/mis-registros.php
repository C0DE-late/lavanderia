<?php
/**
 * Entregas de dietas propias de los últimos 7 días, con el total de cada día.
 * El operario puede corregir las de hoy y ayer.
 */
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/dietas.php';
$u = exigirModulo('dietas');

$periodo = ['desde' => date('Y-m-d', strtotime('-6 days')), 'hasta' => date('Y-m-d')];
$limiteEdicion = $u['rol'] === 'operario' ? date('Y-m-d', strtotime('-1 day')) : '';

// Agrupa por día para mostrar el total de cada jornada.
$porDia = [];
foreach (consultarEntregasDieta(filtrosDietas($periodo, $u['id'])) as $d) {
    $porDia[$d['fecha']][] = $d;
}

pagina('Mis registros', function () use ($porDia, $limiteEdicion) { ?>
    <?php encabezado('Mis registros', 'Tus dietas registradas en los últimos 7 días. Puedes corregir las de hoy y de ayer.'); ?>
    <?php if (!$porDia): ?>
        <div class="aviso aviso-info">Aún no tienes registros esta semana.</div>
    <?php endif; ?>
    <?php foreach ($porDia as $fecha => $filas): $t = totalesDietas($filas); ?>
        <h2><?= e(fechaCorta($fecha)) ?> <span class="suave">· <?= $t['pedidas'] ?> pedidas, <?= $t['entregadas'] ?> entregadas</span></h2>
        <div class="tabla-envoltura">
            <table>
                <thead><tr><th>Tiempo</th><th>Área</th><th class="num">Pedidas</th><th class="num">Entregadas</th><th>Foto</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($filas as $d): ?>
                    <tr>
                        <td><?= e(nombreTiempo($d['tiempo'])) ?></td>
                        <td><?= e($d['area']) ?></td>
                        <td class="num"><?= (int) $d['pedidas'] ?></td>
                        <td class="num <?= $d['entregadas'] < $d['pedidas'] ? 'negativo' : '' ?>"><?= (int) $d['entregadas'] ?></td>
                        <td><?php if ($d['foto']): ?><a href="foto.php?id=<?= (int) $d['id'] ?>" target="_blank">Ver</a><?php endif; ?></td>
                        <td><?php if ($d['fecha'] >= $limiteEdicion): ?><a href="editar.php?id=<?= (int) $d['id'] ?>">Corregir</a><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>
<?php });
