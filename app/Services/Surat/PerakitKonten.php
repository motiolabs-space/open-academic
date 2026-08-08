<?php

declare(strict_types=1);

namespace App\Services\Surat;

use App\Enums\JenisSurat;
use App\Models\Akademik\ProdiCpl;
use App\Models\Kemahasiswaan\AktivitasMahasiswa;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Surat\Surat;
use App\Services\Akademik\TranskripService;
use App\Services\Branding\BrandingService;

/**
 * Freezing the facts a letter asserts, at the moment it is issued.
 *
 * Everything a letter says is captured here and stored on the record. Nothing is
 * recomputed when the PDF is rendered or when somebody verifies it.
 *
 * That is the whole design. A letter is a statement about a moment — "this
 * person was enrolled on 3 March" — and a document that quietly rewrote itself
 * as the database changed would be a different document each time it was
 * opened, which is the one thing an official paper must never be. It also means
 * a reissued PDF matches the copy already sitting in somebody's file.
 */
class PerakitKonten
{
    public function __construct(
        private readonly BrandingService $brand,
        private readonly TranskripService $transkrip,
    ) {}

    /** @return array<string, mixed> */
    public function untuk(Surat $surat): array
    {
        $mahasiswa = $surat->mahasiswa->loadMissing(['prodi.fakultas', 'yudisium']);

        return $this->umum($mahasiswa) + match ($surat->jenis) {
            JenisSurat::AktifKuliah => $this->aktifKuliah($mahasiswa),
            JenisSurat::KeteranganLulus => $this->lulus($mahasiswa),
            JenisSurat::Pengantar => ['keperluan' => $surat->keperluan],
            JenisSurat::TranskripLegalisir => $this->transkripRingkas($mahasiswa),
            JenisSurat::Skpi => $this->skpi($mahasiswa),
        };
    }

    /** Facts every letter states about the person and the institution. */
    private function umum(Mahasiswa $mahasiswa): array
    {
        return [
            'nama' => $mahasiswa->nama,
            'nim' => $mahasiswa->nim,
            'tempat_lahir' => $mahasiswa->tempat_lahir,
            'tanggal_lahir' => $mahasiswa->tanggal_lahir?->toDateString(),
            'prodi' => $mahasiswa->prodi->nama,
            'jenjang' => $mahasiswa->prodi->jenjang->label(),
            'fakultas' => $mahasiswa->prodi->fakultas?->nama,
            'angkatan' => $mahasiswa->angkatan,

            'institusi' => $this->brand->institutionName(),
            'kode_institusi' => $this->brand->institutionCode(),
            'penandatangan' => (array) config('surat.penandatangan'),

            /*
             * NIK is deliberately absent, here as everywhere else.
             *
             * A letter of introduction ends up in a filing cabinet at an
             * organisation with no duty of care towards it, and no recipient of
             * any of these documents has ever needed a national identity number
             * to act on one.
             */
        ];
    }

    private function aktifKuliah(Mahasiswa $mahasiswa): array
    {
        $status = $mahasiswa->statusPada()?->loadMissing('tahunAkademik');

        return [
            'status' => $mahasiswa->status->label(),
            'semester_ke' => $status?->semester_ke,
            'tahun_akademik' => $status?->tahunAkademik?->nama,
        ];
    }

    private function lulus(Mahasiswa $mahasiswa): array
    {
        $yudisium = $mahasiswa->yudisium;

        return [
            'tanggal_lulus' => $yudisium?->tanggal_lulus?->toDateString(),
            'nomor_sk' => $yudisium?->nomor_sk,
            'ipk' => $yudisium?->ipk,
            'predikat' => $yudisium?->predikat,
            'total_sks' => $yudisium?->total_sks,
            'judul_tugas_akhir' => $yudisium?->judul_tugas_akhir,
        ];
    }

    private function transkripRingkas(Mahasiswa $mahasiswa): array
    {
        $data = $this->transkrip->data($mahasiswa);

        // The totals only. The full course list is re-rendered from the
        // transcript service at print time — it is the one part of a letter
        // that is genuinely a report rather than an assertion.
        return [
            'total_sks' => $data['totalSks'],
            'ipk' => $data['ipk'],
            'predikat' => $data['predikat'],
        ];
    }

    /**
     * The diploma supplement.
     *
     * Required alongside every diploma by regulation, and the part that is
     * routinely skipped because assembling it by hand is tedious — which is
     * exactly the kind of work that should not be done by hand.
     */
    private function skpi(Mahasiswa $mahasiswa): array
    {
        $jenjang = $mahasiswa->prodi->jenjang;
        $yudisium = $mahasiswa->yudisium;

        $cpl = ProdiCpl::query()
            ->where('prodi_id', $mahasiswa->prodi_id)
            ->orderBy('kategori')
            ->orderBy('urutan')
            ->orderBy('kode')
            ->get()
            ->map(fn (ProdiCpl $c): array => [
                'kode' => $c->kode,
                'kategori' => $c->kategori,
                'kategori_label' => $c->labelKategori(),
                'deskripsi' => $c->deskripsi,
                'deskripsi_en' => $c->deskripsi_en,
            ])
            ->all();

        /*
         * Only verified activities.
         *
         * The supplement is a statement the institution signs. An unverified
         * claim of an internship is the student's word, and putting it on
         * campus letterhead turns it into the campus's.
         */
        $aktivitas = AktivitasMahasiswa::query()
            ->where('mahasiswa_id', $mahasiswa->id)
            ->where('is_verified', true)
            ->orderBy('tanggal_mulai')
            ->get()
            ->map(fn (AktivitasMahasiswa $a): array => [
                'jenis' => $a->jenis->label(),
                'judul' => $a->judul,
                'penyelenggara' => $a->mitra_nama,
                'mulai' => $a->tanggal_mulai?->toDateString(),
                'selesai' => $a->tanggal_selesai?->toDateString(),
            ])
            ->all();

        return [
            'kkni' => $jenjang->jenjangKkni(),
            'jenjang_en' => $jenjang->labelInggris(),
            'gelar' => $mahasiswa->prodi->gelar_panjang,
            'gelar_pendek' => $mahasiswa->prodi->gelar_pendek,
            'bahasa_pengantar' => 'Bahasa Indonesia',
            'tanggal_lulus' => $yudisium?->tanggal_lulus?->toDateString(),
            'nomor_ijazah' => $yudisium?->pesertaWisuda()->value('nomor_ijazah'),
            'ipk' => $yudisium?->ipk,
            'predikat' => $yudisium?->predikat,
            'total_sks' => $yudisium?->total_sks,
            'judul_tugas_akhir' => $yudisium?->judul_tugas_akhir,
            'cpl' => $cpl,
            'aktivitas' => $aktivitas,

            // Stated openly on the document rather than left as a silent gap:
            // a supplement whose outcomes section is empty should say why.
            'cpl_kosong' => $cpl === [],
        ];
    }
}
