<?php
/**
 * Panel de dietas: cómo va el día por tiempo de comida, con las áreas que
 * faltan, lo pedido frente a lo entregado y las novedades.
 */
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/dietas.php';
$u = exigirModulo('dietas');

$hoy = date('Y-m-d');
$fecha = min(leerFecha($_GET['fecha'] ?? '') ?? $hoy, $hoy);
$areas = array_column(areasDietaActivas(), 'nombre', 'id');

$st = db()->prepare(
    'SELECT e.tiempo, COUNT(DISTINCT e.id) AS areas, SUM(d.pedidas) AS pedidas, SUM(d.entregadas) AS entregadas,
            GROUP_CONCAT(DISTINCT e.area_id) AS ids
     FROM entregas_dieta e JOIN entregas_dieta_detalle d ON d.entrega_id = e.id
     WHERE e.fecha = ? GROUP BY e.tiempo'
);
$st->execute([$fecha]);
$dietas = [];
foreach ($st as $r) {
    $dietas[$r['tiempo']] = $r;
}
$total = totalesDietas($dietas);

// Novedades del día (solo supervisor y administrador).
$novedades = [];
if ($u['rol'] !== 'operario') {
    $st = db()->prepare(
        "SELECT e.id, e.creado_en, a.nombre AS area, e.tiempo, e.observaciones, us.nombre AS responsable
         FROM entregas_dieta e JOIN areas_dieta a ON a.id = e.area_id JOIN usuarios us ON us.id = e.usuario_id
         WHERE e.fecha = ? AND e.observaciones <> '' ORDER BY e.creado_en DESC"
    );
    $st->execute([$fecha]);
    $novedades = $st->fetchAll();
}

pagina('Panel', function () use ($u, $fecha, $hoy, $areas, $dietas, $total, $novedades) {
    global $CONFIG;
    $dif = $total['entregadas'] - $total['pedidas']; ?>
    <?php panelEncabezado('dietas', $u, $fecha, $hoy, 'registrar.php', 'Registrar dietas'); ?>

    <div class="indicadores">
        <div class="indicador"><span>Pedidas</span><strong><?= $total['pedidas'] ?></strong></div>
        <div class="indicador"><span>Entregadas</span><strong><?= $total['entregadas'] ?></strong></div>
        <div class="indicador"><span>Diferencia</span><strong class="<?= $dif < 0 ? 'negativo' : '' ?>"><?= $dif > 0 ? '+' : '' ?><?= $dif ?></strong></div>
        <div class="indicador"><span>Cumplimiento</span><strong><?= cumplimiento($total['pedidas'], $total['entregadas']) ?></strong></div>
    </div>

    <div class="panel-seccion">
        <h2>Avance por tiempo de comida</h2>
        <?php if ($u['rol'] !== 'operario'): ?>
            <a href="reporte.php?desde=<?= e($fecha) ?>&amp;hasta=<?= e($fecha) ?>">Ver reporte del día</a>
        <?php endif; ?>
    </div>
    <div class="estado estado-tres">
        <?php foreach ($CONFIG['tiempos_comida'] as $clave => $nombre): $d = $dietas[$clave] ?? null; ?>
            <?php tarjetaAvance(
                $nombre,
                $d ? "{$d['pedidas']} pedidas · {$d['entregadas']} entregadas" : 'Sin registros',
                $d ? (int) $d['areas'] : 0,
                faltantes($areas, $d['ids'] ?? null),
                count($areas)
            ); ?>
        <?php endforeach; ?>
    </div>

    <?php if ($u['rol'] !== 'operario'): ?>
        <h2>Novedades</h2>
        <?php if (!$novedades): ?>
            <p class="suave">Sin observaciones registradas.</p>
        <?php else: ?>
            <div class="tabla-envoltura">
                <table>
                    <thead><tr><th>Hora</th><th>Área</th><th>Tiempo</th><th>Observación</th><th>Responsable</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($novedades as $n): ?>
                        <tr>
                            <td><?= e(substr($n['creado_en'], 11, 5)) ?></td>
                            <td><?= e($n['area']) ?></td>
                            <td><?= e(nombreTiempo($n['tiempo'])) ?></td>
                            <td class="texto"><?= e($n['observaciones']) ?></td>
                            <td><?= e($n['responsable']) ?></td>
                            <td><a href="editar.php?id=<?= (int) $n['id'] ?>">Ver</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
<?php });
