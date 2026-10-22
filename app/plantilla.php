<?php
declare(strict_types=1);

/**
 * Pinta una página completa con encabezado, menú según el rol y avisos.
 * $contenido es una función que imprime el cuerpo de la página.
 */
function pagina(string $titulo, callable $contenido): void
{
    global $CONFIG;
    $u = usuarioActual();
    $actual = basename($_SERVER['SCRIPT_NAME']);

    $menu = [];
    if ($u) {
        $menu[] = ['registrar.php', 'Registrar'];
        $menu[] = $u['rol'] === 'operario'
            ? ['mis-registros.php', 'Mis registros']
            : ['reporte.php', 'Reporte'];
        if ($u['rol'] === 'admin') {
            $menu[] = ['areas.php', 'Áreas'];
            $menu[] = ['usuarios.php', 'Usuarios'];
        }
        $menu[] = ['clave.php', 'Mi clave'];
    }
    ?>
<!doctype html>
<html lang="es-CO">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0B4F9C">
    <title><?= e($titulo) ?> · <?= e($CONFIG['app']['nombre']) ?></title>
    <link rel="icon" href="assets/icono.svg" type="image/svg+xml">
    <link rel="stylesheet" href="assets/estilos.css?v=1">
</head>
<body>
<header class="encabezado">
    <div class="contenedor fila">
        <a class="marca" href="index.php">
            <img src="assets/icono.svg" alt="" width="34" height="34">
            <span><?= e($CONFIG['app']['nombre']) ?></span>
        </a>
        <?php if ($u): ?>
            <span class="quien"><?= e($u['nombre']) ?> · <?= e(ROLES[$u['rol']]) ?></span>
            <form method="post" action="salir.php" class="salir">
                <?= campoCsrf() ?>
                <button type="submit" class="boton boton-fantasma">Salir</button>
            </form>
        <?php endif; ?>
    </div>
    <?php if ($menu): ?>
        <nav class="contenedor menu" aria-label="Menú principal">
            <?php foreach ($menu as [$url, $texto]): ?>
                <a href="<?= e($url) ?>" <?= $actual === $url ? 'aria-current="page"' : '' ?>><?= e($texto) ?></a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>
</header>
<main class="contenedor">
    <?php foreach (tomarAvisos() as $a): ?>
        <div class="aviso aviso-<?= e($a['tipo']) ?>" role="status"><?= e($a['texto']) ?></div>
    <?php endforeach; ?>
    <?php $contenido(); ?>
</main>
<script src="assets/app.js?v=1" defer></script>
</body>
</html>
<?php
}
