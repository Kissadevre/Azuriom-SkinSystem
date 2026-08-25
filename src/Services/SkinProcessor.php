<?php

namespace Azuriom\Plugin\SkinSystem\Services;

use Azuriom\Plugin\SkinSystem\Models\Skin;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class SkinProcessor
{
    /**
     * Decode and re-encode an uploaded PNG before it is stored or published.
     *
     * @return array{contents: string, sha256: string, width: int, height: int, detected_variant: string}
     */
    public function process(UploadedFile $file): array
    {
        $path = $file->getPathname();
        $details = @getimagesize($path);

        if ($details === false || ($details[2] ?? null) !== IMAGETYPE_PNG) {
            $this->invalid();
        }

        [$width, $height] = $details;

        if ($width !== 64 || ! in_array($height, [32, 64], true)) {
            throw ValidationException::withMessages([
                'skin' => trans('skinsystem::messages.validation.dimensions'),
            ]);
        }

        $image = @imagecreatefrompng($path);

        if ($image === false) {
            $this->invalid();
        }

        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        $detectedVariant = $this->detectVariant($image, $height);

        ob_start();
        $encoded = imagepng($image, null, 7);
        $contents = ob_get_clean();

        unset($image);

        if (! $encoded || ! is_string($contents) || $contents === '') {
            $this->invalid();
        }

        return [
            'contents' => $contents,
            'sha256' => hash('sha256', $contents),
            'width' => $width,
            'height' => $height,
            'detected_variant' => $detectedVariant,
        ];
    }

    /**
     * Infer the arm model with the same unused-pixel heuristic as skinview3d.
     */
    private function detectVariant(\GdImage $image, int $height): string
    {
        if ($height === 32) {
            return Skin::VARIANT_CLASSIC;
        }

        $areas = [
            [50, 16, 2, 4],
            [54, 20, 2, 12],
            [42, 48, 2, 4],
            [46, 52, 2, 12],
        ];

        foreach ($areas as [$x, $y, $width, $areaHeight]) {
            if ($this->areaHasTransparency($image, $x, $y, $width, $areaHeight)) {
                return Skin::VARIANT_SLIM;
            }
        }

        if ($this->areasHaveColor($image, $areas, 0, 0, 0)
            || $this->areasHaveColor($image, $areas, 255, 255, 255)) {
            return Skin::VARIANT_SLIM;
        }

        return Skin::VARIANT_CLASSIC;
    }

    private function areaHasTransparency(\GdImage $image, int $x, int $y, int $width, int $height): bool
    {
        for ($offsetX = 0; $offsetX < $width; $offsetX++) {
            for ($offsetY = 0; $offsetY < $height; $offsetY++) {
                $color = imagecolorat($image, $x + $offsetX, $y + $offsetY);

                if ((($color >> 24) & 0x7F) !== 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<int, array{int, int, int, int}>  $areas
     */
    private function areasHaveColor(\GdImage $image, array $areas, int $red, int $green, int $blue): bool
    {
        foreach ($areas as [$x, $y, $width, $height]) {
            for ($offsetX = 0; $offsetX < $width; $offsetX++) {
                for ($offsetY = 0; $offsetY < $height; $offsetY++) {
                    $color = imagecolorat($image, $x + $offsetX, $y + $offsetY);

                    if ((($color >> 24) & 0x7F) !== 0
                        || (($color >> 16) & 0xFF) !== $red
                        || (($color >> 8) & 0xFF) !== $green
                        || ($color & 0xFF) !== $blue) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages([
            'skin' => trans('skinsystem::messages.validation.invalid_skin'),
        ]);
    }
}
