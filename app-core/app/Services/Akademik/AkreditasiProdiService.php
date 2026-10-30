<?php

declare(strict_types=1);

namespace App\Services\Akademik;

use App\DTOs\Akademik\AkreditasiProdiData;
use App\Enums\KeadaanAkreditasi;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\AkreditasiProdi;
use App\Models\Akademik\Prodi;
use App\Models\Sdm\Staff;
use App\Services\Berkas\BerkasService;
use App\Support\Format;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A programme's accreditation, as a history of decrees (ADR-041 §3).
 *
 * Every question asked of it is "on which day?": PISN asks about the
 * graduation day, the staff dashboard about today and the next few months,
 * the LKPS about the day it is filled. So decrees are only ever added — a
 * mistyped one is withdrawn, never edited over — and everything else is
 * read from the loaded history without another query.
 */
class AkreditasiProdiService
{
    public function __construct(private readonly BerkasService $berkas) {}

    public function catat(
        Prodi $prodi,
        AkreditasiProdiData $data,
        ?Staff $pencatat = null,
        ?UploadedFile $dokumen = null,
    ): AkreditasiProdi {
        // The same decree twice is a double submit or a copy-paste, and two
        // identical rows would make "which one is in force" a coin toss.
        $ganda = $prodi->riwayatAkreditasi()
            ->where('nomor_sk', $data->nomorSk)
            ->exists();

        if ($ganda) {
            throw new AturanAkademikException(__('akreditasi.pesan.sk_ganda', ['nomor' => $data->nomorSk]));
        }

        return DB::transaction(function () use ($prodi, $data, $pencatat, $dokumen): AkreditasiProdi {
            $akreditasi = $prodi->riwayatAkreditasi()->create([
                'lembaga' => $data->lembaga,
                'peringkat' => $data->peringkat,
                'nomor_sk' => $data->nomorSk,
                'tanggal_sk' => $data->tanggalSk,
                'tanggal_berlaku' => $data->tanggalBerlaku,
                'tanggal_kedaluwarsa' => $data->tanggalKedaluwarsa,
                'keterangan' => $data->keterangan,
                'dicatat_oleh_staff_id' => $pencatat?->id,
                'dokumen_path' => $dokumen !== null
                    ? $this->berkas->simpan($dokumen, 'akreditasi/'.$prodi->uuid)
                    : null,
            ]);

            $this->segarkanPeringkat($prodi);

            return $akreditasi;
        });
    }

    /**
     * Withdraws a decree entered by mistake. Soft-deleted and logged, so the
     * history shows it was there and who removed it.
     */
    public function tarik(AkreditasiProdi $akreditasi): void
    {
        DB::transaction(function () use ($akreditasi): void {
            $akreditasi->delete();
            $this->segarkanPeringkat($akreditasi->prodi);
        });
    }

    /**
     * Where the programme stands on that day (today when omitted). A decree
     * running out within the configured warning window is "segera
     * kedaluwarsa" unless a later decree has already been recorded.
     */
    public function keadaan(Prodi $prodi, ?CarbonInterface $per = null): KeadaanAkreditasi
    {
        $per ??= now();
        $riwayat = $prodi->riwayatAkreditasi;

        if ($riwayat->isEmpty()) {
            return KeadaanAkreditasi::BelumDicatat;
        }

        $berlaku = $prodi->akreditasiPada($per);

        if ($berlaku === null) {
            return $riwayat->contains(fn (AkreditasiProdi $a): bool => $a->tanggal_kedaluwarsa->lt($per->copy()->startOfDay()))
                ? KeadaanAkreditasi::Kedaluwarsa
                : KeadaanAkreditasi::BelumDicatat;
        }

        if (!$berlaku->peringkat->terakreditasi()) {
            return KeadaanAkreditasi::TidakTerakreditasi;
        }

        $batas = $per->copy()->addMonths($this->bulanPeringatan());
        $penerus = $riwayat->contains(fn (AkreditasiProdi $a): bool => $a->tanggal_berlaku->gt($berlaku->tanggal_berlaku));

        return $berlaku->tanggal_kedaluwarsa->lte($batas) && !$penerus
            ? KeadaanAkreditasi::SegeraKedaluwarsa
            : KeadaanAkreditasi::Berlaku;
    }

    /**
     * The programmes a staff member should look at today, with why.
     *
     * Reads the loaded history only; the caller eager-loads
     * `riwayatAkreditasi` once for the whole list.
     *
     * @param Collection<int, Prodi> $prodi
     * @return Collection<int, array{prodi: Prodi, keadaan: KeadaanAkreditasi, akreditasi: ?AkreditasiProdi}>
     */
    public function perluPerhatian(Collection $prodi): Collection
    {
        return $prodi
            ->filter(fn (Prodi $p): bool => $p->is_active)
            ->map(fn (Prodi $p): array => [
                'prodi' => $p,
                'keadaan' => $this->keadaan($p),
                'akreditasi' => $p->riwayatAkreditasi->first(),
            ])
            ->filter(fn (array $baris): bool => $baris['keadaan']->perluPerhatian())
            ->values();
    }

    /**
     * One line naming the decree in force on that day — what the LKPS
     * identity block and its CSV print. Null when none is.
     */
    public function ringkasan(Prodi $prodi, ?CarbonInterface $per = null): ?string
    {
        $a = $prodi->akreditasiPada($per ?? now());

        if ($a === null) {
            return null;
        }

        return __('akreditasi.ringkasan', [
            'peringkat' => $a->peringkat->label(),
            'lembaga' => $a->lembaga->label(),
            'nomor' => $a->nomor_sk,
            'mulai' => Format::tanggal($a->tanggal_berlaku),
            'sampai' => Format::tanggal($a->tanggal_kedaluwarsa),
        ]);
    }

    public function bulanPeringatan(): int
    {
        return max(0, (int) config('academic.akreditasi.peringatan_bulan', 6));
    }

    /**
     * `prodi.akreditasi` is a cache of the latest decree's grade, for the
     * screens and documents that only print it. This is the only writer.
     */
    private function segarkanPeringkat(Prodi $prodi): void
    {
        $prodi->unsetRelation('riwayatAkreditasi');

        $prodi->update(['akreditasi' => $prodi->riwayatAkreditasi->first()?->peringkat->label()]);
    }
}
