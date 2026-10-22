<?php
require __DIR__ . '/app/bootstrap.php';

// Cada rol arranca en la pantalla que más usa.
$u = exigirRol();
redirigir($u['rol'] === 'operario' ? 'registrar.php' : 'reporte.php');
