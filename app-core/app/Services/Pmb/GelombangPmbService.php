<?php

declare(strict_types=1);

namespace App\Services\Pmb;

use App\DTOs\Pmb\PengaturanGelombangData;
use App\DTOs\Pmb\TawaranProdiData;
use App\Enums\ApplicantStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Pmb\PmbGelombang;
use App\Models\Pmb\PmbGelombangProdi;
use App\Models\Pmb\PmbPendaftar;
use Illuminate\Support\Collection;

/**
 * A wave's terms for online applicants: whether it is open to them, what it
 * asks for, and which programmes it offers at what price and with how many
 * seats.
 */
class GelombangPmbService
{
    public function perbarui(PmbGelombang $gelombang, PengaturanGelombangData $data): PmbGelombang
    {
        $gelombang->update([
            'is_daring' => $data->daring,
            'dokumen_wajib' => $this->hanyaYangDikenal($data->dokumenWajib),
            'keterangan' => $data->keterangan,
        ]);

        return $gelombang->refresh();
    }

    /**
     * Adds a programme to the wave, or updates its terms.
     *
     * A fee change applies to applications started from now on; those
     * already started keep the fee they were shown (PendaftaranPmbService
     * freezes it on the application).
     */
    public function simpanTawaran(PmbGelombang $gelombang, TawaranProdiData $data): PmbGelombangProdi
    {
        return PmbGelombangProdi::updateOrCreate(
            ['pmb_gelombang_id' => $gelombang->id, 'prodi_id' => $data->prodiId],
            [
                'daya_tampung' => $data->dayaTampung,
                'biaya_formulir' => $data->biayaFormulir,
                'ambang_lulus' => $data->ambangLulus,
                'dokumen_wajib' => $this->hanyaYangDikenal($data->dokumenTambahan),
            ],
        );
    }

    /** Refused once anybody has chosen the programme in this wave. */
    public function hapusTawaran(PmbGelombangProdi $tawaran): void
    {
        $dipakai = PmbPendaftar::withTrashed()
            ->where('pmb_gelombang_id', $tawaran->pmb_gelombang_id)
            ->where(fn ($q) => $q->where('prodi_pilihan_1_id', $tawaran->prodi_id)
                ->orWhere('prodi_pilihan_2_id', $tawaran->prodi_id))
            ->exists();

        if ($dipakai) {
            throw new AturanAkademikException(__('pmb.admin.tawaran_dipakai'));
        }

        $tawaran->delete();
    }

    /**
     * Waves an applicant may join today, with what they offer.
     *
     * @return Collection<int, PmbGelombang>
     */
    public function terbukaDaring(): Collection
    {
        return PmbGelombang::query()
            ->terbukaDaring()
            ->with(['tahunAkademik', 'prodiDitawarkan.prodi'])
            ->orderBy('tanggal_selesai')
            ->get();
    }

    /**
     * Per programme in one wave: submitted applications by first choice, and
     * seats already taken.
     *
     * @return array{pendaftar: Collection<int, int>, terisi: Collection<int, int>}
     */
    public function rekapProdi(PmbGelombang $gelombang): array
    {
        return [
            'pendaftar' => PmbPendaftar::query()
                ->where('pmb_gelombang_id', $gelombang->id)
                ->whereNotIn('status', [ApplicantStatus::Draf->value, ApplicantStatus::Batal->value])
                ->selectRaw('prodi_pilihan_1_id, COUNT(*) as jumlah')
                ->groupBy('prodi_pilihan_1_id')
                ->pluck('jumlah', 'prodi_pilihan_1_id'),
            'terisi' => PmbPendaftar::query()
                ->where('pmb_gelombang_id', $gelombang->id)
                ->whereIn('status', [ApplicantStatus::Lulus->value, ApplicantStatus::DaftarUlang->value, ApplicantStatus::Mahasiswa->value])
                ->selectRaw('prodi_diterima_id, COUNT(*) as jumlah')
                ->groupBy('prodi_diterima_id')
                ->pluck('jumlah', 'prodi_diterima_id'),
        ];
    }

    /**
     * @param array<int, string> $jenis
     * @return array<int, string>
     */
    private function hanyaYangDikenal(array $jenis): array
    {
        return array_values(array_intersect(array_keys((array) config('berkas.pmb')), $jenis));
    }
}
