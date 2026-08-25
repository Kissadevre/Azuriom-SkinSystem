<?php

namespace Azuriom\Plugin\SkinSystem\Tests\Feature;

use Azuriom\Plugin\SkinSystem\Models\SkinRevision;
use Azuriom\Plugin\SkinSystem\Services\SkinStorage;
use Azuriom\Plugin\SkinSystem\Tests\TestCase;
use Illuminate\Support\Facades\Storage;

class SkinImageEndpointTest extends TestCase
{
    public function test_the_exact_revision_tuple_streams_an_immutable_png(): void
    {
        $user = $this->createUser();
        $hash = str_repeat('b', 64);
        $path = app(SkinStorage::class)->path($user->id, $hash);
        $contents = $this->pngContents();
        Storage::disk('local')->put($path, $contents);
        SkinRevision::create([
            'user_id' => $user->id,
            'revision' => 7,
            'file' => $path,
            'sha256' => $hash,
            'resolved_variant' => 'classic',
        ]);

        $response = $this->get("/api/skinsystem/skins/{$user->id}/7-{$hash}.png");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
        $this->assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=31536000', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('immutable', (string) $response->headers->get('Cache-Control'));
        $this->assertSame($contents, $response->streamedContent());
    }

    public function test_aliases_zero_and_overflow_identifiers_are_rejected_with_404(): void
    {
        $user = $this->createUser();
        $hash = str_repeat('c', 64);
        $path = app(SkinStorage::class)->path($user->id, $hash);
        Storage::disk('local')->put($path, $this->pngContents());
        SkinRevision::create([
            'user_id' => $user->id,
            'revision' => 1,
            'file' => $path,
            'sha256' => $hash,
            'resolved_variant' => 'classic',
        ]);

        foreach ([
            "/api/skinsystem/skins/{$user->id}/2-{$hash}.png",
            '/api/skinsystem/skins/'.($user->id + 1)."/1-{$hash}.png",
            "/api/skinsystem/skins/{$user->id}/1-".str_repeat('d', 64).'.png',
            "/api/skinsystem/skins/0/1-{$hash}.png",
            "/api/skinsystem/skins/{$user->id}/0-{$hash}.png",
            "/api/skinsystem/skins/2147483648/1-{$hash}.png",
            "/api/skinsystem/skins/{$user->id}/2147483648-{$hash}.png",
            '/api/skinsystem/skins/'.str_repeat('9', 100)."/1-{$hash}.png",
            "/api/skinsystem/skins/{$user->id}/".str_repeat('9', 100)."-{$hash}.png",
        ] as $url) {
            $this->get($url)->assertNotFound();
        }
    }

    private function pngContents(): string
    {
        $image = imagecreatetruecolor(64, 64);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $color = imagecolorallocatealpha($image, 40, 120, 200, 0);
        imagefill($image, 0, 0, $color);
        ob_start();
        imagepng($image);
        $contents = ob_get_clean();
        imagedestroy($image);

        return $contents;
    }
}
