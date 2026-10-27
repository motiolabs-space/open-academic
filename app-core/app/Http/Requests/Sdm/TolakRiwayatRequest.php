<?php

declare(strict_types=1);

namespace App\Http\Requests\Sdm;

use App\Support\Portal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** Returning a lecturer's entry, with the reason they will act on. */
class TolakRiwayatRequest extends FormRequest
{
    public function authorize(): bool
    {
        $aktor = Portal::user();

        return $aktor !== null && Gate::forUser($aktor)->allows('validasi', $this->route('riwayat'));
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['alasan' => ['required', 'string', 'min:5', 'max:500']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['alasan.required' => __('kepegawaian.pesan.alasan_wajib'), 'alasan.min' => __('kepegawaian.pesan.alasan_wajib')];
    }
}
