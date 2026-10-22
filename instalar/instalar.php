<?php
/**
 * Instalador: crea la base de datos, las tablas, las áreas de ejemplo y un usuario por rol.
 * Uso (desde la consola):  php instalar/instalar.php
 * Solo crea lo que no existe; no borra datos.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit('Ejecuta este instalador desde la consola.');
}

$CONFIG = require __DIR__ . '/../config.php';
$c = $CONFIG['db'];

$pdo = new PDO("mysql:host={$c['host']};port={$c['puerto']};charset=utf8mb4", $c['usuario'], $c['clave'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$pdo->exec("CREATE DATABASE IF NOT EXISTS `{$c['nombre']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci");
$pdo->exec("USE `{$c['nombre']}`");
$pdo->exec(file_get_contents(__DIR__ . '/esquema.sql'));
echo "Base de datos y tablas listas.\n";

// Áreas de ejemplo: edítalas desde la sección Áreas del administrador.
$areas = [
    'Urgencias', 'Unidad materna y partos', 'Hospitalización adultos', 'Hospitalización pediátrica',
    'Hospitalización ginecológica', 'UCI adulto', 'UCI pediátrica', 'UCI neonatal', 'Cirugía',
    'Hemodinamia', 'Consulta externa', 'Imágenes diagnósticas', 'Laboratorio clínico',
];
$insArea = $pdo->prepare('INSERT IGNORE INTO areas (nombre, orden) VALUES (?, ?)');
foreach ($areas as $i => $nombre) {
    $insArea->execute([$nombre, ($i + 1) * 10]);
}
echo count($areas) . " áreas de ejemplo verificadas.\n";

// Un usuario inicial por rol, con clave aleatoria que se muestra una sola vez.
$usuarios = [
    ['Administrador', 'admin', 'admin'],
    ['Supervisor', 'supervisor', 'supervisor'],
    ['Operario', 'operario', 'operario'],
];
$existe = $pdo->prepare('SELECT 1 FROM usuarios WHERE usuario = ?');
$insUsuario = $pdo->prepare('INSERT INTO usuarios (nombre, usuario, clave_hash, rol) VALUES (?, ?, ?, ?)');
foreach ($usuarios as [$nombre, $usuario, $rol]) {
    $existe->execute([$usuario]);
    if ($existe->fetchColumn()) {
        echo "El usuario '$usuario' ya existe; no se modifica.\n";
        continue;
    }
    $clave = substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(9))), 0, 10);
    $insUsuario->execute([$nombre, $usuario, password_hash($clave, PASSWORD_DEFAULT), $rol]);
    echo "Usuario creado -> usuario: $usuario   clave: $clave   (rol: $rol)\n";
}
echo "\nGuarda estas claves y cámbialas al entrar (menú «Mi clave»).\n";
