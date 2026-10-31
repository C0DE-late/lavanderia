<?php
/**
 * Punto de arranque común: configuración, sesión, base de datos y utilidades.
 * Cada página pública empieza con: require __DIR__ . '/app/bootstrap.php';
 */
declare(strict_types=1);

$CONFIG = require __DIR__ . '/../config.php';
date_default_timezone_set($CONFIG['app']['zona_horaria']);

const ROLES = [
    'operario'   => 'Operario',
    'supervisor' => 'Supervisor',
    'admin'      => 'Administrador',
];
const TIPOS = ['sucia' => 'Sucia', 'limpia' => 'Limpia'];
// Módulos de la app: cada uno vive en su carpeta (ropa/, dietas/) con su menú y su panel.
const MODULOS = [
    'ropa'   => ['nombre' => 'Lavandería', 'icono' => 'assets/ropa.svg',   'descripcion' => 'Ropa sucia y limpia por área y recorrido.'],
    'dietas' => ['nombre' => 'Dietas',     'icono' => 'assets/dietas.svg', 'descripcion' => 'Dietas pedidas y entregadas por área y tiempo de comida.'],
];

// Sesión con cookie protegida.
if (session_status() !== PHP_SESSION_ACTIVE && PHP_SAPI !== 'cli') {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_name('lavanderia');
    session_start();
}

require __DIR__ . '/funciones.php';
require __DIR__ . '/plantilla.php';
