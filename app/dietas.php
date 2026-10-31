<?php
declare(strict_types=1);

/**
 * Consultas del módulo de dietas. Una entrega es lo que llega a un área en un
 * tiempo de comida de un día; su detalle dice cuántas dietas de cada tipo se
 * pidieron y cuántas se entregaron.
 */

function tiposDietaActivos(): array
{
    return db()->query('SELECT id, nombre FROM tipos_dieta WHERE activa = 1 ORDER BY orden, nombre')->fetchAll();
}

function todosLosTiposDieta(): array
{
    return db()->query('SELECT id, nombre, activa FROM tipos_dieta ORDER BY orden, nombre')->fetchAll();
}

function nombreTiempo(string $clave): string
{
    global $CONFIG;
    return $CONFIG['tiempos_comida'][$clave] ?? $clave;
}

/** Entrega con su detalle en ['detalle'][tipo_dieta_id] = ['pedidas' => n, 'entregadas' => n]. */
function buscarEntrega(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM entregas_dieta WHERE id = ?');
    $st->execute([$id]);
    $entrega = $st->fetch();
    if (!$entrega) {
        return null;
    }
    $st = db()->prepare('SELECT tipo_dieta_id, pedidas, entregadas FROM entregas_dieta_detalle WHERE entrega_id = ?');
    $st->execute([$id]);
    $entrega['detalle'] = [];
    foreach ($st as $d) {
        $entrega['detalle'][(int) $d['tipo_dieta_id']] = ['pedidas' => (int) $d['pedidas'], 'entregadas' => (int) $d['entregadas']];
    }
    return $entrega;
}

