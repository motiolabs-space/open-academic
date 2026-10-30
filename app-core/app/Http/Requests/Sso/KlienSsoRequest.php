<?php

declare(strict_types=1);

namespace App\Http\Requests\Sso;

use App\DTOs\Sso\KlienSsoData;
use App\Models\Sso\KlienSso;
use App\Rules\UriCallbackAman;
use Illuminate\Foundation\Http\FormRequest;

class KlienSsoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('kelola', KlienSso::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:255'],
            'redirect_uris' => ['required', 'string', 'max:2000'],
            'pihak_pertama' => ['sometimes', 'boolean'],
        ];
    }

    /** Every line a safe URI — checked here so the form can say which one. */
    public function after(): array
    {
        return [function ($validator): void {
            foreach ($this->toDto()->redirectUris as $uri) {
                if (!UriCallbackAman::sah($uri)) {
                    $validator->errors()->add('redirect_uris', __('sso_klien.galat.redirect_tidak_aman', ['uri' => $uri]));
                }
            }
        }];
    }

    public function toDto(): KlienSsoData
    {
        return KlienSsoData::fromRequest($this);
    }
}
