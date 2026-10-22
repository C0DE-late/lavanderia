<?php
/**
 * Reporte para supervisor y administrador: filtros, totales, resumen por área,
 * detalle de registros y descarga en CSV.
 */
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/consultas.php';
exigirRol('supervisor', 'admin');

const LIMITE_PANTALLA = 300;

$q = filtrosReporte($_GET);
$f = $q['filtros'];
$resumen = consultarResumenPorArea($q);
$totales = totalesResumen($resumen);
$detalle = consultarDetalle($q, LIMITE_PANTALLA + 1);
$hayMas = count($detalle) > LIMITE_PANTALLA;
$detalle = array_slice($detalle, 0, LIMITE_PANTALLA);
$consulta = http_build_query($f);

pagina('Reporte', function () use ($f, $resumen, $totales, $detalle, $hayMas, $consulta) {
    global $CONFIG; ?>
    <h1>Reporte de lavandería</h1>

    <form method="get" class="tarjeta">
        <div class="rejilla">
            <div><label for="desde">Desde</label><input type="date" id="desde" name="desde" value="<?= e($f['desde']) ?>"></div>
            <div><label for="hasta">Hasta</label><input type="date" id="hasta" name="hasta" value="<?= e($f['hasta']) ?>"></div>
            <div>
                <label for="area">Área</label>
                <select id="area" name="area">
                    <option value="">Todas</option>
                    <?php foreach (todasLasAreas() as $a): ?>
                        <option value="<?= (int) $a['id'] ?>" <?= $f['area'] === (int) $a['id'] ? 'selected' : '' ?>>
                            <?= e($a['nombre']) ?><?= $a['activa'] ? '' : ' (inactiva)' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="recorrido">Recorrido</label>
                <select id="recorrido" name="recorrido">
                    <option value="">Todos</option>
                    <?php foreach ($CONFIG['recorridos'] as $r): ?>
                        <option value="<?= $r ?>" <?= $f['recorrido'] === $r ? 'selected' : '' ?>>Recorrido <?= $r ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="tipo">Ropa</label>
                <select id="tipo" name="tipo">
                    <option value="">Sucia y limpia</option>
                    <?php foreach (TIPOS as $clave => $nombre): ?>
                        <option value="<?= $clave ?>" <?= $f['tipo'] === $clave ? 'selected' : '' ?>><?= e($nombre) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="usuario">Responsable</label>
                <select id="usuario" name="usuario">
                    <option value="">Todos</option>
                    <?php foreach (usuariosConRegistros() as $p): ?>
                        <option value="<?= (int) $p['id'] ?>" <?= $f['usuario'] === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="acciones">
            <button class="boton" type="submit">Filtrar</button>
            <a class="boton boton-secundario" href="reporte.php">Limpiar</a>
        </div>
    </form>

    <div class="indicadores">
        <div class="indicador"><span>Peso total</span><strong><?= kg($totales['kg_total']) ?> kg</strong></div>
        <div class="indicador"><span>Ropa sucia</span><strong><?= kg($totales['kg_sucia']) ?> kg</strong></div>
        <div class="indicador"><span>Ropa limpia</span><strong><?= kg($totales['kg_limpia']) ?> kg</strong></div>
        <div class="indicador"><span>Bolsas</span><strong><?= (int) ($totales['bolsas_sucia'] + $totales['bolsas_limpia']) ?></strong></div>
    </div>

    <div class="acciones">
        <a class="boton" href="exportar.php?formato=detalle&amp;<?= e($consulta) ?>">⬇ CSV detalle</a>
        <a class="boton boton-secundario" href="exportar.php?formato=resumen&amp;<?= e($consulta) ?>">⬇ CSV total por área</a>
    </div>

    <h2>Peso total por área</h2>
    <p class="suave">Del <?= e(fechaCorta($f['desde'])) ?> al <?= e(fechaCorta($f['hasta'])) ?></p>
    <?php if (!$resumen): ?>
        <div class="aviso aviso-info">No hay registros con estos filtros.</div>
    <?php else: ?>
        <div class="tabla-envoltura">
            <table>
                <thead><tr>
                    <th>Área</th><th class="num">Kg sucia</th><th class="num">Kg limpia</th><th class="num">Kg total</th>
                    <th class="num">Bolsas sucia</th><th class="num">Bolsas limpia</th>
                </tr></thead>
                <tbody>
                <?php foreach ($resumen as $r): ?>
                    <tr>
                        <td><?= e($r['area']) ?></td>
                        <td class="num"><?= kg($r['kg_sucia']) ?></td>
                        <td class="num"><?= kg($r['kg_limpia']) ?></td>
                        <td class="num"><strong><?= kg($r['kg_total']) ?></strong></td>
                        <td class="num"><?= (int) $r['bolsas_sucia'] ?></td>
                        <td class="num"><?= (int) $r['bolsas_limpia'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr>
                    <td>Total</td>
                    <td class="num"><?= kg($totales['kg_sucia']) ?></td>
                    <td class="num"><?= kg($totales['kg_limpia']) ?></td>
                    <td class="num"><?= kg($totales['kg_total']) ?></td>
                    <td class="num"><?= (int) $totales['bolsas_sucia'] ?></td>
                    <td class="num"><?= (int) $totales['bolsas_limpia'] ?></td>
                </tr></tfoot>
            </table>
        </div>

        <h2>Detalle de registros</h2>
        <?php if ($hayMas): ?>
            <div class="aviso aviso-info">
                Se muestran los <?= LIMITE_PANTALLA ?> más recientes. El CSV de detalle incluye todos.
            </div>
        <?php endif; ?>
        <div class="tabla-envoltura">
            <table>
                <thead><tr>
                    <th>Fecha</th><th>Rec.</th><th>Ropa</th><th>Área</th><th class="num">Kg</th><th class="num">Bolsas</th>
                    <th>Responsable</th><th>Observaciones</th><th>Foto</th><th></th>
                </tr></thead>
                <tbody>
                <?php foreach ($detalle as $d): ?>
                    <tr>
                        <td><?= e(fechaCorta($d['fecha'])) ?></td>
                        <td><?= (int) $d['recorrido'] ?></td>
                        <td><?= e(TIPOS[$d['tipo']]) ?></td>
                        <td><?= e($d['area']) ?></td>
                        <td class="num"><?= kg($d['peso_kg']) ?></td>
                        <td class="num"><?= (int) $d['bolsas'] ?></td>
                        <td><?= e($d['responsable']) ?></td>
                        <td class="texto"><?= e($d['observaciones']) ?></td>
                        <td><?php if ($d['foto']): ?><a href="foto.php?id=<?= (int) $d['id'] ?>" target="_blank">Ver</a><?php endif; ?></td>
                        <td><a href="editar.php?id=<?= (int) $d['id'] ?>">Editar</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?php });
