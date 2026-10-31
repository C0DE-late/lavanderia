<?php
/** Corrección o eliminación de una entrega de dietas. */
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/permisos.php';
require __DIR__ . '/app/dietas.php';
$u = exigirRol();

// Las reglas de permisos de la ropa sirven igual: usan usuario_id y fecha.
$entrega = buscarEntrega(leerEntero($_GET['id'] ?? '') ?? 0);
if (!$entrega || !puedeEditarRegistro($entrega, $u)) {
    aviso('error', 'El registro no existe o ya no se puede corregir.');
    redirigir('index.php');
}
$esOperario = $u['rol'] === 'operario';
$volver = $esOperario ? 'mis-registros.php' : 'reporte-dietas.php';
$hoy = date('Y-m-d');
$fechaMinima = $esOperario ? date('Y-m-d', strtotime('-1 day')) : '2020-01-01';

// Tipos activos más los inactivos que ya estaban en esta entrega.
$tipos = array_values(array_filter(
    todosLosTiposDieta(),
    fn($t) => $t['activa'] || isset($entrega['detalle'][(int) $t['id']])
));
$errores = [];
$v = [
    'fecha'      => $entrega['fecha'],
    'tiempo'     => $entrega['tiempo'],
    'area'       => (string) $entrega['area_id'],
    'pedidas'    => array_map(fn($c) => (string) $c['pedidas'], $entrega['detalle']),
    'entregadas' => array_map(fn($c) => (string) $c['entregadas'], $entrega['detalle']),
    'obs'        => $entrega['observaciones'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();

    // Eliminar: solo supervisor y administrador.
    if (isset($_POST['eliminar'])) {
        if ($esOperario) {
            http_response_code(403);
            exit('Sin permiso.');
        }
        db()->prepare('DELETE FROM entregas_dieta WHERE id = ?')->execute([$entrega['id']]);
        borrarFoto($entrega['foto']);
        aviso('exito', 'Registro de dietas eliminado.');
        redirigir($volver);
    }

    $v = [
        'fecha'      => leerTexto($_POST['fecha'] ?? '', 10),
        'tiempo'     => leerTexto($_POST['tiempo'] ?? '', 20),
        'area'       => leerTexto($_POST['area'] ?? '', 6),
        'pedidas'    => is_array($_POST['pedidas'] ?? null) ? $_POST['pedidas'] : [],
        'entregadas' => is_array($_POST['entregadas'] ?? null) ? $_POST['entregadas'] : [],
        'obs'        => leerTexto($_POST['obs'] ?? ''),
    ];
    $fecha = leerFecha($v['fecha']);
    $areaId = leerEntero($v['area']);
    $areasValidas = array_map('intval', array_column(todasLasAreas(), 'id'));

    if (!$fecha || $fecha > $hoy || $fecha < $fechaMinima) $errores[] = 'La fecha no es válida.';
    if (!isset($CONFIG['tiempos_comida'][$v['tiempo']])) $errores[] = 'Elige el tiempo de comida.';
    if (!in_array($areaId, $areasValidas, true)) $errores[] = 'Elige el área.';
    [$detalle, $erroresCantidades] = leerCantidadesDieta($v, $tipos);
    $errores = array_merge($errores, $erroresCantidades);
    if (!$erroresCantidades && !$detalle) $errores[] = 'Escribe la cantidad de al menos un tipo de dieta.';
    $t = totalesDietas($detalle);
    if ($detalle && $t['pedidas'] !== $t['entregadas'] && $v['obs'] === '') {
        $errores[] = 'Lo entregado no coincide con lo pedido: escribe la novedad en «Observaciones».';
    }

    if (!$errores) {
        $fotoNueva = null;
        $pdo = db();
        try {
            $fotoNueva = guardarFoto($_FILES['foto'] ?? null);
            $foto = $fotoNueva ?? (isset($_POST['quitar_foto']) ? null : $entrega['foto']);
            $pdo->beginTransaction();
            $pdo->prepare(
                'UPDATE entregas_dieta SET fecha = ?, tiempo = ?, area_id = ?, foto = ?, observaciones = ?, actualizado_por = ?
                 WHERE id = ?'
            )->execute([$fecha, $v['tiempo'], $areaId, $foto, $v['obs'], $u['id'], $entrega['id']]);
            guardarDetalleDieta((int) $entrega['id'], $detalle);
            $pdo->commit();
            if ($foto !== $entrega['foto']) {
                borrarFoto($entrega['foto']);
            }
            aviso('exito', 'Registro de dietas actualizado.');
            redirigir($volver);
        } catch (RuntimeException $ex) {
            $errores[] = $ex->getMessage();
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            borrarFoto($fotoNueva);
            $errores[] = $ex->getCode() === '23000'
                ? 'Ya existe un registro de esa área en ese tiempo de comida y fecha.'
                : 'No se pudo guardar el cambio.';
        }
    }
}

pagina('Corregir dietas', function () use ($entrega, $tipos, $v, $errores, $esOperario, $volver, $hoy, $fechaMinima) {
    global $CONFIG; ?>
    <h1>Corregir dietas</h1>
    <?php if ($errores): ?>
        <div class="aviso aviso-error" role="alert"><?php foreach ($errores as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data" id="form-dieta">
        <?= campoCsrf() ?>
        <div class="tarjeta">
            <div class="rejilla">
                <div class="campo"><label for="fecha">Fecha</label>
                    <input type="date" id="fecha" name="fecha" value="<?= e($v['fecha']) ?>" min="<?= e($fechaMinima) ?>" max="<?= e($hoy) ?>" required></div>
                <div class="campo"><label for="tiempo">Tiempo de comida</label>
                    <select id="tiempo" name="tiempo">
                        <?php foreach ($CONFIG['tiempos_comida'] as $clave => $nombre): ?>
                            <option value="<?= e($clave) ?>" <?= $v['tiempo'] === $clave ? 'selected' : '' ?>><?= e($nombre) ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="campo"><label for="area">Área</label>
                    <select id="area" name="area">
                        <?php foreach (todasLasAreas() as $a): ?>
                            <option value="<?= (int) $a['id'] ?>" <?= $v['area'] === (string) $a['id'] ? 'selected' : '' ?>><?= e($a['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select></div>
            </div>
        </div>

        <?php camposDietas($tipos, $v); ?>

        <div class="tarjeta" style="margin-top:16px">
            <div class="campo"><label for="obs">Observaciones o novedades</label>
                <textarea id="obs" name="obs" maxlength="500"><?= e($v['obs']) ?></textarea></div>
            <div class="campo">
                <label for="foto"><?= $entrega['foto'] ? 'Reemplazar foto' : 'Agregar foto' ?></label>
                <input type="file" id="foto" name="foto" accept="image/*" capture="environment">
                <?php if ($entrega['foto']): ?>
                    <img class="miniatura" src="foto.php?dieta=<?= (int) $entrega['id'] ?>" alt="Foto actual del registro">
                    <label class="fila" style="font-weight:600;margin-top:8px">
                        <input type="checkbox" name="quitar_foto" style="width:auto;min-height:0"> Quitar la foto actual
                    </label>
                <?php endif; ?>
            </div>
        </div>

        <div class="barra-total">
            <div><span class="suave">Pedidas / entregadas</span><br><strong id="total-dietas">0 / 0</strong></div>
            <div class="acciones" style="margin:0">
                <a class="boton boton-secundario" href="<?= e($volver) ?>">Cancelar</a>
                <?php if (!$esOperario): ?>
                    <button class="boton boton-peligro" type="submit" name="eliminar" value="1" formnovalidate
                            data-confirmar="¿Eliminar este registro de dietas? No se puede deshacer.">Eliminar</button>
                <?php endif; ?>
                <button class="boton" type="submit" id="boton-guardar">Guardar</button>
            </div>
        </div>
    </form>
<?php });
