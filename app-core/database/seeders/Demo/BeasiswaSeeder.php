<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Enums\JenisBeasiswa;
use App\Enums\StudentStatus;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Keuangan\Beasiswa;
use App\Models\Keuangan\Tagihan;
use App\Models\Sdm\Staff;
use App\Services\Keuangan\BeasiswaService;
use App\Services\Keuangan\PotonganService;
use Illuminate\Database\Seeder;

/**
 * Three schemes, some recipients, and one discretionary waiver.
 *
 * Built through the services, like the letters and conversions: the rules here
 * are satisfiable with ordinary data, and bypassing them would produce demo
 * invoices whose totals do not add up the way real ones do.
 *
 * Runs after KeuanganSeeder so there are invoices for the awards to land on —
 * which is also the path that matters most, since scholarship selection in
 * practice finishes weeks after billing.
 */
class BeasiswaSeeder extends Seeder
{
    public function run(): void
    {
        $staf = Staff::where('email', 'keuangan@demo.test')->first();
        $term = TahunAkademik::aktif();

        if ($staf === null || $term === null) {
            return;
        }

        $skema = $this->skema();

        $penerima = Mahasiswa::query()
            ->where('status', StudentStatus::Aktif->value)
            ->whereHas('tagihan', fn ($q) => $q->where('tahun_akademik_id', $term->id))
            ->orderBy('id')
            ->limit(6)
            ->get();

        $layanan = app(BeasiswaService::class);

        foreach ($penerima as $i => $mahasiswa) {
            $layanan->tetapkan($skema[$i % count($skema)], $mahasiswa, $term, staff: $staf);
        }

        $this->keringanan($staf, $term, $penerima->pluck('id')->all());
    }

    /** @return array<int, Beasiswa> */
    private function skema(): array
    {
        return [
            Beasiswa::create([
                'kode' => 'BS-PRESTASI',
                'nama' => 'Beasiswa Prestasi Akademik',
                'jenis' => JenisBeasiswa::Internal,
                'persen' => 100,
                'komponen' => ['UKT'],
                'kuota' => 10,
                'keterangan' => 'Membebaskan UKT bagi mahasiswa dengan IPK tertinggi tiap angkatan.',
                'is_active' => true,
            ]),

            Beasiswa::create([
                'kode' => 'BS-KIP',
                'nama' => 'KIP Kuliah',
                'jenis' => JenisBeasiswa::Eksternal,
                'penyandang' => 'Kementerian Pendidikan Tinggi',
                'persen' => 100,
                'kuota' => 40,
                'keterangan' => 'Ditanggung penuh oleh pemerintah, seluruh komponen biaya.',
                'is_active' => true,
            ]),

            Beasiswa::create([
                'kode' => 'BS-MITRA',
                'nama' => 'Beasiswa Mitra Industri',
                'jenis' => JenisBeasiswa::Eksternal,
                'penyandang' => 'PT Nusantara Digital Teknologi',
                'persen' => null,
                'nominal' => 2_500_000,
                'kuota' => 5,
                'keterangan' => 'Nominal tetap per semester dari mitra industri.',
                'is_active' => true,
            ]),
        ];
    }

    /**
     * One hardship waiver, on a student who has no scholarship.
     *
     * The discretionary path is the one with no scheme behind it, and therefore
     * the one whose written reason is the only record of why the campus charged
     * somebody less.
     */
    private function keringanan(Staff $staf, TahunAkademik $term, array $sudahDapat): void
    {
        $tagihan = Tagihan::query()
            ->where('tahun_akademik_id', $term->id)
            ->whereNotIn('mahasiswa_id', $sudahDapat)
            ->orderBy('id')
            ->first();

        if ($tagihan === null) {
            return;
        }

        app(PotonganService::class)->keringanan(
            $tagihan,
            min(1_500_000, (int) $tagihan->total),
            'Keringanan atas musibah keluarga, sesuai surat permohonan dan berita acara verifikasi.',
            $staf,
        );
    }
}
