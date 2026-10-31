<?php
/** Lavandería: administración de las áreas que entregan y reciben ropa. */
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/catalogo.php';
exigirModulo('ropa', 'admin');

paginaCatalogo([
    'tabla'       => 'areas',
    'titulo'      => 'Áreas de lavandería',
    'subtitulo'   => 'Áreas que aparecen al registrar ropa sucia y limpia.',
    'nuevo'       => 'Nueva área',
    'crear'       => 'Crear área',
    'creado'      => 'Área «%s» creada.',
    'actualizado' => 'Área actualizada.',
    'activado'    => 'Área activada.',
    'desactivado' => 'Área desactivada. Su historial se conserva.',
    'repetido'    => 'Ya existe un área con ese nombre.',
    'sin_nombre'  => 'Escribe el nombre del área.',
]);
