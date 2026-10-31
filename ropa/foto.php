<?php
/** Entrega la foto de un registro de ropa solo a usuarios con permiso para verlo. */
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/ropa.php';
require __DIR__ . '/../app/permisos.php';
$u = exigirModulo('ropa');

enviarFoto(buscarRegistro(leerEntero($_GET['id'] ?? '') ?? 0), $u);
