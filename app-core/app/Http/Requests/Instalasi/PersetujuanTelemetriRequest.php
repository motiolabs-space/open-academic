<?php

declare(strict_types=1);

namespace App\Http\Requests\Instalasi;

use Illuminate\Foundation\Http\FormRequest;

/** Switching the installation ping on or off from Pengaturan (ADR-043). */
class PersetujuanTelemetriRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Permission is checked in the controller.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'telemetri_setuju' => ['nullable', 'boolean'],
            'telemetri_sertakan_url' => ['nullable', 'boolean'],
        ];
    }
}
