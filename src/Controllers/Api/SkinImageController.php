<?php

namespace Azuriom\Plugin\SkinSystem\Controllers\Api;

use Azuriom\Http\Controllers\Controller;
use Azuriom\Plugin\SkinSystem\Services\SkinStorage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SkinImageController extends Controller
{
    /**
     * Stream an immutable normalized skin to viewers and MineSkin.
     */
    public function show(int $user, int $revision, string $hash, SkinStorage $storage): StreamedResponse
    {
        abort_if($revision < 1, 404);

        $path = $storage->path($user, $hash);

        abort_unless($storage->disk()->exists($path), 404);

        return $storage->disk()->response($path, 'skin.png', [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'inline; filename="skin.png"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
