<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Feeder\FeederDictionaryRun;
use Illuminate\Foundation\Http\FormRequest;

/** A new reading of the Feeder dictionary, with the version if Feeder will not say. */
class PeriksaKamusFeederRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', FeederDictionaryRun::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'versi' => ['nullable', 'string', 'max:32', 'regex:/^[0-9A-Za-z.\- ]+$/'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['versi' => __('feeder.kamus.form.versi')];
    }
}
