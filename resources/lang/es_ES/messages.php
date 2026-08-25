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
        'sync_status' => 'Sincronización con el servidor',
        'last_dispatched_at' => 'Último intento de envío',
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
        'sync' => 'Sincronizar de nuevo',
    ],
    'status' => [
        'updated' => 'Tu skin se guardó correctamente.',
        'unchanged' => 'Esta skin y modelo de brazos ya están activos en el sitio web.',
        'deleted' => 'Tu skin del sitio web fue eliminada.',
    ],
    'sync' => [
        'status' => [
            'pending' => 'Pendiente',
            'submitted' => 'Enviado',
            'failed' => 'Fallido',
            'uncertain' => 'Resultado desconocido',
            'not_configured' => 'Sin configurar',
        ],
        'status_help' => 'Enviado significa que el comando se entregó al puente configurado; no confirma que SkinsRestorer haya aplicado la skin.',
        'clear_state_title' => 'Operación para limpiar SkinsRestorer',
        'clear_state_help' => 'La skin del sitio web fue eliminada. Este estado se conserva para volver a enviar comandos de limpieza a todos los destinos registrados.',
        'feedback' => [
            'submitted' => 'El comando de SkinsRestorer fue enviado.',
            'clear_submitted' => 'El comando o los comandos para limpiar la skin en SkinsRestorer fueron enviados.',
        ],
        'errors' => [
            'sync_disabled' => 'La sincronización con el servidor está desactivada actualmente por un administrador.',
            'server_unavailable' => 'El servidor de Minecraft configurado no está disponible o no puede ejecutar comandos.',
            'invalid_game_id' => 'Tu cuenta no contiene un UUID de Minecraft válido, por lo que no se envió el comando al servidor.',
            'invalid_variant' => 'El modelo de brazos resuelto no es válido. Vuelve a subir la skin antes de reintentar.',
            'invalid_public_url' => 'La URL pública generada para la skin no es válida o contiene caracteres inseguros.',
            'insecure_public_url' => 'La URL pública de la skin debe usar HTTPS antes de enviarse a SkinsRestorer.',
            'unreachable_public_url' => 'La URL pública de la skin usa un host local o privado al que MineSkin no puede acceder.',
            'public_url_too_long' => 'La URL pública de la skin supera la longitud admitida por SkinsRestorer.',
            'queue_dispatch_failed' => 'SkinSystem no pudo guardar el comando de SkinsRestorer en la cola de AzLink. Revisa la conexión con la base de datos antes de reintentar.',
            'queue_cleanup_failed' => 'SkinSystem no pudo reemplazar de forma segura un comando anterior de SkinsRestorer en cola. Revisa la conexión con la base de datos e inténtalo de nuevo.',
            'dispatch_exception' => 'El puente devolvió un error después de iniciar el envío. Revisa el servidor de Minecraft antes de sincronizar de nuevo.',
            'clear_may_be_in_flight' => 'Un comando de limpieza anterior podría estar ejecutándose. El comando de la nueva skin fue enviado, pero no se puede confirmar el orden final; verifícalo en el juego y vuelve a sincronizar si es necesario.',
            'stale_revision' => 'Una revisión más reciente reemplazó esta solicitud antes de poder enviarla. Recarga la página y reintenta si es necesario.',
            'operation_busy' => 'Ya hay otra operación de skin en curso para tu cuenta. Espera un momento y vuelve a intentarlo.',
        ],
    ],
    'validation' => [
        'invalid_skin' => 'El archivo subido no es una skin PNG válida y decodificable.',
        'dimensions' => 'La skin debe medir exactamente 64×64 o 64×32 píxeles.',
        'legacy_slim' => 'Las skins antiguas de 64×32 solo admiten el modelo de brazos clásico.',
        'gd_missing' => 'El servidor no puede procesar skins porque la extensión GD de PHP no está disponible.',
    ],
];
