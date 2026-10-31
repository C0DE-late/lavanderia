<?php
declare(strict_types=1);

/**
 * Reglas de acceso a un registro de cualquier módulo (usan usuario_id y fecha):
 * - Supervisor y administrador: ver, editar y eliminar cualquiera.
 * - Operario: ver los suyos y corregir los de hoy o ayer; no puede eliminar.
 */
function puedeVerRegistro(array $registro, array $u): bool
{
    return $u['rol'] !== 'operario' || (int) $registro['usuario_id'] === $u['id'];
}

function puedeEditarRegistro(array $registro, array $u): bool
{
    if ($u['rol'] !== 'operario') {
        return true;
    }
    return (int) $registro['usuario_id'] === $u['id']
        && $registro['fecha'] >= date('Y-m-d', strtotime('-1 day'));
}

/** Envía la foto de un registro (de cualquier módulo) si el usuario puede verlo; si no, 404. */
function enviarFoto(?array $registro, array $u): never
{
    global $CONFIG;
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
    exit;
}
