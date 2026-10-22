<?php
/**
 * Registros propios del operario en los últimos 7 días, con su total.
 * Puede corregir los de hoy y ayer.
 */
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/consultas.php';
$u = exigirRol('operario', 'supervisor', 'admin');

$q = filtrosReporte(['desde' => date('Y-m-d', strtotime('-6 days')), 'hasta' => date('Y-m-d')], $u['id']);
$registros = consultarDetalle($q);
$limiteEdicion = date('Y-m-d', strtotime('-1 day'));

// Agrupa por día para mostrar el total de cada jornada.
$porDia = [];
foreach ($registros as $r) {
    $porDia[$r['fecha']][] = $r;
}

pagina('Mis registros', function () use ($porDia, $limiteEdicion) { ?>
    <h1>Mis registros</h1>
    <p class="suave">Últimos 7 días. Puedes corregir los registros de hoy y de ayer.</p>
    <?php if (!$porDia): ?>
        <div class="aviso aviso-info">Aún no tienes registros esta semana.</div>
    <?php endif; ?>
    <?php foreach ($porDia as $fecha => $filas): ?>
        <h2><?= e(fechaCorta($fecha)) ?> · <?= kg(array_sum(array_column($filas, 'peso_kg'))) ?> kg</h2>
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
