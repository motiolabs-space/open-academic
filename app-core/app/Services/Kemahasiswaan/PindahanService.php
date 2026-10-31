<?php

declare(strict_types=1);

namespace App\Services\Kemahasiswaan;

use App\DTOs\Kemahasiswaan\AsalPendidikanData;
use App\DTOs\Kemahasiswaan\BarisTranskripData;
use App\DTOs\Kemahasiswaan\IdentitasMahasiswaData;
use App\DTOs\Kemahasiswaan\RingkasanPindahan;
use App\Enums\ApplicantStatus;
use App\Enums\JenisDaftar;
use App\Enums\JenisKonversi;
use App\Enums\StatusKonversi;
use App\Enums\StudentStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\KonversiKredit;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Feeder\FeederMapping;
use App\Models\Feeder\FeederRef;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Keuangan\Tagihan;
use App\Models\Pmb\PmbPendaftar;
use App\Services\Akademik\BatasSksCalculator;
use App\Services\Akademik\KonversiService;
use App\Services\Bridge\PenerbitPendaftaran;
use App\Services\Feeder\FeederValidator;
use App\Services\Pmb\NimGenerator;
use App\Services\Pmb\PmbService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Admitting a student who already studied somewhere: transfer and recognition
 * of prior learning.
 *
 * Since #25 the enrolment record could say "transfer from X, Y credits
 * recognised", and nothing but the demo seeder ever said it. This is the intake
 * that does, through two doors — an applicant accepted in a transfer or RPL
 * admissions wave, or somebody the registrar enrols directly — and then hands
 * straight over to recognising their transcript.
 *
 * What it never does is accept a number of recognised credits. That figure is
 * whatever KonversiService has approved, under its ceiling, and it is written
 * onto the record by KonversiService on every decision. A typed total would be
 * one more place for the transcript and the enrolment record to disagree.
 */
class PindahanService
{
    public function __construct(
        private readonly PmbService $pmb,
        private readonly NimGenerator $nim,
        private readonly KonversiService $konversi,
        private readonly BatasSksCalculator $batasSks,
        private readonly FeederValidator $validator,
        private readonly PenerbitPendaftaran $pendaftaran,
    ) {}

    /**
     * The kinds this intake handles: the ones PDDIKTI requires an origin and
     * recognised credits for.
     *
     * @return array<string, string>
     */
    public static function jenisPilihan(): array
    {
        return collect(JenisDaftar::cases())
            ->filter(fn (JenisDaftar $jenis): bool => $jenis->butuhAsal() && !$jenis->internal())
            ->mapWithKeys(fn (JenisDaftar $jenis): array => [$jenis->value => $jenis->label()])
            ->all();
    }

    /**
     * Door one: an applicant accepted through a transfer or RPL wave.
     *
     * The ordinary registration — NIM, account, intake invoice — with the
     * origin written in the same transaction, so there is never a moment in
     * which this person exists as a "new entrant" that a sync could report.
     *
     * @return array{mahasiswa: Mahasiswa, kata_sandi: string, tagihan: ?Tagihan}
     */
    public function daftarkanDariPmb(PmbPendaftar $pendaftar, TahunAkademik $term, AsalPendidikanData $asal): array
    {
        $this->pastikanJenisPindahan($asal);

        return DB::transaction(function () use ($pendaftar, $term, $asal): array {
            $hasil = $this->pmb->daftarUlang($pendaftar, $term);

            $mahasiswa = $hasil['mahasiswa'];
            $mahasiswa->update($asal->kolom());
            $this->catatIntake($mahasiswa, $asal, 'pendaftar PMB '.$pendaftar->nomor_pendaftaran);

            return [...$hasil, 'mahasiswa' => $mahasiswa->refresh()];
        });
    }

