<?php
/** Administración de usuarios: crear, cambiar rol, activar/desactivar y restablecer clave. */
require __DIR__ . '/app/bootstrap.php';
$yo = exigirRol('admin');

/** Clave temporal fácil de dictar (sin letras confusas como l, 1, O, 0). */
function claveTemporal(): string
{
    $alfabeto = 'abcdefghjkmnpqrstuvwxyz23456789';
    $clave = '';
    for ($i = 0; $i < 10; $i++) {
        $clave .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
    }
    return $clave;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    $accion = $_POST['accion'] ?? '';
    $id = leerEntero($_POST['id'] ?? '') ?? 0;
    $rol = $_POST['rol'] ?? '';

    try {
        if ($accion === 'crear') {
            $nombre = leerTexto($_POST['nombre'] ?? '', 120);
            $usuario = mb_strtolower(leerTexto($_POST['usuario'] ?? '', 40));
            if ($nombre === '' || !preg_match('/^[a-z0-9._-]{3,40}$/', $usuario) || !isset(ROLES[$rol])) {
                throw new RuntimeException('Revisa los datos: el usuario debe tener de 3 a 40 letras minúsculas, números, punto o guion.');
            }
            $clave = claveTemporal();
            db()->prepare('INSERT INTO usuarios (nombre, usuario, clave_hash, rol) VALUES (?, ?, ?, ?)')
                ->execute([$nombre, $usuario, password_hash($clave, PASSWORD_DEFAULT), $rol]);
            aviso('exito', "Usuario «{$usuario}» creado. Clave temporal: {$clave} (anótala; no se volverá a mostrar).");
        } elseif ($id === $yo['id'] && $accion !== 'clave') {
            throw new RuntimeException('No puedes cambiar tu propio rol ni desactivarte.');
        } elseif ($accion === 'rol' && isset(ROLES[$rol])) {
            db()->prepare('UPDATE usuarios SET rol = ? WHERE id = ?')->execute([$rol, $id]);
            aviso('exito', 'Rol actualizado.');
        } elseif ($accion === 'activar' || $accion === 'desactivar') {
            db()->prepare('UPDATE usuarios SET activo = ? WHERE id = ?')->execute([$accion === 'activar' ? 1 : 0, $id]);
            aviso('exito', $accion === 'activar' ? 'Usuario activado.' : 'Usuario desactivado. Sus registros se conservan.');
        } elseif ($accion === 'clave') {
            $clave = claveTemporal();
            db()->prepare('UPDATE usuarios SET clave_hash = ? WHERE id = ?')->execute([password_hash($clave, PASSWORD_DEFAULT), $id]);
            aviso('exito', "Nueva clave temporal: {$clave} (anótala; no se volverá a mostrar).");
        }
    } catch (RuntimeException $ex) {
        aviso('error', $ex->getMessage());
    } catch (PDOException $ex) {
        aviso('error', $ex->getCode() === '23000' ? 'Ese nombre de usuario ya existe.' : 'No se pudo guardar.');
    }
    redirigir('usuarios.php');
}

pagina('Usuarios', function () use ($yo) { ?>
    <h1>Usuarios</h1>
    <form method="post" class="tarjeta">
        <?= campoCsrf() ?>
        <input type="hidden" name="accion" value="crear">
        <h2 style="margin-top:0">Nuevo usuario</h2>
        <div class="rejilla">
            <div><label for="nombre">Nombre completo</label><input id="nombre" name="nombre" maxlength="120" required></div>
            <div><label for="usuario">Usuario</label>
                <input id="usuario" name="usuario" maxlength="40" autocapitalize="none" pattern="[a-z0-9._\-]{3,40}" required></div>
            <div><label for="rol">Rol</label>
                <select id="rol" name="rol"><?php foreach (ROLES as $k => $n): ?><option value="<?= $k ?>"><?= e($n) ?></option><?php endforeach; ?></select></div>
        </div>
        <p class="suave">Se genera una clave temporal que la persona puede cambiar en «Mi clave».</p>
        <button class="boton" type="submit">Crear usuario</button>
    </form>

    <div class="tabla-envoltura">
        <table>
            <thead><tr><th>Nombre</th><th>Usuario</th><th>Rol</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach (db()->query('SELECT id, nombre, usuario, rol, activo FROM usuarios ORDER BY activo DESC, nombre') as $p):
                $esYo = (int) $p['id'] === $yo['id']; ?>
                <tr>
                    <td><?= e($p['nombre']) ?><?= $esYo ? ' (tú)' : '' ?></td>
                    <td><?= e($p['usuario']) ?></td>
                    <td>
                        <?php if ($esYo): ?><?= e(ROLES[$p['rol']]) ?><?php else: ?>
                        <form method="post" class="fila">
                            <?= campoCsrf() ?>
                            <input type="hidden" name="accion" value="rol"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                            <select name="rol" aria-label="Rol de <?= e($p['nombre']) ?>" onchange="this.form.submit()">
                                <?php foreach (ROLES as $k => $n): ?><option value="<?= $k ?>" <?= $p['rol'] === $k ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?>
                            </select>
                        </form>
                        <?php endif; ?>
                    </td>
                    <td><?= $p['activo'] ? 'Activo' : 'Inactivo' ?></td>
                    <td>
                        <form method="post" class="fila">
                            <?= campoCsrf() ?>
                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                            <button class="boton boton-fantasma" name="accion" value="clave"
                                    data-confirmar="¿Generar una nueva clave para <?= e($p['nombre']) ?>?">Nueva clave</button>
                            <?php if (!$esYo): ?>
                                <button class="boton boton-fantasma" name="accion" value="<?= $p['activo'] ? 'desactivar' : 'activar' ?>">
                                    <?= $p['activo'] ? 'Desactivar' : 'Activar' ?></button>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php });
