<?php

declare(strict_types=1);

namespace App\Http\Requests\Kemahasiswaan\Concerns;

use App\Enums\JenisDaftar;
use App\Models\Akademik\TahunAkademik;
use Closure;
use Illuminate\Validation\Rule;

/**
 * The enrolment-record rules Feeder applies, applied at the form instead.
 *
 * Each one is FeederValidator::riwayatPendidikan() asked a step earlier: a
 * transfer or RPL student must carry an origin institution and programme, and
 * the entry term must exist. Keyed on the same JenisDaftar::butuhAsal() the
 * validator and the mapper read, so the three cannot disagree about which
 * kinds need an origin. Recognised credits are not here because they are not
 * input — see AsalPendidikanData.
 */
trait AturanAsalPendidikan
{
    /**
     * @param array<int, string> $jenisBoleh enrolment kinds this form accepts
     * @return array<string, mixed>
     */
    protected function aturanAsal(array $jenisBoleh, ?string $termMasukKode = null): array
    {
        return [
            'jenis_daftar' => ['required', Rule::in($jenisBoleh)],
            'pt_asal' => [Rule::requiredIf(fn (): bool => $this->butuhAsal()), 'nullable', 'string', 'max:255'],
            'prodi_asal' => [Rule::requiredIf(fn (): bool => $this->butuhAsal()), 'nullable', 'string', 'max:255'],

            // Optional: PDDIKTI does not ask for it. Only the study-period rule
            // reads it, and only where the campus switched that on.
            'term_masuk_asal' => [
                'nullable',
                'regex:/^\d{4}[12]$/',
                function (string $atribut, mixed $nilai, Closure $gagal) use ($termMasukKode): void {
                    if ($termMasukKode !== null && is_string($nilai) && strcmp($nilai, $termMasukKode) >= 0) {
                        $gagal(__('pindahan.validasi.term_asal_sesudah'));
                    }
                },
            ],
        ];
    }

    /** @return array<string, string> */
    protected function pesanAsal(): array
    {
        return [
            'jenis_daftar.in' => __('pindahan.validasi.jenis'),
            'pt_asal.required' => __('pindahan.validasi.pt_asal'),
            'prodi_asal.required' => __('pindahan.validasi.prodi_asal'),
            'term_masuk_asal.regex' => __('pindahan.validasi.term_asal_format'),
        ];
    }

    /** @return array<string, string> */
    protected function atributAsal(): array
    {
        return [
            'jenis_daftar' => __('pindahan.kolom.jenis_daftar'),
            'pt_asal' => __('pindahan.kolom.pt_asal'),
            'prodi_asal' => __('pindahan.kolom.prodi_asal'),
            'term_masuk_asal' => __('pindahan.kolom.term_masuk_asal'),
        ];
    }

    protected function butuhAsal(): bool
    {
        return JenisDaftar::tryFrom((string) $this->input('jenis_daftar'))?->butuhAsal() ?? false;
    }

    /** The PDDIKTI code of the term chosen as this campus's entry term. */
    protected function kodeTermMasuk(): ?string
    {
        $id = $this->input('tahun_akademik_id');

        return is_numeric($id) ? TahunAkademik::query()->whereKey((int) $id)->value('kode') : null;
    }
}
