<?php
/**
 * Registros propios del operario en los últimos 7 días (ropa y dietas), con sus totales.
 * Puede corregir los de hoy y ayer.
 */
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/consultas.php';
require __DIR__ . '/app/dietas.php';
$u = exigirRol('operario', 'supervisor', 'admin');

$periodo = ['desde' => date('Y-m-d', strtotime('-6 days')), 'hasta' => date('Y-m-d')];
$registros = consultarDetalle(filtrosReporte($periodo, $u['id']));
$entregas = consultarEntregasDieta(filtrosDietas($periodo, $u['id']));
$limiteEdicion = date('Y-m-d', strtotime('-1 day'));

// Agrupa por día para mostrar el total de cada jornada.
$porDia = [];
foreach ($registros as $r) {
    $porDia[$r['fecha']]['ropa'][] = $r;
}
foreach ($entregas as $d) {
    $porDia[$d['fecha']]['dietas'][] = $d;
}
krsort($porDia);

pagina('Mis registros', function () use ($porDia, $limiteEdicion) { ?>
    <h1>Mis registros</h1>
    <p class="suave">Últimos 7 días. Puedes corregir los registros de hoy y de ayer.</p>
    <?php if (!$porDia): ?>
        <div class="aviso aviso-info">Aún no tienes registros esta semana.</div>
    <?php endif; ?>
    <?php foreach ($porDia as $fecha => $dia): ?>
        <h2><?= e(fechaCorta($fecha)) ?></h2>
        <?php if (!empty($dia['ropa'])): $filas = $dia['ropa']; ?>
            <p class="suave">Ropa · <?= kg(array_sum(array_column($filas, 'peso_kg'))) ?> kg</p>
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
        <?php endif; ?>
        <?php if (!empty($dia['dietas'])): $filas = $dia['dietas']; $t = totalesDietas($filas); ?>
            <p class="suave">Dietas · <?= $t['pedidas'] ?> pedidas, <?= $t['entregadas'] ?> entregadas</p>
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
                            <td><?php if ($d['foto']): ?><a href="foto.php?dieta=<?= (int) $d['id'] ?>" target="_blank">Ver</a><?php endif; ?></td>
                            <td><?php if ($d['fecha'] >= $limiteEdicion): ?><a href="editar-dieta.php?id=<?= (int) $d['id'] ?>">Corregir</a><?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
<?php });
