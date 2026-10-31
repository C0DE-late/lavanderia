<?php
/**
 * Exporta el reporte a CSV para abrir o importar en Excel.
 * - Separador punto y coma y coma decimal (configuración regional de Colombia).
 * - Codificación UTF-8 con BOM para que Excel muestre bien las tildes.
 *
 * formato=detalle  → un renglón por registro.
 * formato=resumen  → peso total por área y fila de totales.
 */
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/ropa.php';
exigirModulo('ropa', 'supervisor', 'admin');

$q = filtrosReporte($_GET);
$f = $q['filtros'];
$formato = ($_GET['formato'] ?? '') === 'resumen' ? 'resumen' : 'detalle';

/** Evita que Excel interprete como fórmula un texto que empieza por = + - @. */
function celdaSegura(string $texto): string
{
    $texto = str_replace(["\r\n", "\r", "\n"], ' ', $texto);
    return preg_match('/^[=+\-@\t]/', $texto) ? "'" . $texto : $texto;
}

function decimal(float|string $n): string
{
    return number_format((float) $n, 2, ',', '');
}

$nombre = "lavanderia-{$formato}-{$f['desde']}_a_{$f['hasta']}.csv";
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $nombre . '"');
header('Cache-Control: no-store');

$salida = fopen('php://output', 'w');
fwrite($salida, "\xEF\xBB\xBF");
$fila = fn(array $valores) => fputcsv($salida, $valores, ';', '"', '');

if ($formato === 'detalle') {
    $fila(['Fecha', 'Recorrido', 'Ropa', 'Área', 'Peso (kg)', 'Bolsas', 'Responsable', 'Observaciones', 'Foto']);
    foreach (consultarDetalle($q) as $d) {
        $fila([
            fechaCorta($d['fecha']),
            $d['recorrido'],
            TIPOS[$d['tipo']],
            celdaSegura($d['area']),
            decimal($d['peso_kg']),
            $d['bolsas'],
            celdaSegura($d['responsable']),
            celdaSegura($d['observaciones']),
            $d['foto'] ? 'Sí' : 'No',
        ]);
    }
} else {
    $resumen = consultarResumenPorArea($q);
    $t = totalesResumen($resumen);
    $fila(['Área', 'Kg ropa sucia', 'Kg ropa limpia', 'Kg total', 'Bolsas sucia', 'Bolsas limpia', 'Registros', 'Desde', 'Hasta']);
    foreach ($resumen as $r) {
        $fila([
            celdaSegura($r['area']), decimal($r['kg_sucia']), decimal($r['kg_limpia']), decimal($r['kg_total']),
            $r['bolsas_sucia'], $r['bolsas_limpia'], $r['registros'], fechaCorta($f['desde']), fechaCorta($f['hasta']),
        ]);
    }
    $fila([
        'TOTAL', decimal($t['kg_sucia']), decimal($t['kg_limpia']), decimal($t['kg_total']),
        (int) $t['bolsas_sucia'], (int) $t['bolsas_limpia'], (int) $t['registros'], fechaCorta($f['desde']), fechaCorta($f['hasta']),
    ]);
}
fclose($salida);
