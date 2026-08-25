<?php

namespace Azuriom\Plugin\SkinSystem\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class MinecraftSkin implements ValidationRule
{
    /**
     * Validate a real, decodable Minecraft PNG skin.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail('skinsystem::messages.validation.invalid_skin')->translate();

            return;
        }

        if (! function_exists('imagecreatefrompng')) {
            $fail('skinsystem::messages.validation.gd_missing')->translate();

            return;
        }

        $path = $value->getPathname();
        $details = @getimagesize($path);

        if ($details === false || ($details[2] ?? null) !== IMAGETYPE_PNG) {
            $fail('skinsystem::messages.validation.invalid_skin')->translate();

            return;
        }

        [$width, $height] = $details;

        if ($width !== 64 || ! in_array($height, [32, 64], true)) {
            $fail('skinsystem::messages.validation.dimensions')->translate();

            return;
        }

        $image = @imagecreatefrompng($path);

        if ($image === false) {
            $fail('skinsystem::messages.validation.invalid_skin')->translate();

            return;
        }

        unset($image);
    }
}
