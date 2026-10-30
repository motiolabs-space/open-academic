<?php

declare(strict_types=1);

namespace App\Http\Requests\Bridge;

use App\DTOs\Bridge\KonsumenBridgeData;
use App\Models\Bridge\BridgeConsumer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class KonsumenBridgeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('kelolaKredensial', BridgeConsumer::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $baru = $this->route('konsumen') === null;

        return [
            'nama' => ['required', 'string', 'max:255'],
            'slug' => $baru ? ['required', 'string', 'max:64', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'] : ['prohibited'],
            'deskripsi' => ['nullable', 'string', 'max:255'],
            'base_url' => ['nullable', 'url', 'max:255'],
            'scopes' => ['nullable', 'array'],
            'scopes.*' => ['string', Rule::in(array_keys((array) config('bridge.scopes')))],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['slug.regex' => __('bridge.validasi.slug')];
    }

    public function toDto(): KonsumenBridgeData
    {
        return KonsumenBridgeData::fromRequest($this);
    }
}
