<?php
/**
 * Panel de lavandería: cómo va el día por recorrido y tipo de ropa,
 * con las áreas que faltan y las novedades.
 */
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/ropa.php';
$u = exigirModulo('ropa');

$hoy = date('Y-m-d');
$fecha = min(leerFecha($_GET['fecha'] ?? '') ?? $hoy, $hoy);
$areas = array_column(areasActivas(), 'nombre', 'id');

$st = db()->prepare(
    'SELECT recorrido, tipo, COUNT(*) AS areas, SUM(peso_kg) AS kg, SUM(bolsas) AS bolsas, GROUP_CONCAT(area_id) AS ids
     FROM registros WHERE fecha = ? GROUP BY recorrido, tipo'
);
$st->execute([$fecha]);
$ropa = [];
$total = ['sucia' => 0.0, 'limpia' => 0.0, 'bolsas' => 0];
foreach ($st as $r) {
    $ropa[(int) $r['recorrido']][$r['tipo']] = $r;
    $total[$r['tipo']] += (float) $r['kg'];
    $total['bolsas'] += (int) $r['bolsas'];
}

// Novedades del día (solo supervisor y administrador).
$novedades = [];
if ($u['rol'] !== 'operario') {
    $st = db()->prepare(
        "SELECT r.id, r.creado_en, a.nombre AS area, r.recorrido, r.tipo, r.observaciones, us.nombre AS responsable
         FROM registros r JOIN areas a ON a.id = r.area_id JOIN usuarios us ON us.id = r.usuario_id
         WHERE r.fecha = ? AND r.observaciones <> '' ORDER BY r.creado_en DESC"
    );
    $st->execute([$fecha]);
    $novedades = $st->fetchAll();
}

pagina('Panel', function () use ($u, $fecha, $hoy, $areas, $ropa, $total, $novedades) {
    global $CONFIG; ?>
    <?php panelEncabezado('lavandería', $u, $fecha, $hoy, 'registrar.php', 'Registrar ropa'); ?>

    <div class="indicadores">
        <div class="indicador"><span>Ropa sucia</span><strong><?= kg($total['sucia']) ?> kg</strong></div>
        <div class="indicador"><span>Ropa limpia</span><strong><?= kg($total['limpia']) ?> kg</strong></div>
        <div class="indicador"><span>Limpia − sucia</span><strong><?= kg($total['limpia'] - $total['sucia']) ?> kg</strong></div>
        <div class="indicador"><span>Bolsas</span><strong><?= $total['bolsas'] ?></strong></div>
    </div>

    <div class="panel-seccion">
        <h2>Avance por recorrido</h2>
        <?php if ($u['rol'] !== 'operario'): ?>
            <a href="reporte.php?desde=<?= e($fecha) ?>&amp;hasta=<?= e($fecha) ?>">Ver reporte del día</a>
        <?php endif; ?>
    </div>
    <div class="estado estado-pares">
        <?php foreach ($CONFIG['recorridos'] as $rec): ?>
            <?php foreach (TIPOS as $tipo => $nombreTipo): $r = $ropa[$rec][$tipo] ?? null; ?>
                <?php tarjetaAvance(
                    "Recorrido $rec · $nombreTipo",
                    $r ? kg($r['kg']) . ' kg · ' . (int) $r['bolsas'] . ' bolsas' : 'Sin registros',
                    $r ? (int) $r['areas'] : 0,
                    faltantes($areas, $r['ids'] ?? null),
                    count($areas)
                ); ?>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>

    <?php if ($u['rol'] !== 'operario'): ?>
        <h2>Novedades</h2>
        <?php if (!$novedades): ?>
            <p class="suave">Sin observaciones registradas.</p>
        <?php else: ?>
            <div class="tabla-envoltura">
                <table>
                    <thead><tr><th>Hora</th><th>Área</th><th>Registro</th><th>Observación</th><th>Responsable</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($novedades as $n): ?>
                        <tr>
                            <td><?= e(substr($n['creado_en'], 11, 5)) ?></td>
                            <td><?= e($n['area']) ?></td>
                            <td>Recorrido <?= (int) $n['recorrido'] ?> · <?= e(mb_strtolower(TIPOS[$n['tipo']])) ?></td>
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
