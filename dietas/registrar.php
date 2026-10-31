<?php
/**
 * Registro de dietas: fecha, tiempo de comida y área; por cada tipo de dieta,
 * cuántas se pidieron y cuántas se entregaron. Foto y observaciones opcionales.
 */
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/dietas.php';
$u = exigirModulo('dietas');

$hoy = date('Y-m-d');
// Misma regla que la ropa: el operario registra hoy o ayer; supervisor y administrador, cualquier fecha pasada.
$fechaMinima = $u['rol'] === 'operario' ? date('Y-m-d', strtotime('-1 day')) : '2020-01-01';

// Consulta ligera que usa el formulario para marcar las áreas ya registradas.
if (isset($_GET['existentes'])) {
    $f = leerFecha($_GET['fecha'] ?? '');
    $t = $_GET['tiempo'] ?? '';
    header('Content-Type: application/json');
    echo json_encode(($f && isset($CONFIG['tiempos_comida'][$t])) ? areasConEntrega($f, $t) : []);
    exit;
}

$areas = areasDietaActivas();
$tipos = tiposDietaActivos();
$errores = [];
// Después de guardar se conservan la fecha y el tiempo para seguir con la siguiente área.
$valores = [
    'fecha'      => leerFecha($_GET['fecha'] ?? '') ?? $hoy,
    'tiempo'     => isset($CONFIG['tiempos_comida'][$_GET['tiempo'] ?? '']) ? $_GET['tiempo'] : '',
    'area'       => '',
    'pedidas'    => [],
    'entregadas' => [],
    'obs'        => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    $valores = [
        'fecha'      => leerTexto($_POST['fecha'] ?? '', 10),
        'tiempo'     => leerTexto($_POST['tiempo'] ?? '', 20),
        'area'       => leerTexto($_POST['area'] ?? '', 6),
        'pedidas'    => is_array($_POST['pedidas'] ?? null) ? $_POST['pedidas'] : [],
        'entregadas' => is_array($_POST['entregadas'] ?? null) ? $_POST['entregadas'] : [],
        'obs'        => leerTexto($_POST['obs'] ?? ''),
    ];

    $fecha = leerFecha($valores['fecha']);
    if (!$fecha || $fecha > $hoy || $fecha < $fechaMinima) {
        $errores[] = $u['rol'] === 'operario' ? 'La fecha debe ser hoy o ayer.' : 'La fecha no es válida o es futura.';
    }
    $tiempo = $valores['tiempo'];
    if (!isset($CONFIG['tiempos_comida'][$tiempo])) {
        $errores[] = 'Elige el tiempo de comida.';
    }
    $areaId = leerEntero($valores['area']);
    $nombresArea = array_column($areas, 'nombre', 'id');
    if (!$areaId || !isset($nombresArea[$areaId])) {
        $errores[] = 'Elige el área.';
    }

    [$detalle, $erroresCantidades] = leerCantidadesDieta($valores, $tipos);
    $errores = array_merge($errores, $erroresCantidades);
    if (!$erroresCantidades && !$detalle) {
        $errores[] = 'Escribe la cantidad de al menos un tipo de dieta.';
    }
    $t = totalesDietas($detalle);
    if ($detalle && $t['pedidas'] !== $t['entregadas'] && $valores['obs'] === '') {
        $errores[] = 'Lo entregado no coincide con lo pedido: escribe la novedad en «Observaciones».';
    }
    if (!$errores && in_array($areaId, areasConEntrega($fecha, $tiempo), true)) {
        $errores[] = "{$nombresArea[$areaId]} ya tiene registro de " . mb_strtolower(nombreTiempo($tiempo))
            . ' ese día. Si hay un error, corrígelo desde el registro.';
    }

    if (!$errores) {
        $foto = null;
        $pdo = db();
        try {
            $foto = guardarFoto($_FILES['foto'] ?? null);
            $pdo->beginTransaction();
            $pdo->prepare(
                'INSERT INTO entregas_dieta (fecha, tiempo, area_id, foto, observaciones, usuario_id) VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([$fecha, $tiempo, $areaId, $foto, $valores['obs'], $u['id']]);
            guardarDetalleDieta((int) $pdo->lastInsertId(), $detalle);
            $pdo->commit();
            aviso('exito', sprintf(
                '%s · %s guardado: %d pedidas, %d entregadas.',
                $nombresArea[$areaId], nombreTiempo($tiempo), $t['pedidas'], $t['entregadas']
            ));
            redirigir('registrar.php?' . http_build_query(['fecha' => $fecha, 'tiempo' => $tiempo]));
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            borrarFoto($foto);
            $errores[] = $ex instanceof RuntimeException
                ? $ex->getMessage()
                : 'No se pudo guardar. Revisa si otra persona ya registró esta área e inténtalo de nuevo.';
            error_log('Lavandería registrar dieta: ' . $ex->getMessage());
        }
    }
}

pagina('Registrar dietas', function () use ($areas, $tipos, $errores, $valores, $hoy, $fechaMinima) {
    global $CONFIG; ?>
    <?php encabezado('Registrar dietas', 'Cuántas dietas de cada tipo se pidieron y se entregaron en un área.'); ?>
    <?php if ($errores): ?>
        <div class="aviso aviso-error" role="alert">
            <?php foreach ($errores as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
            <?php if (!empty($_FILES['foto']['name'])): ?><div>La foto se debe tomar de nuevo.</div><?php endif; ?>
        </div>
    <?php endif; ?>
    <?php if (!$areas || !$tipos): ?>
        <div class="aviso aviso-info">Faltan áreas o tipos de dieta activos. El administrador debe crearlos.</div>
    <?php return; endif; ?>

    <form method="post" enctype="multipart/form-data" id="form-dieta" novalidate>
        <?= campoCsrf() ?>
        <div class="tarjeta">
            <div class="campo">
                <label for="fecha">Fecha</label>
                <input type="date" id="fecha" name="fecha" value="<?= e($valores['fecha']) ?>"
                       min="<?= e($fechaMinima) ?>" max="<?= e($hoy) ?>" required>
            </div>
            <fieldset class="opciones">
                <legend>Tiempo de comida</legend>
                <?php foreach ($CONFIG['tiempos_comida'] as $clave => $nombre): ?>
                    <input type="radio" name="tiempo" id="tiempo-<?= e($clave) ?>" value="<?= e($clave) ?>"
                        <?= $valores['tiempo'] === $clave ? 'checked' : '' ?> required>
                    <label for="tiempo-<?= e($clave) ?>"><?= e($nombre) ?></label>
                <?php endforeach; ?>
            </fieldset>
            <div class="campo">
                <label for="area">Área</label>
                <select id="area" name="area" required>
                    <option value="">Elige el área…</option>
                    <?php foreach ($areas as $a): ?>
                        <option value="<?= (int) $a['id'] ?>" <?= $valores['area'] === (string) $a['id'] ? 'selected' : '' ?>><?= e($a['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="aviso aviso-info oculto" id="aviso-registrada" style="margin:8px 0 0">
                    Esta área ya tiene registro en este tiempo de comida.
                </div>
            </div>
        </div>

        <h2>Dietas por tipo</h2>
        <p class="suave">Llena solo los tipos que llegaron al área. El botón «=» copia lo pedido en entregadas.</p>
        <?php camposDietas($tipos, $valores); ?>

        <div class="tarjeta" style="margin-top:16px">
            <div class="campo">
                <label for="obs">Observaciones o novedades</label>
                <textarea id="obs" name="obs" maxlength="500"
                          placeholder="Obligatorio si lo entregado no coincide con lo pedido"><?= e($valores['obs']) ?></textarea>
            </div>
            <div class="campo">
                <label for="foto">Foto (opcional)</label>
                <input type="file" id="foto" name="foto" accept="image/*" capture="environment">
            </div>
        </div>

        <div class="barra-total">
            <div><span class="suave">Pedidas / entregadas</span><br><strong id="total-dietas">0 / 0</strong></div>
            <button class="boton" type="submit" id="boton-guardar">Guardar</button>
        </div>
    </form>
<?php });
