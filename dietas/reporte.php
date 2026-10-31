<?php
/**
 * Reporte de dietas para supervisor y administrador: filtros, pedidas frente a
 * entregadas, totales por área, por tipo y por tiempo de comida, detalle y CSV.
 */
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/dietas.php';
exigirModulo('dietas', 'supervisor', 'admin');

const LIMITE_PANTALLA = 300;

$q = filtrosDietas($_GET);
$f = $q['filtros'];
$porArea = resumenDietas($q, 'area');
$porTipo = resumenDietas($q, 'dieta');
$porTiempo = resumenDietas($q, 'tiempo');
$totales = totalesDietas($porArea);
$entregas = consultarEntregasDieta($q, LIMITE_PANTALLA + 1);
$hayMas = count($entregas) > LIMITE_PANTALLA;
$entregas = array_slice($entregas, 0, LIMITE_PANTALLA);
$consulta = http_build_query($f);

/** Tabla de totales con pedidas, entregadas, diferencia y cumplimiento. */
function tablaResumenDietas(string $titulo, array $filas, array $totales): void
{ ?>
    <div class="tabla-envoltura" style="margin-bottom:16px">
        <table>
            <thead><tr>
                <th><?= e($titulo) ?></th><th class="num">Pedidas</th><th class="num">Entregadas</th>
                <th class="num">Diferencia</th><th class="num">Cumplimiento</th>
            </tr></thead>
            <tbody>
            <?php foreach ($filas as $r): $dif = (int) $r['entregadas'] - (int) $r['pedidas']; ?>
                <tr>
                    <td><?= e($r['nombre']) ?></td>
                    <td class="num"><?= (int) $r['pedidas'] ?></td>
                    <td class="num"><?= (int) $r['entregadas'] ?></td>
                    <td class="num <?= $dif < 0 ? 'negativo' : ($dif > 0 ? 'positivo' : '') ?>"><?= $dif > 0 ? '+' : '' ?><?= $dif ?></td>
                    <td class="num"><?= cumplimiento($r['pedidas'], $r['entregadas']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr>
                <td>Total</td>
                <td class="num"><?= $totales['pedidas'] ?></td>
                <td class="num"><?= $totales['entregadas'] ?></td>
                <td class="num"><?= $totales['entregadas'] - $totales['pedidas'] ?></td>
                <td class="num"><?= cumplimiento($totales['pedidas'], $totales['entregadas']) ?></td>
            </tr></tfoot>
        </table>
    </div>
<?php }

pagina('Reporte de dietas', function () use ($f, $porArea, $porTipo, $porTiempo, $totales, $entregas, $hayMas, $consulta) {
    global $CONFIG; ?>
    <?php encabezado('Reporte de dietas', 'Filtra, revisa lo pedido frente a lo entregado y descarga los CSV para Excel.'); ?>

    <form method="get" class="tarjeta">
        <div class="rejilla">
            <div><label for="desde">Desde</label><input type="date" id="desde" name="desde" value="<?= e($f['desde']) ?>"></div>
            <div><label for="hasta">Hasta</label><input type="date" id="hasta" name="hasta" value="<?= e($f['hasta']) ?>"></div>
            <div>
                <label for="area">Área</label>
                <select id="area" name="area">
                    <option value="">Todas</option>
                    <?php foreach (todasLasAreasDieta() as $a): ?>
                        <option value="<?= (int) $a['id'] ?>" <?= $f['area'] === (int) $a['id'] ? 'selected' : '' ?>>
                            <?= e($a['nombre']) ?><?= $a['activa'] ? '' : ' (inactiva)' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="tiempo">Tiempo de comida</label>
                <select id="tiempo" name="tiempo">
                    <option value="">Todos</option>
                    <?php foreach ($CONFIG['tiempos_comida'] as $clave => $nombre): ?>
                        <option value="<?= e($clave) ?>" <?= $f['tiempo'] === $clave ? 'selected' : '' ?>><?= e($nombre) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="dieta">Tipo de dieta</label>
                <select id="dieta" name="dieta">
                    <option value="">Todos</option>
                    <?php foreach (todosLosTiposDieta() as $t): ?>
                        <option value="<?= (int) $t['id'] ?>" <?= $f['dieta'] === (int) $t['id'] ? 'selected' : '' ?>>
                            <?= e($t['nombre']) ?><?= $t['activa'] ? '' : ' (inactiva)' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="usuario">Responsable</label>
                <select id="usuario" name="usuario">
                    <option value="">Todos</option>
                    <?php foreach (usuariosConEntregas() as $p): ?>
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

    <?php $dif = $totales['entregadas'] - $totales['pedidas']; ?>
    <div class="indicadores">
        <div class="indicador"><span>Pedidas</span><strong><?= $totales['pedidas'] ?></strong></div>
        <div class="indicador"><span>Entregadas</span><strong><?= $totales['entregadas'] ?></strong></div>
        <div class="indicador"><span>Diferencia</span><strong class="<?= $dif < 0 ? 'negativo' : '' ?>"><?= $dif > 0 ? '+' : '' ?><?= $dif ?></strong></div>
        <div class="indicador"><span>Cumplimiento</span><strong><?= cumplimiento($totales['pedidas'], $totales['entregadas']) ?></strong></div>
    </div>

    <div class="acciones">
        <a class="boton" href="exportar.php?formato=detalle&amp;<?= e($consulta) ?>">⬇ CSV detalle</a>
        <a class="boton boton-secundario" href="exportar.php?formato=area&amp;<?= e($consulta) ?>">⬇ CSV por área</a>
        <a class="boton boton-secundario" href="exportar.php?formato=dieta&amp;<?= e($consulta) ?>">⬇ CSV por tipo</a>
    </div>

    <p class="suave">Del <?= e(fechaCorta($f['desde'])) ?> al <?= e(fechaCorta($f['hasta'])) ?></p>
    <?php if (!$porArea): ?>
        <div class="aviso aviso-info">No hay registros de dietas con estos filtros.</div>
    <?php return; endif; ?>

    <h2>Por tiempo de comida</h2>
    <?php tablaResumenDietas('Tiempo', $porTiempo, $totales); ?>
    <h2>Por área</h2>
    <?php tablaResumenDietas('Área', $porArea, $totales); ?>
    <h2>Por tipo de dieta</h2>
    <?php tablaResumenDietas('Tipo de dieta', $porTipo, $totales); ?>

    <h2>Detalle de entregas</h2>
    <?php if ($hayMas): ?>
        <div class="aviso aviso-info">Se muestran las <?= LIMITE_PANTALLA ?> más recientes. El CSV de detalle incluye todas.</div>
    <?php endif; ?>
    <div class="tabla-envoltura">
        <table>
            <thead><tr>
                <th>Fecha</th><th>Tiempo</th><th>Área</th><th class="num">Pedidas</th><th class="num">Entregadas</th>
                <th>Responsable</th><th>Observaciones</th><th>Foto</th><th></th>
            </tr></thead>
            <tbody>
            <?php foreach ($entregas as $d): $dif = (int) $d['entregadas'] - (int) $d['pedidas']; ?>
                <tr>
                    <td><?= e(fechaCorta($d['fecha'])) ?></td>
                    <td><?= e(nombreTiempo($d['tiempo'])) ?></td>
                    <td><?= e($d['area']) ?></td>
                    <td class="num"><?= (int) $d['pedidas'] ?></td>
                    <td class="num <?= $dif < 0 ? 'negativo' : '' ?>"><?= (int) $d['entregadas'] ?></td>
                    <td><?= e($d['responsable']) ?></td>
                    <td class="texto"><?= e($d['observaciones']) ?></td>
                    <td><?php if ($d['foto']): ?><a href="foto.php?id=<?= (int) $d['id'] ?>" target="_blank">Ver</a><?php endif; ?></td>
                    <td><a href="editar.php?id=<?= (int) $d['id'] ?>">Editar</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php });
