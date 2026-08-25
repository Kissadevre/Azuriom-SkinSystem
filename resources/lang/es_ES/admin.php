<?php

return [
    'title' => 'SkinSystem',
    'description' => 'Conecta las skins subidas en Azuriom con un servidor autoritativo de SkinsRestorer.',
    'updated' => 'La configuración de sincronización de SkinSystem se guardó correctamente.',
    'stats' => [
        'total' => 'Skins activas en el sitio',
        'submitted' => 'Última operación enviada',
        'attention' => 'Requieren atención',
    ],
    'settings' => [
        'title' => 'Configuración de sincronización',
        'enabled' => 'Activar sincronización automática',
        'enabled_help' => 'Envía un comando de SkinsRestorer después de cada carga modificada y limpia todos los destinos registrados al eliminar una skin.',
        'server' => 'Servidor de Minecraft autoritativo',
        'select_server' => 'Selecciona un servidor ejecutable',
        'server_help' => 'Solo aparecen servidores de Minecraft con AzLink o RCON capaces de ejecutar comandos de consola.',
        'no_servers' => 'No hay un servidor de Minecraft ejecutable y compatible. Primero configura AzLink o RCON en Azuriom.',
        'endpoint' => 'Patrón del endpoint público de PNG',
        'endpoint_help' => 'SkinsRestorer y MineSkin deben poder descargar las URLs versionadas de PNG desde este endpoint.',
    ],
    'server_types' => [
        'mc-azlink' => 'AzLink',
        'mc-rcon' => 'RCON',
    ],
    'requirements' => [
        'title' => 'Antes de activar la sincronización',
        'skinsrestorer' => 'Instala y configura SkinsRestorer en el servidor que administra los datos de skins de los jugadores.',
        'bridge' => 'Conecta ese servidor con Azuriom mediante AzLink o RCON y selecciónalo arriba.',
        'public_url' => 'Publica Azuriom mediante una URL HTTPS accesible desde Internet; MineSkin no puede obtener archivos desde localhost o direcciones privadas.',
        'uuid' => 'Asegúrate de que cada cuenta tenga exactamente el UUID de Minecraft utilizado por el servidor. Se admiten los UUID offline generados por Azuriom.',
        'url_allowlist' => 'Si SkinsRestorer tiene activo commands.restrictSkinUrls, agrega el origen público de Azuriom a commands.restrictSkinUrls.list.',
        'skin_api' => 'Si Skin API continúa instalado, desactiva la opción heredada skinrestorer-integration de AzLink para evitar que su listener de ingreso sobrescriba SkinSystem.',
        'proxy' => 'En modo proxy con BungeeCord o Velocity, selecciona el AzLink/servidor del proxy donde SkinsRestorer administra sus datos; la consola de un backend no es autoritativa.',
        'cache_lock' => 'En instalaciones de Azuriom con varios nodos, usa una caché compartida como Redis o database para que los bloqueos por usuario funcionen en todos los nodos.',
        'scheduler' => 'Ejecuta el programador de Azuriom para eliminar revisiones reemplazadas después de la ventana de seguridad de 30 días.',
        'https_warning' => 'La URL configurada del sitio no usa HTTPS. Las skins se guardarán, pero SkinSystem se negará a enviar una URL insegura a SkinsRestorer.',
        'submitted_semantics' => '“Enviado” significa que Azuriom entregó uno o varios comandos a RCON o los dejó en la cola de AzLink. No demuestra su ejecución; las limpiezas conservadas pueden reenviarse de forma segura.',
    ],
    'validation' => [
        'server_required' => 'Selecciona un servidor autoritativo antes de activar la sincronización.',
        'server_unavailable' => 'El servidor seleccionado ya no está disponible o no puede ejecutar comandos de Minecraft compatibles.',
    ],
    'permissions' => [
        'skin' => 'Subir y administrar su propia skin de Minecraft',
        'admin' => 'Administrar la configuración de SkinSystem',
    ],
];
