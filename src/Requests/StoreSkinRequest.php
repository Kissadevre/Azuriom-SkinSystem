<?php

namespace Azuriom\Plugin\SkinSystem\Requests;

use Azuriom\Plugin\SkinSystem\Models\Skin;
use Azuriom\Plugin\SkinSystem\Rules\MinecraftSkin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSkinRequest extends FormRequest
{
    /**
     * Determine whether the current user may upload a skin.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('skinsystem.skin') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'skin' => [
                'bail',
                'required',
                'file',
                'max:3072',
                'mimes:png',
                new MinecraftSkin(),
            ],
            'variant' => [
                'required',
                Rule::in(Skin::variants()),
            ],
        ];
    }
}
