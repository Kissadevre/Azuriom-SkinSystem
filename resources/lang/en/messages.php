<?php

return [
    'title' => 'My skin',
    'description' => 'Upload and preview the Minecraft skin managed by your Azuriom account.',
    'viewer' => [
        'title' => '3D preview',
        'canvas' => 'Interactive 3D preview of your Minecraft skin',
        'empty' => 'Choose a PNG skin to preview it here.',
        'error' => 'The selected skin could not be rendered in the 3D viewer.',
        'unsupported' => 'Your browser does not support the 3D skin viewer.',
    ],
    'upload' => [
        'title' => 'Upload a skin',
        'help' => 'PNG only, 64×64 or legacy 64×32 pixels, up to 3 MB.',
    ],
    'current' => [
        'title' => 'Current website skin',
    ],
    'delete' => [
        'title' => 'Delete skin',
        'confirm' => 'Are you sure you want to remove your current website skin?',
    ],
    'fields' => [
        'skin' => 'Minecraft skin PNG',
        'variant' => 'Arm model',
        'resolved_variant' => 'Detected model',
        'revision' => 'Revision',
    ],
    'variants' => [
        'auto' => 'Detect automatically',
        'classic' => 'Classic (4-pixel arms)',
        'slim' => 'Slim (3-pixel arms)',
    ],
    'actions' => [
        'upload' => 'Upload skin',
        'replace' => 'Replace skin',
        'delete' => 'Delete skin',
        'download' => 'Download PNG',
    ],
    'status' => [
        'updated' => 'Your skin was saved successfully.',
        'unchanged' => 'This skin and arm model are already active on the website.',
        'deleted' => 'Your website skin was deleted.',
    ],
    'validation' => [
        'invalid_skin' => 'The uploaded file is not a valid, decodable PNG skin.',
        'dimensions' => 'The skin must be exactly 64×64 or 64×32 pixels.',
        'legacy_slim' => 'Legacy 64×32 skins only support the classic arm model.',
        'gd_missing' => 'The server cannot process skins because the PHP GD extension is unavailable.',
    ],
];
