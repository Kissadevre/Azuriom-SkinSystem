<?php

namespace Azuriom\Plugin\SkinSystem\Requests;

use Azuriom\Plugin\SkinSystem\Services\SkinSystemSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSettingsRequest extends FormRequest
{
    /**
     * Determine whether the current administrator may edit SkinSystem.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('skinsystem.admin') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'sync_enabled' => ['required', 'boolean'],
            'delivery_mode' => ['required', Rule::in(SkinSystemSettings::deliveryModes())],
            'application_target' => ['required', Rule::in(SkinSystemSettings::applicationTargets())],
            'mineskin_api_key' => ['nullable', 'string', 'max:512'],
            'remove_mineskin_api_key' => ['required', 'boolean'],
            'user_menu_enabled' => ['required', 'boolean'],
            'user_menu_icon' => [
                'required',
                'string',
                'max:64',
                'regex:/^bi-[a-z0-9]+(?:-[a-z0-9]+)*$/D',
            ],
            'library_limit' => [
                'required',
                'integer',
                'min:1',
                'max:'.SkinSystemSettings::MAX_LIBRARY_LIMIT,
            ],
            'server_id' => [
                'bail',
                'nullable',
                'integer',
                'min:1',
                'max:'.SkinSystemSettings::MAX_DATABASE_ID,
                Rule::exists('servers', 'id')->where(
                    fn ($query) => $query->whereIn('type', SkinSystemSettings::supportedServerTypes())
                ),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'library_limit.integer' => trans('skinsystem::admin.validation.library_limit_integer'),
            'library_limit.min' => trans('skinsystem::admin.validation.library_limit_min'),
            'library_limit.max' => trans('skinsystem::admin.validation.library_limit_max'),
            'user_menu_icon.regex' => trans('skinsystem::admin.validation.user_menu_icon_format'),
            'application_target.in' => trans('skinsystem::admin.validation.application_target_invalid'),
        ];
    }

    /**
     * Verify that the selected server still exposes an executable bridge.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->boolean('sync_enabled') && ! $this->filled('server_id')) {
                $validator->errors()->add('server_id', trans('skinsystem::admin.validation.server_required'));

                return;
            }

            if (! $validator->errors()->has('server_id')
                && $this->filled('server_id')
                && app(SkinSystemSettings::class)->findServer((int) $this->input('server_id')) === null) {
                $validator->errors()->add('server_id', trans('skinsystem::admin.validation.server_unavailable'));
            }

            $willHaveMineSkinKey = $this->filled('mineskin_api_key')
                || (! $this->boolean('remove_mineskin_api_key')
                    && app(SkinSystemSettings::class)->hasMineSkinApiKey());

            if ($this->input('delivery_mode') === SkinSystemSettings::DELIVERY_MINESKIN
                && ! $willHaveMineSkinKey) {
                $validator->errors()->add(
                    'mineskin_api_key',
                    trans('skinsystem::admin.validation.mineskin_key_required'),
                );
            }
        });
    }
}