    /**
     * Door two: a transfer the registrar enrols without an admissions record.
     *
     * Common enough — a student moving between campuses of one foundation, or
     * arriving mid-year outside any wave. Gets everything registration gives:
     * a NIM from the campus pattern, an account, the intake invoice.
     *
     * @return array{mahasiswa: Mahasiswa, kata_sandi: string, tagihan: ?Tagihan}
     */
    public function daftarkanLangsung(
        IdentitasMahasiswaData $identitas,
        Prodi $prodi,
        TahunAkademik $term,
        AsalPendidikanData $asal,
    ): array {
        $this->pastikanJenisPindahan($asal);

        if (Mahasiswa::withTrashed()->where('email', $identitas->email)->exists()) {
            throw new AturanAkademikException(
                "Surel {$identitas->email} sudah dipakai oleh akun mahasiswa lain.",
            );
        }

        $kataSandi = $this->pmb->kataSandiSementara();

        $hasil = DB::transaction(function () use ($identitas, $prodi, $term, $asal, $kataSandi): array {
            $mahasiswa = Mahasiswa::create([
                ...$identitas->kolom(),
                ...$asal->kolom(),

                'nim' => $this->nim->untuk($prodi, (int) $term->tahun_mulai),
                'password' => Hash::make($kataSandi),
                'prodi_id' => $prodi->id,
                'kurikulum_id' => $this->pmb->kurikulumBerlaku($prodi)?->id,
                'angkatan' => (int) $term->tahun_mulai,
                'term_masuk' => $term->kode,
                'status' => StudentStatus::Aktif,
                'is_active' => true,
            ]);

            $mahasiswa->assignRole('mahasiswa');
            $this->catatIntake($mahasiswa, $asal, 'pendaftaran langsung');

            return [
                'mahasiswa' => $mahasiswa,
                'tagihan' => $this->pmb->terbitkanTagihanAwal($mahasiswa, $term),
            ];
        });

        $this->pendaftaran->umumkan($hasil['mahasiswa']);

        return [...$hasil, 'kata_sandi' => $kataSandi];
    }

    /**
     * Corrects how an existing student entered — including one registered
     * through the ordinary daftar ulang who was in fact a transfer.
     *
     * Refused once PDDIKTI holds the enrolment record: from then on the
     * correction is a Feeder update of that record, and changing it here first
     * would leave the two disagreeing with nothing to say which is right.
     */
    public function perbaruiAsal(Mahasiswa $mahasiswa, AsalPendidikanData $asal): Mahasiswa
    {
        if (filled($mahasiswa->feeder_registrasi_id)) {
            throw new AturanAkademikException(
                'Riwayat pendidikan mahasiswa ini sudah tercatat di PDDIKTI. Perubahan jenis daftar '
                    .'atau asal harus melewati pembaruan riwayat pendidikan di Feeder, bukan disunting di sini.',
            );
        }

        // A registration opened by the re-NIM flow takes its kind and origin
        // from the event that opened it (RegistrasiUlangService); editing them
        // here would leave the event saying one thing and the record another.
        if ($mahasiswa->jenis_daftar?->internal()) {
            throw new AturanAkademikException(__('registrasi_ulang.galat.asal_internal'));
        }

        if ($asal->jenisDaftar->butuhAsal()) {
            $this->pastikanJenisPindahan($asal);
        }

        DB::transaction(function () use ($mahasiswa, $asal): void {
            $mahasiswa->update($asal->kolom() + ['sks_diakui' => null]);

            // Back to the sum of what is approved — or null, for a kind that
            // does not carry one.
            $this->konversi->segarkanSksDiakui($mahasiswa);
        });

        return $mahasiswa->refresh();
    }

