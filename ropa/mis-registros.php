<?php
/**
 * Registros de ropa propios de los últimos 7 días, con el total de cada día.
 * El operario puede corregir los de hoy y ayer.
 */
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/ropa.php';
$u = exigirModulo('ropa');

$periodo = ['desde' => date('Y-m-d', strtotime('-6 days')), 'hasta' => date('Y-m-d')];
$limiteEdicion = $u['rol'] === 'operario' ? date('Y-m-d', strtotime('-1 day')) : '';

// Agrupa por día para mostrar el total de cada jornada.
$porDia = [];
foreach (consultarDetalle(filtrosReporte($periodo, $u['id'])) as $r) {
    $porDia[$r['fecha']][] = $r;
}

pagina('Mis registros', function () use ($porDia, $limiteEdicion) { ?>
    <?php encabezado('Mis registros', 'Tu ropa registrada en los últimos 7 días. Puedes corregir los registros de hoy y de ayer.'); ?>
    <?php if (!$porDia): ?>
        <div class="aviso aviso-info">Aún no tienes registros esta semana.</div>
    <?php endif; ?>
    <?php foreach ($porDia as $fecha => $filas): ?>
        <h2><?= e(fechaCorta($fecha)) ?> <span class="suave">· <?= kg(array_sum(array_column($filas, 'peso_kg'))) ?> kg</span></h2>
        <div class="tabla-envoltura">
            <table>
                <thead><tr><th>Rec.</th><th>Ropa</th><th>Área</th><th class="num">Kg</th><th class="num">Bolsas</th><th>Foto</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($filas as $r): ?>
                    <tr>
                        <td><?= (int) $r['recorrido'] ?></td>
                        <td><?= e(TIPOS[$r['tipo']]) ?></td>
                        <td><?= e($r['area']) ?></td>
                        <td class="num"><?= kg($r['peso_kg']) ?></td>
                        <td class="num"><?= (int) $r['bolsas'] ?></td>
                        <td><?php if ($r['foto']): ?><a href="foto.php?id=<?= (int) $r['id'] ?>" target="_blank">Ver</a><?php endif; ?></td>
                        <td><?php if ($r['fecha'] >= $limiteEdicion): ?><a href="editar.php?id=<?= (int) $r['id'] ?>">Corregir</a><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>
<?php });
