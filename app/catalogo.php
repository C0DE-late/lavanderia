<?php
declare(strict_types=1);

/**
 * Página de administración de un catálogo sencillo (nombre, orden, activo):
 * áreas de lavandería, áreas de dietas y tipos de dieta.
 * Los elementos no se borran para conservar el historial; solo se desactivan.
 *
 * $t trae la tabla y los textos de la página:
 *   tabla, titulo, subtitulo, nuevo, crear, creado (con %s para el nombre),
 *   actualizado, activado, desactivado, repetido, sin_nombre
 */
function paginaCatalogo(array $t): never
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verificarCsrf();
        $accion = $_POST['accion'] ?? '';
        $nombre = leerTexto($_POST['nombre'] ?? '', 100);
        $orden = leerEntero($_POST['orden'] ?? '') ?? 0;
        $id = leerEntero($_POST['id'] ?? '') ?? 0;

        try {
            if ($accion === 'crear' || $accion === 'guardar') {
                if ($nombre === '') {
                    throw new RuntimeException($t['sin_nombre']);
                }
                if ($accion === 'crear') {
                    db()->prepare("INSERT INTO {$t['tabla']} (nombre, orden) VALUES (?, ?)")->execute([$nombre, $orden]);
                    aviso('exito', sprintf($t['creado'], $nombre));
                } else {
                    db()->prepare("UPDATE {$t['tabla']} SET nombre = ?, orden = ? WHERE id = ?")->execute([$nombre, $orden, $id]);
                    aviso('exito', $t['actualizado']);
                }
            } elseif ($accion === 'activar' || $accion === 'desactivar') {
                db()->prepare("UPDATE {$t['tabla']} SET activa = ? WHERE id = ?")->execute([$accion === 'activar' ? 1 : 0, $id]);
                aviso('exito', $accion === 'activar' ? $t['activado'] : $t['desactivado']);
            }
        } catch (RuntimeException $ex) {
            aviso('error', $ex->getMessage());
        } catch (PDOException $ex) {
            aviso('error', $ex->getCode() === '23000' ? $t['repetido'] : 'No se pudo guardar.');
        }
        redirigir(basename($_SERVER['SCRIPT_NAME']));
    }

    pagina($t['titulo'], function () use ($t) { ?>
        <?php encabezado($t['titulo'], $t['subtitulo']); ?>
        <form method="post" class="tarjeta">
            <?= campoCsrf() ?>
            <input type="hidden" name="accion" value="crear">
            <h2><?= e($t['nuevo']) ?></h2>
            <div class="rejilla">
                <div><label for="nombre-nueva">Nombre</label><input id="nombre-nueva" name="nombre" maxlength="100" required></div>
                <div><label for="orden-nueva">Orden en la lista</label><input id="orden-nueva" name="orden" inputmode="numeric" value="100"></div>
            </div>
            <div class="acciones"><button class="boton" type="submit"><?= e($t['crear']) ?></button></div>
        </form>

        <div class="tabla-envoltura">
            <table>
                <thead><tr><th>Nombre</th><th class="num">Orden</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                <?php foreach (db()->query("SELECT * FROM {$t['tabla']} ORDER BY activa DESC, orden, nombre") as $a): $id = (int) $a['id']; ?>
                    <tr <?= $a['activa'] ? '' : 'style="opacity:.6"' ?>>
                        <td><input form="f-<?= $id ?>" name="nombre" value="<?= e($a['nombre']) ?>" maxlength="100" required
                                   aria-label="Nombre"></td>
                        <td class="num"><input form="f-<?= $id ?>" name="orden" inputmode="numeric" value="<?= (int) $a['orden'] ?>"
                                   aria-label="Orden de <?= e($a['nombre']) ?>" style="width:6em"></td>
                        <td><?= $a['activa'] ? 'Activo' : 'Inactivo' ?></td>
                        <td>
                            <form method="post" id="f-<?= $id ?>" class="fila">
                                <?= campoCsrf() ?>
                                <input type="hidden" name="id" value="<?= $id ?>">
                                <button class="boton boton-fantasma" name="accion" value="guardar">Guardar</button>
                                <button class="boton boton-fantasma" name="accion" value="<?= $a['activa'] ? 'desactivar' : 'activar' ?>" formnovalidate>
                                    <?= $a['activa'] ? 'Desactivar' : 'Activar' ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="suave" style="margin-top:12px">El orden define cómo aparecen al registrar (de menor a mayor). Lo inactivo deja de aparecer, pero su historial se conserva.</p>
    <?php });
    exit;
}
