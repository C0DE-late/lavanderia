<?php
/** Entrega la foto de una entrega de dietas solo a usuarios con permiso para verla. */
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/dietas.php';
require __DIR__ . '/../app/permisos.php';
$u = exigirModulo('dietas');

enviarFoto(buscarEntrega(leerEntero($_GET['id'] ?? '') ?? 0), $u);
