<?php
/**
 * Configuración de la aplicación de Lavandería.
 * Cada valor se puede definir con una variable de entorno (Docker);
 * si no existe, se usa el valor por defecto (WAMP local).
 */
$env = static fn(string $nombre, string $defecto): string => getenv($nombre) !== false ? getenv($nombre) : $defecto;

return [
    'app' => [
        'nombre'       => $env('APP_NOMBRE', 'Lavandería'),
        'institucion'  => $env('APP_INSTITUCION', 'Hospital de Alta Complejidad del Magdalena Centro'),
        'zona_horaria' => 'America/Bogota',
    ],
    'db' => [
        'host'    => $env('DB_HOST', 'localhost'),
        'puerto'  => (int) $env('DB_PUERTO', '3306'),
        'nombre'  => $env('DB_NOMBRE', 'lavanderia'),
        'usuario' => $env('DB_USUARIO', 'root'),
        'clave'   => $env('DB_CLAVE', ''),
    ],
    // Recorridos diarios disponibles.
    'recorridos' => [1, 2, 3],
    // Límites de validación.
    'peso_max_kg'  => 500,
    'bolsas_max'   => 200,
    // Fotos: se guardan fuera del acceso público y se sirven solo a usuarios con sesión.
    'dir_fotos'     => $env('DIR_FOTOS', __DIR__ . '/almacen/fotos'),
    'foto_lado_max' => 1280, // píxeles del lado mayor tras reducir la imagen
];
