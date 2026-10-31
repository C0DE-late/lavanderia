<?php
/**
 * Exporta el reporte de dietas a CSV para Excel (punto y coma, UTF-8 con BOM).
 *
 * formato=detalle → un renglón por entrega y tipo de dieta.
 * formato=area    → totales por área.
 * formato=dieta   → totales por tipo de dieta.
 */
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/dietas.php';
exigirModulo('dietas', 'supervisor', 'admin');

$q = filtrosDietas($_GET);
$f = $q['filtros'];
$formato = in_array($_GET['formato'] ?? '', ['area', 'dieta'], true) ? $_GET['formato'] : 'detalle';

/** Evita que Excel interprete como fórmula un texto que empieza por = + - @. */
function celdaSegura(string $texto): string
{
    $texto = str_replace(["\r\n", "\r", "\n"], ' ', $texto);
    return preg_match('/^[=+\-@\t]/', $texto) ? "'" . $texto : $texto;
}

$nombre = "dietas-{$formato}-{$f['desde']}_a_{$f['hasta']}.csv";
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $nombre . '"');
header('Cache-Control: no-store');

$salida = fopen('php://output', 'w');
fwrite($salida, "\xEF\xBB\xBF");
$fila = fn(array $valores) => fputcsv($salida, $valores, ';', '"', '');

if ($formato === 'detalle') {
    $fila(['Fecha', 'Tiempo de comida', 'Área', 'Tipo de dieta', 'Pedidas', 'Entregadas', 'Diferencia', 'Responsable', 'Observaciones']);
    foreach (consultarDetalleDietas($q) as $d) {
        $fila([
            fechaCorta($d['fecha']),
            nombreTiempo($d['tiempo']),
            celdaSegura($d['area']),
            celdaSegura($d['dieta']),
            $d['pedidas'],
            $d['entregadas'],
            (int) $d['entregadas'] - (int) $d['pedidas'],
            celdaSegura($d['responsable']),
            celdaSegura($d['observaciones']),
        ]);
    }
} else {
    $resumen = resumenDietas($q, $formato);
    $t = totalesDietas($resumen);
    $fila([$formato === 'area' ? 'Área' : 'Tipo de dieta', 'Pedidas', 'Entregadas', 'Diferencia', 'Cumplimiento', 'Entregas', 'Desde', 'Hasta']);
    foreach ($resumen as $r) {
        $fila([
            celdaSegura($r['nombre']), $r['pedidas'], $r['entregadas'], (int) $r['entregadas'] - (int) $r['pedidas'],
            cumplimiento($r['pedidas'], $r['entregadas']), $r['entregas'], fechaCorta($f['desde']), fechaCorta($f['hasta']),
        ]);
    }
    $fila([
        'TOTAL', $t['pedidas'], $t['entregadas'], $t['entregadas'] - $t['pedidas'],
        cumplimiento($t['pedidas'], $t['entregadas']), '', fechaCorta($f['desde']), fechaCorta($f['hasta']),
    ]);
}
fclose($salida);
