<?php
/** Administración de usuarios: crear, cambiar rol, activar/desactivar y restablecer clave. */
require __DIR__ . '/app/bootstrap.php';
$yo = exigirRol('admin');

/** Módulos marcados en el formulario, como texto para la columna SET («ropa,dietas»). */
function leerModulos(): string
{
    $marcados = is_array($_POST['modulos'] ?? null) ? $_POST['modulos'] : [];
    $modulos = array_values(array_intersect(array_keys(MODULOS), $marcados));
    if (!$modulos) {
        throw new RuntimeException('Marca al menos un módulo para el usuario.');
    }
    return implode(',', $modulos);
}

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
            $modulos = leerModulos();
            $clave = claveTemporal();
            db()->prepare('INSERT INTO usuarios (nombre, usuario, clave_hash, rol, modulos) VALUES (?, ?, ?, ?, ?)')
                ->execute([$nombre, $usuario, password_hash($clave, PASSWORD_DEFAULT), $rol, $modulos]);
            aviso('exito', "Usuario «{$usuario}» creado. Clave temporal: {$clave} (anótala; no se volverá a mostrar).");
        } elseif ($id === $yo['id'] && $accion !== 'clave') {
            throw new RuntimeException('No puedes cambiar tu propio rol ni desactivarte.');
        } elseif ($accion === 'rol' && isset(ROLES[$rol])) {
            db()->prepare('UPDATE usuarios SET rol = ? WHERE id = ?')->execute([$rol, $id]);
            aviso('exito', 'Rol actualizado.');
        } elseif ($accion === 'modulos') {
            db()->prepare('UPDATE usuarios SET modulos = ? WHERE id = ?')->execute([leerModulos(), $id]);
            aviso('exito', 'Módulos actualizados.');
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
    <?php encabezado('Usuarios', 'Crea usuarios, asigna su rol y los módulos a los que entran.'); ?>
    <form method="post" class="tarjeta">
        <?= campoCsrf() ?>
        <input type="hidden" name="accion" value="crear">
        <h2>Nuevo usuario</h2>
        <div class="rejilla">
            <div><label for="nombre">Nombre completo</label><input id="nombre" name="nombre" maxlength="120" required></div>
            <div><label for="usuario">Usuario</label>
                <input id="usuario" name="usuario" maxlength="40" autocapitalize="none" pattern="[a-z0-9._\-]{3,40}" required></div>
            <div><label for="rol">Rol</label>
                <select id="rol" name="rol"><?php foreach (ROLES as $k => $n): ?><option value="<?= $k ?>"><?= e($n) ?></option><?php endforeach; ?></select></div>
        </div>
        <fieldset class="opciones" style="margin-top:14px">
            <legend>Módulos a los que entra</legend>
            <?php foreach (MODULOS as $clave => $m): ?>
                <input type="checkbox" name="modulos[]" id="nuevo-<?= $clave ?>" value="<?= $clave ?>" checked>
                <label for="nuevo-<?= $clave ?>"><?= e($m['nombre']) ?></label>
            <?php endforeach; ?>
        </fieldset>
        <p class="suave">El administrador entra a todos los módulos. Se genera una clave temporal que la persona puede cambiar en «Mi clave».</p>
        <button class="boton" type="submit">Crear usuario</button>
    </form>

    <div class="tabla-envoltura">
        <table>
            <thead><tr><th>Nombre</th><th>Usuario</th><th>Rol</th><th>Módulos</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach (db()->query('SELECT id, nombre, usuario, rol, modulos, activo FROM usuarios ORDER BY activo DESC, nombre') as $p):
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
                    <td>
                        <?php if ($p['rol'] === 'admin'): ?><span class="suave">Todos</span><?php else:
                            $suyos = explode(',', $p['modulos']); ?>
                        <form method="post" class="fila">
                            <?= campoCsrf() ?>
                            <input type="hidden" name="accion" value="modulos"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                            <?php foreach (MODULOS as $clave => $m): ?>
                                <label class="fila" style="margin:0;font-weight:600;gap:6px">
                                    <input type="checkbox" name="modulos[]" value="<?= $clave ?>" style="width:auto;min-height:0"
                                        <?= in_array($clave, $suyos, true) ? 'checked' : '' ?> onchange="this.form.submit()"> <?= e($m['nombre']) ?>
                                </label>
                            <?php endforeach; ?>
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
