<?php
declare(strict_types=1);

/**
 * Reglas de acceso a un registro:
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

function buscarRegistro(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM registros WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}
