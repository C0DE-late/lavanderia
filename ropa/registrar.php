<?php
/**
 * Registro de un recorrido: fecha, recorrido, tipo de ropa y, por cada área,
 * peso en kg, número de bolsas, foto opcional y observaciones.
 */
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/ropa.php';
$u = exigirModulo('ropa');

$hoy = date('Y-m-d');
// El operario puede registrar hoy o ayer (turnos de noche); supervisor y administrador, cualquier fecha pasada.
$fechaMinima = $u['rol'] === 'operario' ? date('Y-m-d', strtotime('-1 day')) : '2020-01-01';

/** Áreas ya registradas para una fecha, recorrido y tipo. */
function areasYaRegistradas(string $fecha, int $recorrido, string $tipo): array
{
    $st = db()->prepare('SELECT area_id FROM registros WHERE fecha = ? AND recorrido = ? AND tipo = ?');
    $st->execute([$fecha, $recorrido, $tipo]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

// Consulta ligera que usa el formulario para marcar las áreas ya registradas.
if (isset($_GET['existentes'])) {
    $f = leerFecha($_GET['fecha'] ?? '');
    $r = leerEntero($_GET['recorrido'] ?? '');
    $t = $_GET['tipo'] ?? '';
    header('Content-Type: application/json');
    echo json_encode(($f && $r && isset(TIPOS[$t])) ? areasYaRegistradas($f, $r, $t) : []);
    exit;
}

$areas = areasActivas();
$errores = [];
$valores = ['fecha' => $hoy, 'recorrido' => '', 'tipo' => 'sucia', 'peso' => [], 'bolsas' => [], 'obs' => []];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    $valores = [
        'fecha'     => leerTexto($_POST['fecha'] ?? '', 10),
        'recorrido' => leerTexto($_POST['recorrido'] ?? '', 2),
        'tipo'      => leerTexto($_POST['tipo'] ?? '', 10),
        'peso'      => is_array($_POST['peso'] ?? null) ? $_POST['peso'] : [],
        'bolsas'    => is_array($_POST['bolsas'] ?? null) ? $_POST['bolsas'] : [],
        'obs'       => is_array($_POST['obs'] ?? null) ? $_POST['obs'] : [],
    ];

    $fecha = leerFecha($valores['fecha']);
    if (!$fecha || $fecha > $hoy || $fecha < $fechaMinima) {
        $errores[] = $u['rol'] === 'operario'
            ? 'La fecha debe ser hoy o ayer.'
            : 'La fecha no es válida o es futura.';
    }
    $recorrido = leerEntero($valores['recorrido']);
    if (!in_array($recorrido, $CONFIG['recorridos'], true)) {
        $errores[] = 'Elige el número de recorrido.';
    }
    $tipo = $valores['tipo'];
    if (!isset(TIPOS[$tipo])) {
        $errores[] = 'Elige si la ropa es sucia o limpia.';
    }

    // Filas con datos: solo las áreas donde se escribió un peso.
    $filas = [];
    foreach ($areas as $a) {
        $id = (int) $a['id'];
        $pesoTexto = trim((string) ($valores['peso'][$id] ?? ''));
        $bolsasTexto = trim((string) ($valores['bolsas'][$id] ?? ''));
        if ($pesoTexto === '' && $bolsasTexto === '') {
            continue;
        }
        $peso = leerDecimal($pesoTexto);
        if ($peso === null || $peso <= 0 || $peso > $CONFIG['peso_max_kg']) {
            $errores[] = "{$a['nombre']}: escribe un peso válido en kg (máximo {$CONFIG['peso_max_kg']}, hasta 2 decimales).";
            continue;
        }
        $bolsas = $bolsasTexto === '' ? 0 : leerEntero($bolsasTexto);
        if ($bolsas === null || $bolsas > $CONFIG['bolsas_max']) {
            $errores[] = "{$a['nombre']}: el número de bolsas no es válido.";
            continue;
        }
        $filas[$id] = [
            'nombre' => $a['nombre'],
            'peso'   => $peso,
            'bolsas' => $bolsas,
            'obs'    => leerTexto($valores['obs'][$id] ?? ''),
        ];
    }
    if (!$errores && !$filas) {
        $errores[] = 'Escribe el peso de al menos un área.';
    }

    // No se permite repetir un área en el mismo recorrido.
    if (!$errores) {
        $repetidas = array_intersect(array_keys($filas), areasYaRegistradas($fecha, $recorrido, $tipo));
        foreach ($repetidas as $id) {
            $errores[] = "{$filas[$id]['nombre']} ya tiene registro en el recorrido $recorrido de ese día. "
                . 'Si hay un error, pide al supervisor que lo corrija.';
        }
    }

    if (!$errores) {
        $fotosGuardadas = [];
        $pdo = db();
        try {
            $pdo->beginTransaction();
            $ins = $pdo->prepare(
                'INSERT INTO registros (fecha, recorrido, tipo, area_id, peso_kg, bolsas, foto, observaciones, usuario_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            foreach ($filas as $id => $f) {
                $foto = guardarFoto(archivoDeArea($id));
                if ($foto) {
                    $fotosGuardadas[] = $foto;
                }
                $ins->execute([$fecha, $recorrido, $tipo, $id, $f['peso'], $f['bolsas'], $foto, $f['obs'], $u['id']]);
            }
            $pdo->commit();
            $total = array_sum(array_column($filas, 'peso'));
            aviso('exito', sprintf(
                'Recorrido %d guardado: %d área(s), %s kg de ropa %s.',
                $recorrido, count($filas), kg($total), mb_strtolower(TIPOS[$tipo])
            ));
            redirigir('registrar.php');
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            array_map('borrarFoto', $fotosGuardadas);
            $errores[] = $ex instanceof RuntimeException
                ? $ex->getMessage()
                : 'No se pudo guardar. Revisa si otra persona ya registró estas áreas e inténtalo de nuevo.';
            error_log('Lavandería registrar: ' . $ex->getMessage());
        }
    }
}

/** Extrae el archivo de foto de un área desde $_FILES['foto'][...][$id]. */
function archivoDeArea(int $id): ?array
{
    $f = $_FILES['foto'] ?? null;
    if (!$f || !isset($f['error'][$id])) {
        return null;
    }
    return [
        'name'     => $f['name'][$id],
        'type'     => $f['type'][$id],
        'tmp_name' => $f['tmp_name'][$id],
        'error'    => $f['error'][$id],
        'size'     => $f['size'][$id],
    ];
}

pagina('Registrar', function () use ($areas, $errores, $valores, $hoy, $fechaMinima) {
    global $CONFIG; ?>
    <?php encabezado('Registrar recorrido', 'Peso y bolsas de ropa de cada área en un recorrido.'); ?>
    <?php if ($errores): ?>
        <div class="aviso aviso-error" role="alert">
            <?php foreach ($errores as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
            <?php if (!empty($_FILES['foto'])): ?><div>Las fotos se deben tomar de nuevo.</div><?php endif; ?>
        </div>
    <?php endif; ?>
    <?php if (!$areas): ?>
        <div class="aviso aviso-info">No hay áreas activas. El administrador debe crearlas en «Áreas».</div>
    <?php return; endif; ?>

    <form method="post" enctype="multipart/form-data" id="form-registro" novalidate>
        <?= campoCsrf() ?>
        <div class="tarjeta">
            <div class="campo">
                <label for="fecha">Fecha</label>
                <input type="date" id="fecha" name="fecha" value="<?= e($valores['fecha']) ?>"
                       min="<?= e($fechaMinima) ?>" max="<?= e($hoy) ?>" required>
            </div>
            <fieldset class="opciones">
                <legend>Recorrido</legend>
                <?php foreach ($CONFIG['recorridos'] as $r): ?>
                    <input type="radio" name="recorrido" id="rec-<?= $r ?>" value="<?= $r ?>"
                        <?= (string) $valores['recorrido'] === (string) $r ? 'checked' : '' ?> required>
                    <label for="rec-<?= $r ?>">Recorrido <?= $r ?></label>
                <?php endforeach; ?>
            </fieldset>
            <fieldset class="opciones">
                <legend>Ropa</legend>
                <?php foreach (TIPOS as $clave => $nombre): ?>
                    <input type="radio" name="tipo" id="tipo-<?= $clave ?>" value="<?= $clave ?>"
                        <?= $valores['tipo'] === $clave ? 'checked' : '' ?>>
                    <label for="tipo-<?= $clave ?>"><?= e($nombre) ?></label>
                <?php endforeach; ?>
            </fieldset>
        </div>

        <h2>Peso por área</h2>
        <p class="suave">Llena solo las áreas que recogiste o entregaste. La foto es opcional.</p>

        <?php foreach ($areas as $a): $id = (int) $a['id']; ?>
            <div class="area" data-area="<?= $id ?>">
                <div class="area-titulo">
                    <span><?= e($a['nombre']) ?></span>
                    <span class="etiqueta oculto">Ya registrada</span>
                </div>
                <div class="area-campos">
                    <div>
                        <label for="peso-<?= $id ?>">Peso (kg)</label>
                        <input id="peso-<?= $id ?>" name="peso[<?= $id ?>]" inputmode="decimal" autocomplete="off"
                               placeholder="0,0" value="<?= e((string) ($valores['peso'][$id] ?? '')) ?>" class="peso">
                    </div>
                    <div>
                        <label for="bolsas-<?= $id ?>">Bolsas</label>
                        <input id="bolsas-<?= $id ?>" name="bolsas[<?= $id ?>]" inputmode="numeric" autocomplete="off"
                               placeholder="0" value="<?= e((string) ($valores['bolsas'][$id] ?? '')) ?>">
                    </div>
                    <span class="boton boton-secundario boton-foto">
                        📷 <span class="texto-foto">Foto</span>
                        <input type="file" name="foto[<?= $id ?>]" accept="image/*" capture="environment"
                               aria-label="Tomar foto de <?= e($a['nombre']) ?>">
                    </span>
                </div>
                <img class="miniatura oculto" alt="Foto de <?= e($a['nombre']) ?>">
                <details <?= !empty($valores['obs'][$id]) ? 'open' : '' ?>>
                    <summary>Observaciones o novedades</summary>
                    <textarea name="obs[<?= $id ?>]" maxlength="500" aria-label="Observaciones de <?= e($a['nombre']) ?>"
                    ><?= e((string) ($valores['obs'][$id] ?? '')) ?></textarea>
                </details>
            </div>
        <?php endforeach; ?>

        <div class="barra-total">
            <div><span class="suave">Total</span><br><strong id="total-kg">0,00 kg</strong></div>
            <button class="boton" type="submit" id="boton-guardar">Guardar recorrido</button>
        </div>
    </form>
<?php });
