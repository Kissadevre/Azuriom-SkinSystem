<?php

return [
    'title' => 'Mi skin',
    'description' => 'Sube y visualiza la skin de Minecraft administrada por tu cuenta de Azuriom.',
    'viewer' => [
        'title' => 'Vista previa 3D',
        'canvas' => 'Vista previa 3D interactiva de tu skin de Minecraft',
        'empty' => 'Selecciona una skin PNG para visualizarla aquí.',
        'error' => 'No fue posible mostrar la skin seleccionada en el visor 3D.',
        'unsupported' => 'Tu navegador no es compatible con el visor 3D de skins.',
    ],
    'upload' => [
        'title' => 'Subir una skin',
        'help' => 'Solo PNG, 64×64 o formato antiguo 64×32 píxeles, hasta 3 MB.',
    ],
    'current' => [
        'title' => 'Skin actual del sitio web',
    ],
    'delete' => [
        'title' => 'Eliminar skin',
        'confirm' => '¿Seguro que quieres eliminar tu skin actual del sitio web?',
    ],
    'fields' => [
        'skin' => 'Skin de Minecraft en PNG',
        'variant' => 'Modelo de brazos',
        'resolved_variant' => 'Modelo detectado',
        'revision' => 'Revisión',
    ],
    'variants' => [
        'auto' => 'Detectar automáticamente',
        'classic' => 'Clásico (brazos de 4 píxeles)',
        'slim' => 'Delgado (brazos de 3 píxeles)',
    ],
    'actions' => [
        'upload' => 'Subir skin',
        'replace' => 'Reemplazar skin',
        'delete' => 'Eliminar skin',
        'download' => 'Descargar PNG',
    ],
    'status' => [
        'updated' => 'Tu skin se guardó correctamente.',
        'unchanged' => 'Esta skin y modelo de brazos ya están activos en el sitio web.',
        'deleted' => 'Tu skin del sitio web fue eliminada.',
    ],
    'validation' => [
        'invalid_skin' => 'El archivo subido no es una skin PNG válida y decodificable.',
        'dimensions' => 'La skin debe medir exactamente 64×64 o 64×32 píxeles.',
        'legacy_slim' => 'Las skins antiguas de 64×32 solo admiten el modelo de brazos clásico.',
        'gd_missing' => 'El servidor no puede procesar skins porque la extensión GD de PHP no está disponible.',
    ],
];
