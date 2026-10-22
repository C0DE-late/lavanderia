<?php
declare(strict_types=1);

/**
 * Lee los filtros del reporte desde la URL y arma la condición SQL.
 * Lo comparten la pantalla de reporte y la exportación CSV, para que el CSV
 * contenga exactamente lo que se ve en pantalla.
 *
 * @return array{filtros: array, where: string, params: array}
 */
function filtrosReporte(array $get, ?int $soloUsuario = null): array
{
    global $CONFIG;
    $hoy = date('Y-m-d');
    $f = [
        'desde'     => leerFecha($get['desde'] ?? '') ?? date('Y-m-01'),
        'hasta'     => leerFecha($get['hasta'] ?? '') ?? $hoy,
        'area'      => leerEntero($get['area'] ?? '') ?? 0,
        'recorrido' => leerEntero($get['recorrido'] ?? '') ?? 0,
        'tipo'      => isset(TIPOS[$get['tipo'] ?? '']) ? $get['tipo'] : '',
        'usuario'   => leerEntero($get['usuario'] ?? '') ?? 0,
    ];
    if ($f['desde'] > $f['hasta']) {
        [$f['desde'], $f['hasta']] = [$f['hasta'], $f['desde']];
    }
    if (!in_array($f['recorrido'], $CONFIG['recorridos'], true)) {
        $f['recorrido'] = 0;
    }
    if ($soloUsuario !== null) {
        $f['usuario'] = $soloUsuario;
    }

    $where = ['r.fecha BETWEEN ? AND ?'];
    $params = [$f['desde'], $f['hasta']];
    foreach (['area' => 'r.area_id', 'recorrido' => 'r.recorrido', 'usuario' => 'r.usuario_id'] as $clave => $columna) {
        if ($f[$clave]) {
            $where[] = "$columna = ?";
            $params[] = $f[$clave];
        }
    }
    if ($f['tipo']) {
        $where[] = 'r.tipo = ?';
        $params[] = $f['tipo'];
    }
    return ['filtros' => $f, 'where' => implode(' AND ', $where), 'params' => $params];
}

/** Registros detallados según los filtros. */
function consultarDetalle(array $q, ?int $limite = null): array
{
    $sql = "SELECT r.id, r.fecha, r.recorrido, r.tipo, a.nombre AS area, r.peso_kg, r.bolsas,
                   r.foto, r.observaciones, u.nombre AS responsable, r.usuario_id, r.creado_en
            FROM registros r
            JOIN areas a ON a.id = r.area_id
            JOIN usuarios u ON u.id = r.usuario_id
            WHERE {$q['where']}
            ORDER BY r.fecha DESC, r.recorrido DESC, a.orden, a.nombre, r.tipo";
    if ($limite) {
        $sql .= ' LIMIT ' . (int) $limite;
    }
    $st = db()->prepare($sql);
    $st->execute($q['params']);
    return $st->fetchAll();
}

/** Totales por área, separando ropa sucia y limpia. */
function consultarResumenPorArea(array $q): array
{
    $st = db()->prepare(
        "SELECT a.nombre AS area,
                SUM(CASE WHEN r.tipo = 'sucia'  THEN r.peso_kg ELSE 0 END) AS kg_sucia,
                SUM(CASE WHEN r.tipo = 'limpia' THEN r.peso_kg ELSE 0 END) AS kg_limpia,
                SUM(r.peso_kg) AS kg_total,
                SUM(CASE WHEN r.tipo = 'sucia'  THEN r.bolsas ELSE 0 END) AS bolsas_sucia,
                SUM(CASE WHEN r.tipo = 'limpia' THEN r.bolsas ELSE 0 END) AS bolsas_limpia,
                COUNT(*) AS registros
         FROM registros r
         JOIN areas a ON a.id = r.area_id
         WHERE {$q['where']}
         GROUP BY a.id, a.nombre, a.orden
         ORDER BY a.orden, a.nombre"
    );
    $st->execute($q['params']);
    return $st->fetchAll();
}

/** Suma las columnas numéricas del resumen para la fila de totales. */
function totalesResumen(array $resumen): array
{
    $t = ['kg_sucia' => 0, 'kg_limpia' => 0, 'kg_total' => 0, 'bolsas_sucia' => 0, 'bolsas_limpia' => 0, 'registros' => 0];
    foreach ($resumen as $fila) {
        foreach ($t as $k => $_) {
            $t[$k] += (float) $fila[$k];
        }
    }
    return $t;
}

function usuariosConRegistros(): array
{
    return db()->query(
        'SELECT DISTINCT u.id, u.nombre FROM usuarios u JOIN registros r ON r.usuario_id = u.id ORDER BY u.nombre'
    )->fetchAll();
}
