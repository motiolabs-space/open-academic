<?php

declare(strict_types=1);

namespace App\Http\Requests\Bridge;

use App\Models\Bridge\BridgeConsumer;
use Illuminate\Foundation\Http\FormRequest;

class TokenBridgeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('kelolaKredensial', BridgeConsumer::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['nama_token' => ['nullable', 'string', 'max:64']];
    }

    public function namaToken(): string
    {
        return (string) $this->string('nama_token');
    }
}
