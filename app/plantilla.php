<?php
declare(strict_types=1);

/** Iconos de línea (24×24) usados en el menú y los paneles. */
function icono(string $nombre): string
{
    $trazos = [
        'panel'     => '<path d="M3 13h8V3H3zM13 21h8V11h-8zM3 21h8v-6H3zM13 3v6h8V3z"/>',
        'registrar' => '<circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/>',
        'registros' => '<path d="M8 6h13M8 12h13M8 18h13"/><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/>',
        'reporte'   => '<path d="M3 3v18h18"/><path d="M7 15l4-4 3 3 5-6"/>',
        'areas'     => '<path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>',
        'tipos'     => '<path d="M3 12V3h9l9 9-9 9z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
        'usuarios'  => '<circle cx="9" cy="8" r="4"/><path d="M2 21a7 7 0 0 1 14 0"/><path d="M16 4a4 4 0 0 1 0 8M22 21a7 7 0 0 0-4-6.3"/>',
        'clave'     => '<circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M17 6l3 3"/>',
        'modulos'   => '<rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/>',
        'salir'     => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/>',
        'menu'      => '<path d="M4 6h16M4 12h16M4 18h16"/>',
    ];
    return '<svg class="icono" viewBox="0 0 24 24" aria-hidden="true">' . ($trazos[$nombre] ?? '') . '</svg>';
}

