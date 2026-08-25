<?php

namespace Azuriom\Plugin\SkinSystem\Controllers\Api;

use Azuriom\Http\Controllers\Controller;
use Azuriom\Plugin\SkinSystem\Models\SkinRevision;
use Azuriom\Plugin\SkinSystem\Services\SkinStorage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SkinImageController extends Controller
{
    /**
     * Stream an immutable normalized skin to viewers and MineSkin.
     */
    public function show(string $user, string $revision, string $hash, SkinStorage $storage): StreamedResponse
    {
        abort_unless($this->isDatabaseId($user) && $this->isDatabaseId($revision), 404);

        $userId = (int) $user;
        $revisionNumber = (int) $revision;

        $skinRevision = SkinRevision::query()
            ->where('user_id', $userId)
            ->where('revision', $revisionNumber)
            ->where('sha256', $hash)
            ->firstOrFail();

        $path = $skinRevision->file;

        abort_unless($storage->disk()->exists($path), 404);

        return $storage->disk()->response($path, 'skin.png', [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'inline; filename="skin.png"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    private function isDatabaseId(string $value): bool
    {
        return preg_match('/^[1-9][0-9]{0,9}$/D', $value) === 1
            && (strlen($value) < 10 || strcmp($value, '2147483647') <= 0);
    }
}
