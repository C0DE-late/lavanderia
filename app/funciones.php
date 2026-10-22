<?php
declare(strict_types=1);

/* ---------- Base de datos ---------- */

function db(): PDO
{
    static $pdo = null;
    global $CONFIG;
    if ($pdo === null) {
        $c = $CONFIG['db'];
        $pdo = new PDO(
            "mysql:host={$c['host']};port={$c['puerto']};dbname={$c['nombre']};charset=utf8mb4",
            $c['usuario'],
            $c['clave'],
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
        $pdo->exec("SET time_zone = '-05:00'");
    }
    return $pdo;
}

/* ---------- Utilidades de salida ---------- */

/** Escapa texto para HTML. */
function e(?string $texto): string
{
    return htmlspecialchars($texto ?? '', ENT_QUOTES, 'UTF-8');
}

function redirigir(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/** Mensaje que se muestra una sola vez en la siguiente página. */
function aviso(string $tipo, string $texto): void
{
    $_SESSION['avisos'][] = ['tipo' => $tipo, 'texto' => $texto];
}

function tomarAvisos(): array
{
    $avisos = $_SESSION['avisos'] ?? [];
    unset($_SESSION['avisos']);
    return $avisos;
}

/** Formato colombiano: 1.234,50 */
function kg(float|string|null $valor): string
{
    return number_format((float) $valor, 2, ',', '.');
}

function fechaCorta(string $fechaIso): string
{
    $d = DateTime::createFromFormat('Y-m-d', substr($fechaIso, 0, 10));
    return $d ? $d->format('d/m/Y') : $fechaIso;
}

/* ---------- Autenticación y roles ---------- */

function usuarioActual(): ?array
{
    return $_SESSION['usuario'] ?? null;
}

/** Exige sesión iniciada y, opcionalmente, uno de los roles indicados. */
function exigirRol(string ...$roles): array
{
    $u = usuarioActual();
    if (!$u) {
        redirigir('login.php');
    }
    // Refresca nombre y rol desde la base; si la cuenta fue desactivada, cierra la sesión.
    $st = db()->prepare('SELECT id, nombre, rol FROM usuarios WHERE id = ? AND activo = 1');
    $st->execute([$u['id']]);
    $fila = $st->fetch();
    if (!$fila) {
        $_SESSION = [];
        session_destroy();
        redirigir('login.php');
    }
    $u = $_SESSION['usuario'] = ['id' => (int) $fila['id'], 'nombre' => $fila['nombre'], 'rol' => $fila['rol']];
    if ($roles && !in_array($u['rol'], $roles, true)) {
        http_response_code(403);
        pagina('Sin permiso', function () {
            echo '<div class="tarjeta"><h1>Sin permiso</h1><p>Tu usuario no tiene acceso a esta sección.</p>'
                . '<a class="boton" href="index.php">Volver al inicio</a></div>';
        });
        exit;
    }
    return $u;
}

function esRol(string ...$roles): bool
{
    $u = usuarioActual();
    return $u && in_array($u['rol'], $roles, true);
}

/* ---------- Protección CSRF ---------- */

function tokenCsrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function campoCsrf(): string
{
    return '<input type="hidden" name="csrf" value="' . e(tokenCsrf()) . '">';
}

/** Detiene la petición si el formulario no trae el token de la sesión. */
function verificarCsrf(): void
{
    $enviado = $_POST['csrf'] ?? '';
    if (!is_string($enviado) || !hash_equals(tokenCsrf(), $enviado)) {
        http_response_code(400);
        exit('La sesión expiró o el formulario no es válido. Vuelve atrás y recarga la página.');
    }
}

/* ---------- Validación de datos ---------- */

/** Convierte "12,5" o "12.5" en número; devuelve null si no es válido. */
function leerDecimal(mixed $valor): ?float
{
    if (!is_string($valor)) {
        return null;
    }
    $valor = trim(str_replace(',', '.', $valor));
    if ($valor === '' || !preg_match('/^\d+(\.\d{1,2})?$/', $valor)) {
        return null;
    }
    return (float) $valor;
}

function leerEntero(mixed $valor): ?int
{
    if (!is_string($valor) || !preg_match('/^\d{1,6}$/', trim($valor))) {
        return null;
    }
    return (int) trim($valor);
}

function leerFecha(mixed $valor): ?string
{
    if (!is_string($valor)) {
        return null;
    }
    $d = DateTime::createFromFormat('!Y-m-d', $valor);
    return ($d && $d->format('Y-m-d') === $valor) ? $valor : null;
}

/** Texto libre recortado a un largo máximo. */
function leerTexto(mixed $valor, int $max = 500): string
{
    return is_string($valor) ? mb_substr(trim($valor), 0, $max) : '';
}

/* ---------- Fotos ---------- */

/**
 * Valida una imagen subida, la reduce y la guarda como JPEG sin metadatos.
 * Devuelve el nombre del archivo guardado, null si no se subió nada,
 * o lanza RuntimeException con un mensaje para el usuario.
 */
function guardarFoto(?array $archivo): ?string
{
    global $CONFIG;
    if (!$archivo || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($archivo['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($archivo['tmp_name'])) {
        throw new RuntimeException('No se pudo recibir la foto. Intenta tomarla de nuevo.');
    }
    $tipo = (new finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name']);
    $origen = match ($tipo) {
        'image/jpeg' => @imagecreatefromjpeg($archivo['tmp_name']),
        'image/png'  => @imagecreatefrompng($archivo['tmp_name']),
        'image/webp' => @imagecreatefromwebp($archivo['tmp_name']),
        default      => false,
    };
    if (!$origen) {
        throw new RuntimeException('La foto debe ser una imagen JPG, PNG o WEBP.');
    }

    // Reduce la imagen para que ocupe poco espacio.
    $ancho = imagesx($origen);
    $alto = imagesy($origen);
    $escala = min(1, $CONFIG['foto_lado_max'] / max($ancho, $alto));
    $nuevoAncho = max(1, (int) round($ancho * $escala));
    $nuevoAlto = max(1, (int) round($alto * $escala));
    $destino = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
    imagefill($destino, 0, 0, imagecolorallocate($destino, 255, 255, 255));
    imagecopyresampled($destino, $origen, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);

    // Carpeta por mes: almacen/fotos/2026-10/
    $subcarpeta = date('Y-m');
    $carpeta = $CONFIG['dir_fotos'] . '/' . $subcarpeta;
    if (!is_dir($carpeta) && !mkdir($carpeta, 0775, true)) {
        throw new RuntimeException('No se pudo crear la carpeta de fotos en el servidor.');
    }
    $nombre = $subcarpeta . '/' . bin2hex(random_bytes(12)) . '.jpg';
    if (!imagejpeg($destino, $CONFIG['dir_fotos'] . '/' . $nombre, 78)) {
        throw new RuntimeException('No se pudo guardar la foto en el servidor.');
    }
    return $nombre;
}

function borrarFoto(?string $nombre): void
{
    global $CONFIG;
    if ($nombre && preg_match('#^\d{4}-\d{2}/[a-f0-9]{24}\.jpg$#', $nombre)) {
        @unlink($CONFIG['dir_fotos'] . '/' . $nombre);
    }
}

/* ---------- Catálogos ---------- */

function areasActivas(): array
{
    return db()->query('SELECT id, nombre FROM areas WHERE activa = 1 ORDER BY orden, nombre')->fetchAll();
}

function todasLasAreas(): array
{
    return db()->query('SELECT id, nombre, activa FROM areas ORDER BY orden, nombre')->fetchAll();
}
