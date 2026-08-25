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
        'sync_status' => 'Server synchronization',
        'last_dispatched_at' => 'Last dispatch attempt',
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
        'sync' => 'Synchronize again',
    ],
    'status' => [
        'updated' => 'Your skin was saved successfully.',
        'unchanged' => 'This skin and arm model are already active on the website.',
        'deleted' => 'Your website skin was deleted.',
    ],
    'sync' => [
        'status' => [
            'pending' => 'Pending',
            'submitted' => 'Submitted',
            'failed' => 'Failed',
            'uncertain' => 'Outcome unknown',
            'not_configured' => 'Not configured',
        ],
        'status_help' => 'Submitted means the command was handed to the configured bridge; it does not confirm that SkinsRestorer applied the skin.',
        'clear_state_title' => 'SkinsRestorer clear operation',
        'clear_state_help' => 'The website skin is deleted. This state is retained so clear commands for every recorded server destination can be submitted again.',
        'feedback' => [
            'submitted' => 'The SkinsRestorer command was submitted.',
            'clear_submitted' => 'The SkinsRestorer clear command or commands were submitted.',
        ],
        'errors' => [
            'sync_disabled' => 'Server synchronization is currently disabled by an administrator.',
            'server_unavailable' => 'The configured Minecraft server is unavailable or cannot execute commands.',
            'invalid_game_id' => 'Your account does not contain a valid Minecraft UUID, so the server command was not submitted.',
            'invalid_variant' => 'The resolved arm model is invalid. Upload the skin again before retrying.',
            'invalid_public_url' => 'The generated public skin URL is invalid or contains unsafe characters.',
            'insecure_public_url' => 'The public skin URL must use HTTPS before it can be submitted to SkinsRestorer.',
            'unreachable_public_url' => 'The public skin URL uses a local or private host that MineSkin cannot reach.',
            'public_url_too_long' => 'The public skin URL exceeds the length supported by SkinsRestorer.',
            'queue_dispatch_failed' => 'SkinSystem could not save the SkinsRestorer command in the AzLink queue. Check the database connection before trying again.',
            'queue_cleanup_failed' => 'SkinSystem could not safely replace an older queued SkinsRestorer command. Try again after checking the database connection.',
            'dispatch_exception' => 'The bridge returned an error after dispatch began. Check the Minecraft server before synchronizing again.',
            'clear_may_be_in_flight' => 'A previous clear command may already be executing. The new skin command was submitted, but its final order cannot be confirmed; verify it in game and synchronize again if needed.',
            'stale_revision' => 'A newer skin revision replaced this request before it could be submitted. Reload the page and try again if needed.',
            'operation_busy' => 'Another skin operation is still running for your account. Wait a moment and try again.',
        ],
    ],
    'validation' => [
        'invalid_skin' => 'The uploaded file is not a valid, decodable PNG skin.',
        'dimensions' => 'The skin must be exactly 64×64 or 64×32 pixels.',
        'legacy_slim' => 'Legacy 64×32 skins only support the classic arm model.',
        'gd_missing' => 'The server cannot process skins because the PHP GD extension is unavailable.',
    ],
];