    /**
     * Proposes the lines of an origin transcript as conversions, all at once.
     *
     * All or nothing: a transcript is one document, and half of it recorded
     * with the other half refused would leave the assessor unsure which lines
     * still need entering. The rules are KonversiService's own — a course
     * studied here, or already proposed, refuses the whole batch by name.
     *
     * Proposals are not bounded by the ceiling; approvals are. An assessor may
     * well propose more than fits and then choose, and the screen says so.
     *
     * @param array<int, BarisTranskripData> $baris
     * @return Collection<int, KonversiKredit>
     */
    public function ajukanTranskrip(Mahasiswa $mahasiswa, array $baris, ?string $berkasPath = null): Collection
    {
        $jenisDaftar = $mahasiswa->jenis_daftar;

        if (!($jenisDaftar?->butuhAsal() ?? false)) {
            throw new AturanAkademikException(
                'Tetapkan dulu jenis daftar mahasiswa ini sebagai pindahan atau RPL beserta asalnya.',
            );
        }

        if ($baris === []) {
            throw new AturanAkademikException('Transkrip asal belum berisi satu baris pun.');
        }

        $jenis = self::jenisKonversi($jenisDaftar);
        $mataKuliah = MataKuliah::query()
            ->where('prodi_id', $mahasiswa->prodi_id)
            ->whereIn('id', array_map(fn (BarisTranskripData $b): int => $b->mataKuliahId, $baris))
            ->get()
            ->keyBy('id');

        return DB::transaction(fn (): Collection => collect($baris)->map(
            function (BarisTranskripData $satu) use ($mahasiswa, $mataKuliah, $jenis, $berkasPath): KonversiKredit {
                $mk = $mataKuliah->get($satu->mataKuliahId)
                    ?? throw new AturanAkademikException(
                        'Mata kuliah tujuan tidak ada pada kurikulum program studi mahasiswa ini.',
                    );

                return $this->konversi->ajukan(
                    $mahasiswa,
                    $mk,
                    $jenis,
                    $satu->asalNama,
                    $jenis->perluInstitusi() ? $mahasiswa->pt_asal : null,
                    $satu->asalSks,
                    $satu->asalNilai,
                    $berkasPath,
                );
            },
        ));
    }

    /**
     * Transfer and RPL students, newest intake first, with how many of their
     * proposals still wait for a decision.
     *
     * @return LengthAwarePaginator<int, Mahasiswa>
     */
    public function daftar(string $cari = ''): LengthAwarePaginator
    {
        return Mahasiswa::query()
            ->with('prodi')
            ->whereIn('jenis_daftar', array_keys(self::jenisPilihan()))
            ->withCount(['konversiKredit as menunggu_count' => fn ($q) => $q
                ->where('status', StatusKonversi::Diajukan->value)])
            ->cari($cari, ['nama', 'nim'])
            ->orderByDesc('term_masuk')
            ->orderBy('nim')
            ->paginate(25)
            ->withQueryString();
    }

    /**
     * Applicants accepted through a transfer or RPL wave and not yet
     * registered — the first door's queue.
     *
     * @return Collection<int, PmbPendaftar>
     */
    public function menungguRegistrasi(): Collection
    {
        return PmbPendaftar::query()
            ->with(['gelombang', 'prodiDiterima'])
            ->whereNull('mahasiswa_id')
            ->whereIn('status', [ApplicantStatus::Lulus->value, ApplicantStatus::DaftarUlang->value])
            ->whereHas('gelombang', fn ($q) => $q->whereIn('jalur', ['transfer', 'rpl']))
            ->orderBy('nama')
            ->get();
    }

    /**
     * Name suggestions for the origin fields.
     *
     * Institutions come from the PDDIKTI reference pull when it has run, plus
     * what earlier intakes typed; programmes only from earlier intakes, since
     * PDDIKTI has no identifier for another campus's programme and the payload
     * carries it as text either way. Both exist to keep one institution from
     * being spelled four ways across a cohort.
     *
     * @return array<int, string>
     */
    public function sarankanAsal(string $tipe, string $cari, int $batas = 15): array
    {
        $cari = trim($cari);

        if (mb_strlen($cari) < 2) {
            return [];
        }

        $kolom = $tipe === 'prodi' ? 'prodi_asal' : 'pt_asal';

        $lokal = Mahasiswa::query()
            ->whereNotNull($kolom)
            ->whereLike($kolom, '%'.$cari.'%')
            ->distinct()
            ->limit($batas)
            ->pluck($kolom);

        $referensi = $tipe === 'prodi' ? collect() : FeederRef::query()
            ->type('perguruan_tinggi')
            ->whereLike('name', '%'.$cari.'%')
            ->orderBy('name')
            ->limit($batas)
            ->pluck('name');

        return $referensi->merge($lokal)
            ->map(fn (mixed $nama): string => (string) $nama)
            ->unique(fn (string $nama): string => mb_strtolower($nama))
            ->take($batas)
            ->values()
            ->all();
    }

