<?php

declare(strict_types=1);

namespace App\Http\Requests\TugasAkhir;

use App\Services\Berkas\BerkasService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A proposal version from the student.
 *
 * Whose project it belongs to is resolved from the signed-in student by the
 * controller and checked against ProposalPolicy there, never read from the
 * form.
 */
class ProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'berkas' => app(BerkasService::class)->aturan('dokumen'),
            'judul' => ['nullable', 'string', 'max:500'],
            'ringkasan' => ['nullable', 'string', 'max:5000'],
            'calon_pembimbing_dosen_id' => ['nullable', 'integer', Rule::exists('dosen', 'id')->whereNull('deleted_at')],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('tugas_akhir_proposal.field');
    }
}