/** Áreas que ya tienen entrega para una fecha y tiempo de comida. */
function areasConEntrega(string $fecha, string $tiempo): array
{
    $st = db()->prepare('SELECT area_id FROM entregas_dieta WHERE fecha = ? AND tiempo = ?');
    $st->execute([$fecha, $tiempo]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Lee las cantidades del formulario (pedidas[tipo], entregadas[tipo]) y las valida.
 * Devuelve [detalle, errores]; el detalle solo incluye los tipos con alguna cantidad.
 */
function leerCantidadesDieta(array $post, array $tipos): array
{
    global $CONFIG;
    $detalle = [];
    $errores = [];
    foreach ($tipos as $t) {
        $id = (int) $t['id'];
        $pedTexto = trim((string) ($post['pedidas'][$id] ?? ''));
        $entTexto = trim((string) ($post['entregadas'][$id] ?? ''));
        if ($pedTexto === '' && $entTexto === '') {
            continue;
        }
        $pedidas = $pedTexto === '' ? 0 : leerEntero($pedTexto);
        $entregadas = $entTexto === '' ? 0 : leerEntero($entTexto);
        if ($pedidas === null || $entregadas === null || $pedidas > $CONFIG['dietas_max'] || $entregadas > $CONFIG['dietas_max']) {
            $errores[] = "{$t['nombre']}: escribe cantidades enteras de 0 a {$CONFIG['dietas_max']}.";
            continue;
        }
        if ($pedidas === 0 && $entregadas === 0) {
            continue;
        }
        $detalle[$id] = ['pedidas' => $pedidas, 'entregadas' => $entregadas];
    }
    return [$detalle, $errores];
}

function guardarDetalleDieta(int $entregaId, array $detalle): void
{
    $pdo = db();
    $pdo->prepare('DELETE FROM entregas_dieta_detalle WHERE entrega_id = ?')->execute([$entregaId]);
    $ins = $pdo->prepare('INSERT INTO entregas_dieta_detalle (entrega_id, tipo_dieta_id, pedidas, entregadas) VALUES (?, ?, ?, ?)');
    foreach ($detalle as $tipoId => $c) {
        $ins->execute([$entregaId, $tipoId, $c['pedidas'], $c['entregadas']]);
    }
}

/**
 * Filtros del reporte de dietas, compartidos por la pantalla y el CSV.
 *
 * @return array{filtros: array, where: string, params: array}
 */
function filtrosDietas(array $get, ?int $soloUsuario = null): array
{
    global $CONFIG;
    $f = [
        'desde'   => leerFecha($get['desde'] ?? '') ?? date('Y-m-01'),
        'hasta'   => leerFecha($get['hasta'] ?? '') ?? date('Y-m-d'),
        'area'    => leerEntero($get['area'] ?? '') ?? 0,
        'tiempo'  => isset($CONFIG['tiempos_comida'][$get['tiempo'] ?? '']) ? $get['tiempo'] : '',
        'dieta'   => leerEntero($get['dieta'] ?? '') ?? 0,
        'usuario' => leerEntero($get['usuario'] ?? '') ?? 0,
    ];
    if ($f['desde'] > $f['hasta']) {
        [$f['desde'], $f['hasta']] = [$f['hasta'], $f['desde']];
    }
    if ($soloUsuario !== null) {
        $f['usuario'] = $soloUsuario;
    }

    $where = ['e.fecha BETWEEN ? AND ?'];
    $params = [$f['desde'], $f['hasta']];
    foreach (['area' => 'e.area_id', 'dieta' => 'd.tipo_dieta_id', 'usuario' => 'e.usuario_id'] as $clave => $columna) {
        if ($f[$clave]) {
            $where[] = "$columna = ?";
            $params[] = $f[$clave];
        }
    }
    if ($f['tiempo']) {
        $where[] = 'e.tiempo = ?';
        $params[] = $f['tiempo'];
    }
    return ['filtros' => $f, 'where' => implode(' AND ', $where), 'params' => $params];
}

const DIETAS_DESDE = 'FROM entregas_dieta e
    JOIN entregas_dieta_detalle d ON d.entrega_id = e.id
    JOIN tipos_dieta t ON t.id = d.tipo_dieta_id
    JOIN areas a ON a.id = e.area_id
    JOIN usuarios u ON u.id = e.usuario_id';

/** Una fila por entrega, con el total pedido y entregado (del tipo filtrado, si hay filtro). */
function consultarEntregasDieta(array $q, ?int $limite = null): array
{
    $sql = 'SELECT e.id, e.fecha, e.tiempo, e.area_id, a.nombre AS area, e.foto, e.observaciones,
                   u.nombre AS responsable, e.usuario_id,
                   SUM(d.pedidas) AS pedidas, SUM(d.entregadas) AS entregadas
            ' . DIETAS_DESDE . "
            WHERE {$q['where']}
            GROUP BY e.id, e.fecha, e.tiempo, e.area_id, a.nombre, a.orden, e.foto, e.observaciones, u.nombre, e.usuario_id, e.creado_en
            ORDER BY e.fecha DESC, e.creado_en DESC, a.orden, a.nombre";
    if ($limite) {
        $sql .= ' LIMIT ' . (int) $limite;
    }
    $st = db()->prepare($sql);
    $st->execute($q['params']);
    return $st->fetchAll();
}

/** Una fila por entrega y tipo de dieta (para el CSV detallado). */
function consultarDetalleDietas(array $q): array
{
    $st = db()->prepare(
        'SELECT e.fecha, e.tiempo, a.nombre AS area, t.nombre AS dieta, d.pedidas, d.entregadas,
                u.nombre AS responsable, e.observaciones
         ' . DIETAS_DESDE . "
         WHERE {$q['where']}
         ORDER BY e.fecha DESC, e.creado_en DESC, a.orden, a.nombre, t.orden, t.nombre"
    );
    $st->execute($q['params']);
    return $st->fetchAll();
}

/**
 * Totales agrupados por área, tipo de dieta o tiempo de comida.
 * Cada fila trae: nombre, pedidas, entregadas, entregas.
 */
function resumenDietas(array $q, string $por): array
{
    global $CONFIG;
    [$grupo, $nombre, $orden] = match ($por) {
        'area'   => ['a.id, a.nombre, a.orden', 'a.nombre', 'a.orden, a.nombre'],
        'dieta'  => ['t.id, t.nombre, t.orden', 't.nombre', 't.orden, t.nombre'],
        'tiempo' => ['e.tiempo', 'e.tiempo', 'e.tiempo'],
    };
    $st = db()->prepare(
        "SELECT $nombre AS nombre, SUM(d.pedidas) AS pedidas, SUM(d.entregadas) AS entregadas,
                COUNT(DISTINCT e.id) AS entregas
         " . DIETAS_DESDE . "
         WHERE {$q['where']}
         GROUP BY $grupo
         ORDER BY $orden"
    );
    $st->execute($q['params']);
    $filas = $st->fetchAll();
    if ($por === 'tiempo') {
        // Orden del día (desayuno → nocturno) y nombre legible.
        $posicion = array_flip(array_keys($CONFIG['tiempos_comida']));
        usort($filas, fn($x, $y) => ($posicion[$x['nombre']] ?? 99) <=> ($posicion[$y['nombre']] ?? 99));
        foreach ($filas as &$fila) {
            $fila['nombre'] = nombreTiempo($fila['nombre']);
        }
        unset($fila);
    }
    return $filas;
}

function totalesDietas(array $filas): array
{
    $t = ['pedidas' => 0, 'entregadas' => 0];
    foreach ($filas as $f) {
        $t['pedidas'] += (int) $f['pedidas'];
        $t['entregadas'] += (int) $f['entregadas'];
    }
    return $t;
}

/** Porcentaje entregado frente a lo pedido, p. ej. «96 %». */
function cumplimiento(int|string $pedidas, int|string $entregadas): string
{
    return (int) $pedidas > 0 ? round((int) $entregadas * 100 / (int) $pedidas) . ' %' : '—';
}

function usuariosConEntregas(): array
{
    return db()->query(
        'SELECT DISTINCT u.id, u.nombre FROM usuarios u JOIN entregas_dieta e ON e.usuario_id = u.id ORDER BY u.nombre'
    )->fetchAll();
}

/**
 * Tabla del formulario con Pedidas / Entregadas por tipo de dieta.
 * $valores trae los textos escritos en ['pedidas'][id] y ['entregadas'][id].
 */
function camposDietas(array $tipos, array $valores): void
{ ?>
    <div class="tabla-envoltura">
        <table class="tabla-dietas">
            <thead><tr><th>Tipo de dieta</th><th class="num">Pedidas</th><th class="num">Entregadas</th><th class="num">Dif.</th></tr></thead>
            <tbody>
            <?php foreach ($tipos as $t): $id = (int) $t['id']; ?>
                <tr class="dieta-fila">
                    <td class="texto"><?= e($t['nombre']) ?><?= isset($t['activa']) && !$t['activa'] ? ' <span class="suave">(inactiva)</span>' : '' ?></td>
                    <td class="num"><input name="pedidas[<?= $id ?>]" class="pedidas" inputmode="numeric" autocomplete="off"
                               placeholder="0" value="<?= e((string) ($valores['pedidas'][$id] ?? '')) ?>"
                               aria-label="<?= e($t['nombre']) ?>: pedidas"></td>
                    <td class="num">
                        <div class="fila">
                            <input name="entregadas[<?= $id ?>]" class="entregadas" inputmode="numeric" autocomplete="off"
                                   placeholder="0" value="<?= e((string) ($valores['entregadas'][$id] ?? '')) ?>"
                                   aria-label="<?= e($t['nombre']) ?>: entregadas">
                            <button type="button" class="boton boton-fantasma copiar"
                                    aria-label="<?= e($t['nombre']) ?>: entregadas igual a pedidas">=</button>
                        </div>
                    </td>
                    <td class="num diferencia">0</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php }
