<?php

declare(strict_types=1);

namespace App\Http\Requests\Master;

use App\Enums\SemesterType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The academic calendar's dates, validated as a sequence rather than as eight
 * independent fields.
 *
 * A KRS window that closes before it opens, or a grade window outside the
 * semester it grades, is not caught by any per-field rule — and neither
 * produces an error later. It produces a semester where nobody can do anything,
 * discovered by a student at midnight on the first day of registration.
 */
class TahunAkademikRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Permission is checked in the controller.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $membuat = $this->route('term') === null;

        return [
            'tahun_mulai' => ['required', 'integer', 'min:2000', 'max:2100'],

            // Immutable after creation: the PDDIKTI code is derived from it,
            // and that code is already stamped on everything reported.
            'semester' => [
                Rule::requiredIf($membuat),
                Rule::enum(SemesterType::class),
            ],

            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after:tanggal_mulai'],

            'krs_mulai' => ['nullable', 'date'],
            'krs_selesai' => ['nullable', 'date', 'after_or_equal:krs_mulai', 'required_with:krs_mulai'],
            'krs_perubahan_selesai' => ['nullable', 'date', 'after_or_equal:krs_selesai'],

            'nilai_mulai' => ['nullable', 'date'],
            'nilai_selesai' => ['nullable', 'date', 'after_or_equal:nilai_mulai', 'required_with:nilai_mulai'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'tanggal_selesai.after' => 'Semester harus berakhir setelah tanggal mulainya.',
            'krs_selesai.after_or_equal' => 'Masa KRS tidak boleh ditutup sebelum dibuka.',
            'krs_selesai.required_with' => 'Tanggal penutupan KRS wajib diisi bila tanggal pembukaan diisi.',
            'krs_perubahan_selesai.after_or_equal' => 'Masa perubahan KRS berakhir setelah masa KRS reguler.',
            'nilai_selesai.after_or_equal' => 'Masa input nilai tidak boleh ditutup sebelum dibuka.',
            'nilai_selesai.required_with' => 'Tanggal penutupan input nilai wajib diisi bila tanggal pembukaan diisi.',
        ];
    }

    protected function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $mulai = $this->date('tanggal_mulai');
            $selesai = $this->date('tanggal_selesai');

            if ($mulai === null || $selesai === null) {
                return;
            }

            // Every gate has to sit inside the semester it gates. A grade window
            // that runs past the semester's end is how a locked term ends up
            // still accepting marks.
            foreach ([
                'krs_mulai' => 'Pembukaan KRS',
                'krs_selesai' => 'Penutupan KRS',
                'krs_perubahan_selesai' => 'Penutupan perubahan KRS',
                'nilai_mulai' => 'Pembukaan input nilai',
                'nilai_selesai' => 'Penutupan input nilai',
            ] as $field => $label) {
                $nilai = $this->date($field);

                if ($nilai === null) {
                    continue;
                }

                // Grade entry legitimately runs past the last day of teaching,
                // so only the lower bound is enforced for it.
                $lewatBawah = $nilai->lt($mulai->copy()->subMonth());
                $lewatAtas = !str_starts_with($field, 'nilai_') && $nilai->gt($selesai);

                if ($lewatBawah || $lewatAtas) {
                    $validator->errors()->add(
                        $field,
                        "{$label} berada di luar rentang semester ini.",
                    );
                }
            }
        });
    }
}
