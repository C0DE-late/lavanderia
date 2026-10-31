<?php
/**
 * Entrega la foto de un registro solo a usuarios con permiso para verlo.
 * foto.php?id=N → registro de ropa;  foto.php?dieta=N → entrega de dietas.
 */
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/permisos.php';
$u = exigirRol();

if (isset($_GET['dieta'])) {
    require __DIR__ . '/app/dietas.php';
    $registro = buscarEntrega(leerEntero($_GET['dieta']) ?? 0);
} else {
    $registro = buscarRegistro(leerEntero($_GET['id'] ?? '') ?? 0);
}
$ruta = $registro && $registro['foto'] ? $CONFIG['dir_fotos'] . '/' . $registro['foto'] : null;

if (!$registro || !puedeVerRegistro($registro, $u) || !$ruta || !is_file($ruta)) {
    http_response_code(404);
    exit('Foto no encontrada.');
}
header('Content-Type: image/jpeg');
header('Content-Length: ' . filesize($ruta));
header('Cache-Control: private, max-age=86400');
header('X-Content-Type-Options: nosniff');
readfile($ruta);
