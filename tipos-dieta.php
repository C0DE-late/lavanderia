<?php
/** Administración de los tipos de dieta: crear, renombrar, ordenar y activar/desactivar. */
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
                throw new RuntimeException('Escribe el nombre del tipo de dieta.');
            }
            if ($accion === 'crear') {
                db()->prepare('INSERT INTO tipos_dieta (nombre, orden) VALUES (?, ?)')->execute([$nombre, $orden]);
                aviso('exito', "Tipo de dieta «{$nombre}» creado.");
            } else {
                db()->prepare('UPDATE tipos_dieta SET nombre = ?, orden = ? WHERE id = ?')->execute([$nombre, $orden, $id]);
                aviso('exito', 'Tipo de dieta actualizado.');
            }
        } elseif ($accion === 'activar' || $accion === 'desactivar') {
            // Los tipos no se borran para conservar el historial; solo se ocultan del formulario.
            db()->prepare('UPDATE tipos_dieta SET activa = ? WHERE id = ?')->execute([$accion === 'activar' ? 1 : 0, $id]);
            aviso('exito', $accion === 'activar' ? 'Tipo de dieta activado.' : 'Tipo de dieta desactivado. Su historial se conserva.');
        }
    } catch (RuntimeException $ex) {
        aviso('error', $ex->getMessage());
    } catch (PDOException $ex) {
        aviso('error', $ex->getCode() === '23000' ? 'Ya existe un tipo de dieta con ese nombre.' : 'No se pudo guardar.');
    }
    redirigir('tipos-dieta.php');
}

pagina('Tipos de dieta', function () { ?>
    <h1>Tipos de dieta</h1>
    <form method="post" class="tarjeta">
        <?= campoCsrf() ?>
        <input type="hidden" name="accion" value="crear">
        <h2 style="margin-top:0">Nuevo tipo de dieta</h2>
        <div class="rejilla">
            <div><label for="nombre-nueva">Nombre</label><input id="nombre-nueva" name="nombre" maxlength="100" required></div>
            <div><label for="orden-nueva">Orden en la lista</label><input id="orden-nueva" name="orden" inputmode="numeric" value="100"></div>
        </div>
        <div class="acciones"><button class="boton" type="submit">Crear tipo</button></div>
    </form>

    <p class="suave">El orden define cómo aparecen los tipos al registrar dietas (de menor a mayor).</p>
    <?php foreach (db()->query('SELECT * FROM tipos_dieta ORDER BY activa DESC, orden, nombre') as $a): ?>
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
                    <span class="suave" style="align-self:center">Inactivo</span>
                    <button class="boton boton-secundario" name="accion" value="activar" formnovalidate>Activar</button>
                <?php endif; ?>
            </div>
        </form>
    <?php endforeach; ?>
<?php });
