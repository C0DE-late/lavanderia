<?php
/** Dietas: administración de las áreas que reciben dietas (independientes de las de lavandería). */
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/catalogo.php';
exigirModulo('dietas', 'admin');

paginaCatalogo([
    'tabla'       => 'areas_dieta',
    'titulo'      => 'Áreas de dietas',
    'subtitulo'   => 'Áreas con pacientes que reciben dietas.',
    'nuevo'       => 'Nueva área',
    'crear'       => 'Crear área',
    'creado'      => 'Área «%s» creada.',
    'actualizado' => 'Área actualizada.',
    'activado'    => 'Área activada.',
    'desactivado' => 'Área desactivada. Su historial se conserva.',
    'repetido'    => 'Ya existe un área con ese nombre.',
    'sin_nombre'  => 'Escribe el nombre del área.',
]);
