<?php
/**
 * Panel de inicio: cómo va el día en lavandería (por recorrido) y en dietas
 * (por tiempo de comida), con las áreas que faltan y las novedades.
 */
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/dietas.php';
$u = exigirRol();

$hoy = date('Y-m-d');
$fecha = leerFecha($_GET['fecha'] ?? '') ?? $hoy;
if ($fecha > $hoy) {
    $fecha = $hoy;
}
$areas = array_column(areasActivas(), 'nombre', 'id');

/** Nombres de las áreas activas que no están en la lista de ids (texto separado por comas de MySQL). */
function faltantes(array $areas, ?string $ids): array
{
    return array_values(array_diff_key($areas, array_flip(array_map('intval', explode(',', (string) $ids)))));
}

// Lavandería: por recorrido y tipo de ropa.
$st = db()->prepare(
    'SELECT recorrido, tipo, COUNT(*) AS areas, SUM(peso_kg) AS kg, SUM(bolsas) AS bolsas, GROUP_CONCAT(area_id) AS ids
     FROM registros WHERE fecha = ? GROUP BY recorrido, tipo'
);
$st->execute([$fecha]);
$ropa = [];
$ropaTotal = ['sucia' => 0.0, 'limpia' => 0.0, 'bolsas' => 0];
foreach ($st as $r) {
    $ropa[(int) $r['recorrido']][$r['tipo']] = $r;
    $ropaTotal[$r['tipo']] += (float) $r['kg'];
    $ropaTotal['bolsas'] += (int) $r['bolsas'];
}

// Dietas: por tiempo de comida.
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
$dietasTotal = totalesDietas($dietas);

// Novedades del día: observaciones de ropa y de dietas (solo supervisor y administrador).
$novedades = [];
if ($u['rol'] !== 'operario') {
    $st = db()->prepare(
        "SELECT 'ropa' AS modulo, r.id, r.creado_en, a.nombre AS area, CONCAT('Recorrido ', r.recorrido, ' · ropa ', r.tipo) AS detalle,
                r.observaciones, us.nombre AS responsable
         FROM registros r JOIN areas a ON a.id = r.area_id JOIN usuarios us ON us.id = r.usuario_id
         WHERE r.fecha = ? AND r.observaciones <> ''
         UNION ALL
         SELECT 'dieta', e.id, e.creado_en, a.nombre, e.tiempo, e.observaciones, us.nombre
         FROM entregas_dieta e JOIN areas a ON a.id = e.area_id JOIN usuarios us ON us.id = e.usuario_id
         WHERE e.fecha = ? AND e.observaciones <> ''
         ORDER BY creado_en DESC"
    );
    $st->execute([$fecha, $fecha]);
    $novedades = $st->fetchAll();
}

/** Tarjeta de avance: cuántas áreas van, barra y áreas que faltan. */
function tarjetaAvance(string $titulo, string $cifra, int $hechas, array $faltan, int $total): void
{
    $porcentaje = $total > 0 ? min(100, round($hechas * 100 / $total)) : 0; ?>
    <div class="estado-item">
        <strong><?= e($titulo) ?></strong>
        <span class="suave"><?= e($cifra) ?></span>
        <div class="barra" aria-hidden="true"><span style="width:<?= $porcentaje ?>%"></span></div>
        <span class="pendientes"><?= $hechas ?> de <?= $total ?> áreas</span>
        <?php if ($hechas && $faltan): ?>
            <details class="pendientes"><summary>Faltan <?= count($faltan) ?></summary><?= e(implode(', ', $faltan)) ?></details>
        <?php endif; ?>
    </div>
<?php }

