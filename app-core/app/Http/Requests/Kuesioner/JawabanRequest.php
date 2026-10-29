<?php

declare(strict_types=1);

namespace App\Http\Requests\Kuesioner;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The shape of a filled-in form.
 *
 * Only the shape. Which questions are required, which scale values and choices
 * are valid, and whether this person may answer at all are KuesionerService's
 * to decide — the same rules then hold for a test or a command as for a form.
 */
class JawabanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('respond', $this->route('kuesioner')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'jawaban' => ['nullable', 'array'],
            'jawaban.*' => ['array'],
            'jawaban.*.nilai' => ['nullable', 'integer', 'between:1,5'],
            'jawaban.*.teks' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /** @return array<int, array{nilai?: int|null, teks?: string|null}> */
    public function jawaban(): array
    {
        return collect((array) $this->input('jawaban', []))
            ->mapWithKeys(fn (mixed $isi, mixed $id): array => [(int) $id => is_array($isi) ? $isi : []])
            ->all();
    }
}