    /**
     * Enrolment kinds this intake offers that have no PDDIKTI code yet.
     *
     * Not a reason to refuse the intake — the campus may not have pulled
     * references yet — but a reason to say now that the sync will refuse it.
     *
     * @return array<int, string> labels
     */
    public function jenisBelumDipetakan(): array
    {
        return collect(JenisDaftar::cases())
            ->filter(fn (JenisDaftar $jenis): bool => $jenis->butuhAsal() && !$jenis->internal()
                && FeederMapping::toFeeder('jenis_daftar', $jenis->value) === null)
            ->map(fn (JenisDaftar $jenis): string => $jenis->label())
            ->values()
            ->all();
    }

    public function ringkasan(Mahasiswa $mahasiswa): RingkasanPindahan
    {
        $mahasiswa->loadMissing('prodi');

        $isu = $this->validator->isuUntuk('mahasiswa', $mahasiswa)
            ->merge($this->validator->isuUntuk('riwayat_pendidikan', $mahasiswa))

            // Order of sync, not a fault in the data: the enrolment cannot go
            // before the biodata, and nothing entered here changes that.
            ->reject(fn (array $i): bool => $i['rule'] === 'biodata_belum_sinkron')
            ->values()
            ->all();

        $ips = $this->batasSks->ipsKonversi($mahasiswa);

        // sisaKuota() is batas - sudahDiakui; computed here from the two so
        // the recognised sum is read once.
        $batas = $this->konversi->batas($mahasiswa);
        $diakui = $this->konversi->sudahDiakui($mahasiswa);

        return new RingkasanPindahan(
            batas: $batas,
            diakui: $diakui,
            sisa: max(0, $batas - $diakui),
            menunggu: (int) KonversiKredit::query()
                ->where('mahasiswa_id', $mahasiswa->id)
                ->where('status', StatusKonversi::Diajukan->value)
                ->sum('sks_diakui'),
            semesterAwal: $this->batasSks->semesterAwal($mahasiswa),
            ipsKonversi: $ips,
            batasKrsAwal: $ips === null
                ? (int) config('academic.krs.default_credits')
                : $this->batasSks->dariIps($ips),
            semesterDiAsal: $mahasiswa->semesterDiAsal(),
            isuFeeder: $isu,
        );
    }

    /**
     * The conversion kind a transcript line takes for this enrolment kind.
     *
     * RPL by acquisition is experience assessed here, with no issuing
     * institution; the other two arrive with a transcript from one.
     */
    public static function jenisKonversi(JenisDaftar $jenis): JenisKonversi
    {
        return $jenis === JenisDaftar::RplPerolehan ? JenisKonversi::Rpl : JenisKonversi::Transfer;
    }

    private function pastikanJenisPindahan(AsalPendidikanData $asal): void
    {
        if (!$asal->jenisDaftar->butuhAsal()) {
            throw new AturanAkademikException(
                "Jenis daftar \"{$asal->jenisDaftar->label()}\" tidak melalui intake pindahan.",
            );
        }

        if (blank($asal->ptAsal) || blank($asal->prodiAsal)) {
            throw new AturanAkademikException(
                "Mahasiswa {$asal->jenisDaftar->label()} wajib membawa perguruan tinggi dan program studi asal.",
            );
        }
    }

    private function catatIntake(Mahasiswa $mahasiswa, AsalPendidikanData $asal, string $pintu): void
    {
        $mahasiswa->recordActivity('transfer_intake', sprintf(
            'Diterima sebagai %s dari %s (%s), lewat %s.',
            $asal->jenisDaftar->label(),
            $asal->ptAsal,
            $asal->prodiAsal,
            $pintu,
        ));
    }
}
