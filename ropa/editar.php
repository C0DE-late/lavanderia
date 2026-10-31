<?php
/** Corrección o eliminación de un registro. */
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/ropa.php';
require __DIR__ . '/../app/permisos.php';
$u = exigirModulo('ropa');

$registro = buscarRegistro(leerEntero($_GET['id'] ?? '') ?? 0);
if (!$registro || !puedeEditarRegistro($registro, $u)) {
    aviso('error', 'El registro no existe o ya no se puede corregir.');
    redirigir('index.php');
}
$esOperario = $u['rol'] === 'operario';
$volver = $esOperario ? 'mis-registros.php' : 'reporte.php';
$hoy = date('Y-m-d');
$fechaMinima = $esOperario ? date('Y-m-d', strtotime('-1 day')) : '2020-01-01';
$errores = [];
$v = [
    'fecha'     => $registro['fecha'],
    'recorrido' => (string) $registro['recorrido'],
    'tipo'      => $registro['tipo'],
    'area'      => (string) $registro['area_id'],
    'peso'      => str_replace('.', ',', rtrim(rtrim($registro['peso_kg'], '0'), '.')),
    'bolsas'    => (string) $registro['bolsas'],
    'obs'       => $registro['observaciones'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();

    // Eliminar: solo supervisor y administrador.
    if (isset($_POST['eliminar'])) {
        if ($esOperario) {
            http_response_code(403);
            exit('Sin permiso.');
        }
        db()->prepare('DELETE FROM registros WHERE id = ?')->execute([$registro['id']]);
        borrarFoto($registro['foto']);
        aviso('exito', 'Registro eliminado.');
        redirigir($volver);
    }

    foreach (array_keys($v) as $k) {
        $v[$k] = leerTexto($_POST[$k] ?? '', $k === 'obs' ? 500 : 20);
    }
    $fecha = leerFecha($v['fecha']);
    $recorrido = leerEntero($v['recorrido']);
    $areaId = leerEntero($v['area']);
    $peso = leerDecimal($v['peso']);
    $bolsas = $v['bolsas'] === '' ? 0 : leerEntero($v['bolsas']);
    $areasValidas = array_map('intval', array_column(todasLasAreas(), 'id'));

    if (!$fecha || $fecha > $hoy || $fecha < $fechaMinima) $errores[] = 'La fecha no es válida.';
    if (!in_array($recorrido, $CONFIG['recorridos'], true)) $errores[] = 'Elige el recorrido.';
    if (!isset(TIPOS[$v['tipo']])) $errores[] = 'Elige si la ropa es sucia o limpia.';
    if (!in_array($areaId, $areasValidas, true)) $errores[] = 'Elige el área.';
    if ($peso === null || $peso <= 0 || $peso > $CONFIG['peso_max_kg']) $errores[] = 'Escribe un peso válido en kg.';
    if ($bolsas === null || $bolsas > $CONFIG['bolsas_max']) $errores[] = 'El número de bolsas no es válido.';

    if (!$errores) {
        $fotoNueva = null;
        try {
            $fotoNueva = guardarFoto($_FILES['foto'] ?? null);
            $quitarFoto = isset($_POST['quitar_foto']);
            $foto = $fotoNueva ?? ($quitarFoto ? null : $registro['foto']);
            db()->prepare(
                'UPDATE registros SET fecha = ?, recorrido = ?, tipo = ?, area_id = ?, peso_kg = ?, bolsas = ?,
                        foto = ?, observaciones = ?, actualizado_por = ? WHERE id = ?'
            )->execute([$fecha, $recorrido, $v['tipo'], $areaId, $peso, $bolsas, $foto, $v['obs'], $u['id'], $registro['id']]);
            if ($foto !== $registro['foto']) {
                borrarFoto($registro['foto']);
            }
            aviso('exito', 'Registro actualizado.');
            redirigir($volver);
        } catch (RuntimeException $ex) {
            $errores[] = $ex->getMessage();
        } catch (PDOException $ex) {
            borrarFoto($fotoNueva);
            $errores[] = $ex->getCode() === '23000'
                ? 'Ya existe un registro de esa área en ese recorrido, fecha y tipo de ropa.'
                : 'No se pudo guardar el cambio.';
        }
    }
}

pagina('Corregir registro', function () use ($registro, $v, $errores, $esOperario, $volver, $hoy, $fechaMinima) {
    global $CONFIG; ?>
    <?php encabezado('Corregir registro'); ?>
    <?php if ($errores): ?>
        <div class="aviso aviso-error" role="alert"><?php foreach ($errores as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="tarjeta">
        <?= campoCsrf() ?>
        <div class="rejilla">
            <div class="campo"><label for="fecha">Fecha</label>
                <input type="date" id="fecha" name="fecha" value="<?= e($v['fecha']) ?>" min="<?= e($fechaMinima) ?>" max="<?= e($hoy) ?>" required></div>
            <div class="campo"><label for="recorrido">Recorrido</label>
                <select id="recorrido" name="recorrido">
                    <?php foreach ($CONFIG['recorridos'] as $r): ?>
                        <option value="<?= $r ?>" <?= $v['recorrido'] === (string) $r ? 'selected' : '' ?>>Recorrido <?= $r ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="campo"><label for="tipo">Ropa</label>
                <select id="tipo" name="tipo">
                    <?php foreach (TIPOS as $clave => $nombre): ?>
                        <option value="<?= $clave ?>" <?= $v['tipo'] === $clave ? 'selected' : '' ?>><?= e($nombre) ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="campo"><label for="area">Área</label>
                <select id="area" name="area">
                    <?php foreach (todasLasAreas() as $a): ?>
                        <option value="<?= (int) $a['id'] ?>" <?= $v['area'] === (string) $a['id'] ? 'selected' : '' ?>><?= e($a['nombre']) ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="campo"><label for="peso">Peso (kg)</label>
                <input id="peso" name="peso" inputmode="decimal" value="<?= e($v['peso']) ?>" required></div>
            <div class="campo"><label for="bolsas">Bolsas</label>
                <input id="bolsas" name="bolsas" inputmode="numeric" value="<?= e($v['bolsas']) ?>"></div>
        </div>
        <div class="campo"><label for="obs">Observaciones o novedades</label>
            <textarea id="obs" name="obs" maxlength="500"><?= e($v['obs']) ?></textarea></div>
        <div class="campo">
            <label for="foto"><?= $registro['foto'] ? 'Reemplazar foto' : 'Agregar foto' ?></label>
            <input type="file" id="foto" name="foto" accept="image/*" capture="environment">
            <?php if ($registro['foto']): ?>
                <img class="miniatura" src="foto.php?id=<?= (int) $registro['id'] ?>" alt="Foto actual del registro">
                <label class="fila" style="font-weight:600;margin-top:8px">
                    <input type="checkbox" name="quitar_foto" style="width:auto;min-height:0"> Quitar la foto actual
                </label>
            <?php endif; ?>
        </div>
        <div class="acciones">
            <button class="boton" type="submit">Guardar cambios</button>
            <a class="boton boton-secundario" href="<?= e($volver) ?>">Cancelar</a>
            <?php if (!$esOperario): ?>
                <button class="boton boton-peligro" type="submit" name="eliminar" value="1" formnovalidate
                        data-confirmar="¿Eliminar este registro? No se puede deshacer.">Eliminar</button>
            <?php endif; ?>
        </div>
    </form>
<?php });
