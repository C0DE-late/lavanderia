<?php
/** Administración de áreas: crear, renombrar, ordenar y activar/desactivar. */
require __DIR__ . '/app/bootstrap.php';
exigirRol('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    $accion = $_POST['accion'] ?? '';
    $nombre = leerTexto($_POST['nombre'] ?? '', 100);
    $orden = leerEntero($_POST['orden'] ?? '') ?? 0;
    $id = leerEntero($_POST['id'] ?? '') ?? 0;

    try {
        if ($accion === 'crear' || $accion === 'guardar') {
            if ($nombre === '') {
                throw new RuntimeException('Escribe el nombre del área.');
            }
            if ($accion === 'crear') {
                db()->prepare('INSERT INTO areas (nombre, orden) VALUES (?, ?)')->execute([$nombre, $orden]);
                aviso('exito', "Área «{$nombre}» creada.");
            } else {
                db()->prepare('UPDATE areas SET nombre = ?, orden = ? WHERE id = ?')->execute([$nombre, $orden, $id]);
                aviso('exito', 'Área actualizada.');
            }
        } elseif ($accion === 'activar' || $accion === 'desactivar') {
            // Las áreas no se borran para conservar el historial; solo se ocultan del formulario.
            db()->prepare('UPDATE areas SET activa = ? WHERE id = ?')->execute([$accion === 'activar' ? 1 : 0, $id]);
            aviso('exito', $accion === 'activar' ? 'Área activada.' : 'Área desactivada. Su historial se conserva.');
        }
    } catch (RuntimeException $ex) {
        aviso('error', $ex->getMessage());
    } catch (PDOException $ex) {
        aviso('error', $ex->getCode() === '23000' ? 'Ya existe un área con ese nombre.' : 'No se pudo guardar.');
    }
    redirigir('areas.php');
}

pagina('Áreas', function () { ?>
    <h1>Áreas</h1>
    <form method="post" class="tarjeta">
        <?= campoCsrf() ?>
        <input type="hidden" name="accion" value="crear">
        <h2 style="margin-top:0">Nueva área</h2>
        <div class="rejilla">
            <div><label for="nombre-nueva">Nombre</label><input id="nombre-nueva" name="nombre" maxlength="100" required></div>
            <div><label for="orden-nueva">Orden en la lista</label><input id="orden-nueva" name="orden" inputmode="numeric" value="100"></div>
        </div>
        <div class="acciones"><button class="boton" type="submit">Crear área</button></div>
    </form>

    <p class="suave">El orden define cómo aparecen las áreas al registrar (de menor a mayor).</p>
    <?php foreach (db()->query('SELECT * FROM areas ORDER BY activa DESC, orden, nombre') as $a): ?>
        <form method="post" class="tarjeta" <?= $a['activa'] ? '' : 'style="opacity:.65"' ?>>
            <?= campoCsrf() ?>
            <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
            <div class="rejilla">
                <div><label for="nombre-<?= (int) $a['id'] ?>">Nombre</label>
                    <input id="nombre-<?= (int) $a['id'] ?>" name="nombre" value="<?= e($a['nombre']) ?>" maxlength="100" required></div>
                <div><label for="orden-<?= (int) $a['id'] ?>">Orden</label>
                    <input id="orden-<?= (int) $a['id'] ?>" name="orden" inputmode="numeric" value="<?= (int) $a['orden'] ?>"></div>
            </div>
            <div class="acciones">
                <button class="boton" name="accion" value="guardar">Guardar</button>
                <?php if ($a['activa']): ?>
                    <button class="boton boton-secundario" name="accion" value="desactivar" formnovalidate>Desactivar</button>
                <?php else: ?>
                    <span class="suave" style="align-self:center">Inactiva</span>
                    <button class="boton boton-secundario" name="accion" value="activar" formnovalidate>Activar</button>
                <?php endif; ?>
            </div>
        </form>
    <?php endforeach; ?>
<?php });
