<?php

namespace Azuriom\Plugin\SkinSystem\Requests;

use Azuriom\Plugin\SkinSystem\Models\SavedSkin;
use Azuriom\Plugin\SkinSystem\Models\Skin;
use Azuriom\Plugin\SkinSystem\Rules\MinecraftSkin;
use Azuriom\Plugin\SkinSystem\Services\SkinSystemSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSkinRequest extends FormRequest
{
    /**
     * Determine whether the current user may upload a skin.
     */
    public function authorize(): bool
    {
        if (! ($this->user()?->can('skinsystem.skin') ?? false)) {
            return false;
        }

        return $this->input('action') !== 'save' || $this->user()->can('skinsystem.library');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['activate', 'save'])],
            'skin' => [
                'bail',
                'required',
                'file',
                'max:3072',
                'mimes:png',
                new MinecraftSkin,
            ],
            'variant' => [
                'required',
                Rule::in(Skin::variants()),
            ],
            'name' => [
                Rule::excludeIf($this->input('action') !== 'save'),
                Rule::requiredIf($this->input('action') === 'save'),
                'nullable',
                'string',
                'max:16',
                'regex:/^[A-Za-z0-9]+$/D',
            ],
            'replacement_id' => [
                Rule::excludeIf($this->input('action') !== 'save'),
                Rule::requiredIf($this->replacementIsRequired()),
                'nullable',
                'integer',
                Rule::exists('skinsystem_saved_skins', 'id')->where(
                    fn ($query) => $query->where('user_id', $this->user()?->getKey())
                ),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => trans('skinsystem::messages.validation.name_required'),
            'name.max' => trans('skinsystem::messages.validation.name_max'),
            'name.regex' => trans('skinsystem::messages.validation.name_format'),
            'replacement_id.required' => trans('skinsystem::messages.validation.replacement_required'),
            'replacement_id.exists' => trans('skinsystem::messages.validation.replacement_invalid'),
        ];
    }

    private function replacementIsRequired(): bool
    {
        if ($this->input('action') !== 'save' || ! $this->user()?->can('skinsystem.library')) {
            return false;
        }

        $limit = app(SkinSystemSettings::class)->libraryLimit();

        return $this->user()->getKey() !== null
            && SavedSkin::query()
                ->where('user_id', $this->user()->getKey())
                ->count() >= $limit;
    }
}