pagina('Inicio', function () use ($u, $fecha, $hoy, $areas, $ropa, $ropaTotal, $dietas, $dietasTotal, $novedades) {
    global $CONFIG;
    $totalAreas = count($areas); ?>
    <div class="panel-seccion">
        <h1>Hola, <?= e(explode(' ', $u['nombre'])[0]) ?></h1>
        <form method="get" class="fila">
            <label for="fecha" class="oculto">Día</label>
            <input type="date" id="fecha" name="fecha" value="<?= e($fecha) ?>" max="<?= e($hoy) ?>" style="width:auto" onchange="this.form.submit()">
        </form>
    </div>
    <p class="suave"><?= $fecha === $hoy ? 'Así va el día de hoy' : 'Resumen del ' . e(fechaCorta($fecha)) ?>.</p>

    <div class="acciones">
        <a class="boton" href="registrar.php">🧺 Registrar ropa</a>
        <a class="boton" href="registrar-dieta.php">🍽 Registrar dietas</a>
    </div>

    <div class="panel-seccion">
        <h2>Lavandería</h2>
        <?php if ($u['rol'] !== 'operario'): ?>
            <a href="reporte.php?desde=<?= e($fecha) ?>&amp;hasta=<?= e($fecha) ?>">Ver reporte</a>
        <?php endif; ?>
    </div>
    <div class="indicadores">
        <div class="indicador"><span>Ropa sucia</span><strong><?= kg($ropaTotal['sucia']) ?> kg</strong></div>
        <div class="indicador"><span>Ropa limpia</span><strong><?= kg($ropaTotal['limpia']) ?> kg</strong></div>
        <div class="indicador"><span>Limpia − sucia</span><strong><?= kg($ropaTotal['limpia'] - $ropaTotal['sucia']) ?> kg</strong></div>
        <div class="indicador"><span>Bolsas</span><strong><?= $ropaTotal['bolsas'] ?></strong></div>
    </div>
    <div class="estado estado-pares">
        <?php foreach ($CONFIG['recorridos'] as $rec): ?>
            <?php foreach (TIPOS as $tipo => $nombreTipo): $r = $ropa[$rec][$tipo] ?? null; ?>
                <?php tarjetaAvance(
                    "Recorrido $rec · $nombreTipo",
                    $r ? kg($r['kg']) . ' kg · ' . (int) $r['bolsas'] . ' bolsas' : 'Sin registros',
                    $r ? (int) $r['areas'] : 0,
                    faltantes($areas, $r['ids'] ?? null),
                    $totalAreas
                ); ?>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>

    <div class="panel-seccion">
        <h2>Dietas</h2>
        <?php if ($u['rol'] !== 'operario'): ?>
            <a href="reporte-dietas.php?desde=<?= e($fecha) ?>&amp;hasta=<?= e($fecha) ?>">Ver reporte</a>
        <?php endif; ?>
    </div>
    <?php $dif = $dietasTotal['entregadas'] - $dietasTotal['pedidas']; ?>
    <div class="indicadores">
        <div class="indicador"><span>Pedidas</span><strong><?= $dietasTotal['pedidas'] ?></strong></div>
        <div class="indicador"><span>Entregadas</span><strong><?= $dietasTotal['entregadas'] ?></strong></div>
        <div class="indicador"><span>Diferencia</span><strong class="<?= $dif < 0 ? 'negativo' : '' ?>"><?= $dif > 0 ? '+' : '' ?><?= $dif ?></strong></div>
        <div class="indicador"><span>Cumplimiento</span><strong><?= cumplimiento($dietasTotal['pedidas'], $dietasTotal['entregadas']) ?></strong></div>
    </div>
    <div class="estado">
        <?php foreach ($CONFIG['tiempos_comida'] as $clave => $nombre): $d = $dietas[$clave] ?? null; ?>
            <?php tarjetaAvance(
                $nombre,
                $d ? "{$d['pedidas']} pedidas · {$d['entregadas']} entregadas" : 'Sin registros',
                $d ? (int) $d['areas'] : 0,
                faltantes($areas, $d['ids'] ?? null),
                $totalAreas
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
                    <thead><tr><th>Hora</th><th>Área</th><th>Registro</th><th>Observación</th><th>Responsable</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($novedades as $n): $esDieta = $n['modulo'] === 'dieta'; ?>
                        <tr>
                            <td><?= e(substr($n['creado_en'], 11, 5)) ?></td>
                            <td><?= e($n['area']) ?></td>
                            <td><?= e($esDieta ? 'Dietas · ' . nombreTiempo($n['detalle']) : $n['detalle']) ?></td>
                            <td class="texto"><?= e($n['observaciones']) ?></td>
                            <td><?= e($n['responsable']) ?></td>
                            <td><a href="<?= $esDieta ? 'editar-dieta.php' : 'editar.php' ?>?id=<?= (int) $n['id'] ?>">Ver</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
<?php });
