<?php
/** Dietas: administración de los tipos de dieta (normal, blanda, líquida...). */
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/catalogo.php';
exigirModulo('dietas', 'admin');

paginaCatalogo([
    'tabla'       => 'tipos_dieta',
    'titulo'      => 'Tipos de dieta',
    'subtitulo'   => 'Tipos que aparecen al registrar las dietas de un área.',
    'nuevo'       => 'Nuevo tipo de dieta',
    'crear'       => 'Crear tipo',
    'creado'      => 'Tipo de dieta «%s» creado.',
    'actualizado' => 'Tipo de dieta actualizado.',
    'activado'    => 'Tipo de dieta activado.',
    'desactivado' => 'Tipo de dieta desactivado. Su historial se conserva.',
    'repetido'    => 'Ya existe un tipo de dieta con ese nombre.',
    'sin_nombre'  => 'Escribe el nombre del tipo de dieta.',
]);