/** Módulo de la página actual según su carpeta (ropa/, dietas/), o null en la raíz. */
function moduloActual(): ?string
{
    $carpeta = basename(dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    return isset(MODULOS[$carpeta]) ? $carpeta : null;
}

/** Opciones del menú: [ruta desde la raíz de la app, texto, icono]. */
function menuDe(?string $modulo, array $u): array
{
    $supervisa = $u['rol'] !== 'operario';
    $admin = $u['rol'] === 'admin';
    $menu = match ($modulo) {
        'ropa' => [
            ['ropa/index.php', 'Panel', 'panel'],
            ['ropa/registrar.php', 'Registrar', 'registrar'],
            ['ropa/mis-registros.php', 'Mis registros', 'registros'],
            $supervisa ? ['ropa/reporte.php', 'Reporte', 'reporte'] : null,
            $admin ? ['ropa/areas.php', 'Áreas', 'areas'] : null,
        ],
        'dietas' => [
            ['dietas/index.php', 'Panel', 'panel'],
            ['dietas/registrar.php', 'Registrar', 'registrar'],
            ['dietas/mis-registros.php', 'Mis registros', 'registros'],
            $supervisa ? ['dietas/reporte.php', 'Reporte', 'reporte'] : null,
            $admin ? ['dietas/areas.php', 'Áreas', 'areas'] : null,
            $admin ? ['dietas/tipos.php', 'Tipos de dieta', 'tipos'] : null,
        ],
        default => [['index.php', 'Módulos', 'modulos']],
    };
    return array_values(array_filter($menu));
}

/**
 * Pinta una página completa: menú lateral (en el celular, menú desplegable y
 * barra inferior de accesos rápidos), avisos y contenido.
 * $contenido es una función que imprime el cuerpo de la página.
 */
function pagina(string $titulo, callable $contenido): void
{
    global $CONFIG;
    $u = usuarioActual();
    $modulo = moduloActual();
    $actual = ($modulo ? "$modulo/" : '') . basename($_SERVER['SCRIPT_NAME']);
    $nombre = $modulo ? MODULOS[$modulo]['nombre'] : $CONFIG['app']['nombre'];
    $iconoApp = $modulo ? MODULOS[$modulo]['icono'] : 'assets/icono.svg';
    $menu = $u ? menuDe($modulo, $u) : [];
    $otros = $u ? array_diff($u['modulos'], [$modulo]) : [];
    $enlace = function (array $item) use ($actual): string {
        [$ruta, $texto, $ico] = $item;
        return '<a href="' . e(url($ruta)) . '"' . ($actual === $ruta ? ' aria-current="page"' : '') . '>'
            . icono($ico) . '<span>' . e($texto) . '</span></a>';
    };
    ?>
<!doctype html>
<html lang="es-CO">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0072BC">
    <title><?= e($titulo) ?> · <?= e($nombre) ?></title>
    <link rel="icon" href="<?= e(url($iconoApp)) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(url('assets/estilos.css?v=7')) ?>">
</head>
<body class="<?= $u ? 'con-menu' : 'sin-menu' ?><?= $u && $modulo ? ' con-accesos' : '' ?>">
<?php if ($u): ?>
    <header class="barra-superior">
        <button type="button" class="boton-icono" id="abrir-menu" aria-controls="lateral" aria-expanded="false" aria-label="Abrir menú"><?= icono('menu') ?></button>
        <a class="marca" href="<?= e(url($menu[0][0])) ?>">
            <img src="<?= e(url($iconoApp)) ?>" alt="" width="30" height="30"><span><?= e($nombre) ?></span>
        </a>
    </header>

    <aside class="lateral" id="lateral">
        <a class="marca" href="<?= e(url($menu[0][0])) ?>">
            <img src="<?= e(url($iconoApp)) ?>" alt="" width="38" height="38">
            <span><?= e($nombre) ?><small><?= e($CONFIG['app']['institucion']) ?></small></span>
        </a>
        <nav class="nav-lateral" aria-label="Menú del módulo">
            <?php foreach ($menu as $item): ?><?= $enlace($item) ?><?php endforeach; ?>
        </nav>
        <nav class="nav-lateral nav-secundaria" aria-label="Otros módulos y cuenta">
            <?php foreach ($otros as $m): ?>
                <a href="<?= e(url("$m/index.php")) ?>"><img src="<?= e(url(MODULOS[$m]['icono'])) ?>" alt="" class="icono"><span><?= e(MODULOS[$m]['nombre']) ?></span></a>
            <?php endforeach; ?>
            <?php if ($u['rol'] === 'admin'): ?><?= $enlace(['usuarios.php', 'Usuarios', 'usuarios']) ?><?php endif; ?>
            <?= $enlace(['clave.php', 'Mi clave', 'clave']) ?>
        </nav>
        <div class="quien">
            <span class="avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($u['nombre'], 0, 1))) ?></span>
            <span class="quien-texto"><strong><?= e($u['nombre']) ?></strong><small><?= e(ROLES[$u['rol']]) ?></small></span>
            <form method="post" action="<?= e(url('salir.php')) ?>">
                <?= campoCsrf() ?>
                <button type="submit" class="boton-icono" aria-label="Salir" title="Salir"><?= icono('salir') ?></button>
            </form>
        </div>
    </aside>
    <div class="velo" id="velo" hidden></div>
<?php endif; ?>

<main class="contenido">
    <?php foreach (tomarAvisos() as $a): ?>
        <div class="aviso aviso-<?= e($a['tipo']) ?>" role="status"><?= e($a['texto']) ?></div>
    <?php endforeach; ?>
    <?php $contenido(); ?>
</main>

<?php if ($u && $modulo): ?>
    <nav class="nav-inferior" aria-label="Accesos rápidos">
        <?php foreach (array_slice($menu, 0, 4) as $item): ?><?= $enlace($item) ?><?php endforeach; ?>
    </nav>
<?php endif; ?>
<script src="<?= e(url('assets/app.js?v=6')) ?>" defer></script>
</body>
</html>
<?php
}

/** Encabezado de página: título, subtítulo opcional y botones a la derecha (HTML ya escapado). */
function encabezado(string $titulo, string $subtitulo = '', string $acciones = ''): void
{ ?>
    <div class="encabezado-pagina">
        <div>
            <h1><?= e($titulo) ?></h1>
            <?php if ($subtitulo !== ''): ?><p class="suave"><?= e($subtitulo) ?></p><?php endif; ?>
        </div>
        <?php if ($acciones !== ''): ?><div class="acciones"><?= $acciones ?></div><?php endif; ?>
    </div>
<?php }

/* ---------- Paneles de los módulos ---------- */

/** Encabezado del panel: saludo, selector de día y botón principal. $modulo va en minúscula («lavandería»). */
function panelEncabezado(string $modulo, array $u, string $fecha, string $hoy, string $urlRegistrar, string $textoRegistrar): void
{
    $subtitulo = $fecha === $hoy ? "Así va el día de hoy en $modulo." : "Resumen de $modulo del " . fechaCorta($fecha) . '.';
    ob_start(); ?>
        <form method="get" class="fila" style="margin:0">
            <label for="fecha" class="oculto">Día</label>
            <input type="date" id="fecha" name="fecha" value="<?= e($fecha) ?>" max="<?= e($hoy) ?>" style="width:auto" onchange="this.form.submit()">
        </form>
        <a class="boton" href="<?= e($urlRegistrar) ?>"><?= icono('registrar') ?><?= e($textoRegistrar) ?></a>
    <?php
    encabezado('Hola, ' . explode(' ', $u['nombre'])[0], $subtitulo, (string) ob_get_clean());
}

/** Nombres de las áreas que no están en la lista de ids (texto separado por comas de MySQL). */
function faltantes(array $areas, ?string $ids): array
{
    return array_values(array_diff_key($areas, array_flip(array_map('intval', explode(',', (string) $ids)))));
}

/** Tarjeta de avance: cuántas áreas van, barra y áreas que faltan. */
function tarjetaAvance(string $titulo, string $cifra, int $hechas, array $faltan, int $total): void
{
    $porcentaje = $total > 0 ? min(100, round($hechas * 100 / $total)) : 0; ?>
    <div class="estado-item">
        <strong><?= e($titulo) ?></strong>
        <span class="suave"><?= e($cifra) ?></span>
        <div class="barra" aria-hidden="true"><span style="width:<?= $porcentaje ?>%"></span></div>
        <span class="pendientes"><?= $hechas ?> de <?= $total ?> áreas</span>
        <?php if ($hechas && $faltan): ?>
            <details class="pendientes"><summary>Faltan <?= count($faltan) ?></summary><?= e(implode(', ', $faltan)) ?></details>
        <?php endif; ?>
    </div>
<?php }
