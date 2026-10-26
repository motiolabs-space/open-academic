<?php

declare(strict_types=1);

namespace App\Services\Surat;

use App\Enums\JenisPerubahanFeeder;
use App\Enums\JenisSurat;
use App\Enums\StatusSurat;
use App\Exceptions\AturanAkademikException;
use App\Models\Feeder\FeederChangeRequest;
use App\Models\Sdm\Staff;
use App\Models\Surat\Surat;
use App\Services\Feeder\FeederPerubahanService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The letter that carries PDM/PJK requests to PDDIKTI admin (#27b).
 *
 * #27 records a locked-column change the moment it happens and tells the
 * operator it "must be filed at PDDIKTI admin". Filing means a numbered
 * letter on campus letterhead, with the old and new value of every column,
 * the reason, and the supporting documents. This assembles that letter from
 * the requests themselves, numbers it through the ordinary letter sequence,
 * and writes the number back onto each request — so the console, the letter
 * and the verification page all name the same document.
 *
 * One letter per student and per procedure: PDDIKTI admin processes a PDM
 * and a PJK as different forms, but several corrections to one student's
 * identity as lines on the same one.
 */
class PermohonanFeederSurat
{
    /** Asked for with every request, whatever changed. */
    private const LAMPIRAN_UMUM = ['sptjm'];

    /**
     * PDDIKTI column => supporting documents, as keys into
     * lang/id/surat.php (permohonan_feeder.lampiran). What PDDIKTI admin
     * commonly asks for; frozen into each letter, so a campus whose LLDIKTI
     * asks for more amends the catalogue without rewriting what was sent.
     *
     * @var array<string, array<int, string>>
     */
    private const LAMPIRAN = [
        'nim' => ['sk_nim'],
        'id_periode_masuk' => ['bukti_registrasi'],
        'nama_mahasiswa' => ['ktp', 'akta_kelahiran', 'ijazah_sebelumnya'],
        'nama_ibu_kandung' => ['kk', 'akta_kelahiran'],
        'tempat_lahir' => ['ktp', 'akta_kelahiran', 'ijazah_sebelumnya'],
        'tanggal_lahir' => ['ktp', 'akta_kelahiran', 'ijazah_sebelumnya'],
        'id_jenis_keluar' => ['sk_keluar'],
        'tanggal_keluar' => ['sk_yudisium'],
        'id_periode_keluar' => ['sk_yudisium'],
        'tanggal_sk_yudisium' => ['sk_yudisium'],
        'ipk' => ['sk_yudisium', 'transkrip'],
        'nomor_ijazah' => ['ijazah'],
        'keterangan' => ['sk_keluar'],
    ];

    public function __construct(
        private readonly SuratService $surat,
        private readonly FeederPerubahanService $perubahan,
    ) {}

    /**
     * Issues one letter for the given requests and files each of them with it.
     *
     * @param Collection<int, FeederChangeRequest> $permintaan
     */
    public function terbitkan(Collection $permintaan, Staff $staff, string $alasan): Surat
    {
        $this->pastikanDapatDisurati($permintaan);

        /** @var FeederChangeRequest $pertama */
        $pertama = $permintaan->first();

        return DB::transaction(function () use ($permintaan, $pertama, $staff, $alasan): Surat {
            $surat = Surat::create([
                'mahasiswa_id' => $pertama->mahasiswa_id,
                'jenis' => self::jenisSurat($pertama->jenis),
                'status' => StatusSurat::Diajukan,
                'keperluan' => $alasan,
                'diajukan_at' => now(),
            ]);

            // Linked before issuing: the content is assembled from these rows.
            FeederChangeRequest::query()
                ->whereKey($permintaan->map(fn (FeederChangeRequest $p): int => $p->id)->all())
                ->update(['surat_id' => $surat->id]);

            $surat = $this->surat->terbitkan($surat, $staff);

            foreach ($permintaan as $p) {
                $this->perubahan->ajukan($p->refresh(), $staff, $surat->nomor, $p->catatan ?? $alasan);
            }

            return $surat;
        });
    }

    /**
     * The supporting documents for these PDDIKTI columns, in order, once each.
     *
     * @param array<int, string> $kolom
     * @return array<int, string>
     */
    public static function lampiranUntuk(array $kolom): array
    {
        $kunci = self::LAMPIRAN_UMUM;

        foreach ($kolom as $k) {
            array_push($kunci, ...(self::LAMPIRAN[$k] ?? []));
        }

        return array_map(
            fn (string $k): string => __('surat.permohonan_feeder.lampiran.'.$k),
            array_values(array_unique($kunci)),
        );
    }

    public static function jenisSurat(JenisPerubahanFeeder $jenis): JenisSurat
    {
        return match ($jenis) {
            JenisPerubahanFeeder::Pdm => JenisSurat::PermohonanPdm,
            JenisPerubahanFeeder::Pjk => JenisSurat::PermohonanPjk,
        };
    }

    /** @param Collection<int, FeederChangeRequest> $permintaan */
    private function pastikanDapatDisurati(Collection $permintaan): void
    {
        if ($permintaan->isEmpty()) {
            throw new AturanAkademikException(__('surat.permohonan_feeder.galat.kosong'));
        }

        if ($permintaan->pluck('mahasiswa_id')->unique()->count() > 1) {
            throw new AturanAkademikException(__('surat.permohonan_feeder.galat.beda_mahasiswa'));
        }

        if ($permintaan->map(fn (FeederChangeRequest $p): string => $p->jenis->value)->unique()->count() > 1) {
            throw new AturanAkademikException(__('surat.permohonan_feeder.galat.beda_jenis'));
        }

        $tidak = $permintaan->first(fn (FeederChangeRequest $p): bool => !$p->perluSurat());

        if ($tidak !== null) {
            throw new AturanAkademikException(__('surat.permohonan_feeder.galat.tidak_perlu_surat', [
                'kolom' => $tidak->labelKolom(),
            ]));
        }
    }
}
