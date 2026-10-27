<?php

declare(strict_types=1);

namespace App\Http\Requests\TugasAkhir;

use App\Enums\KeputusanTelaah;
use App\Models\TugasAkhir\Proposal;
use App\Support\Portal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** A reviewer's decision on a proposal version, from staff or a lecturer. */
class TelaahProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $aktor = Portal::user();
        $proposal = $this->route('proposal');

        return $aktor !== null
            && $proposal instanceof Proposal
            && Gate::forUser($aktor)->allows('telaah', $proposal);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'keputusan' => ['required', Rule::enum(KeputusanTelaah::class)],
            'catatan' => [
                Rule::requiredIf(fn (): bool => KeputusanTelaah::tryFrom((string) $this->input('keputusan'))?->wajibCatatan() ?? false),
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }

    public function keputusan(): KeputusanTelaah
    {
        return KeputusanTelaah::from((string) $this->validated('keputusan'));
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['catatan.required' => __('tugas_akhir_proposal.galat.catatan_wajib')];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return __('tugas_akhir_proposal.field');
    }
}
