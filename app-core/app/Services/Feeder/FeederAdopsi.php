<?php

declare(strict_types=1);

namespace App\Services\Feeder;

use App\Enums\AlasanAmbiguAdopsi;
use App\Enums\FeederSyncStatus;
use App\Enums\StatusAdopsiFeeder;
use App\Exceptions\FeederException;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Feeder\FeederAdoption;
use App\Models\Feeder\FeederSyncLog;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Staff;
use App\Services\Feeder\Contracts\FeederClientInterface;
use App\Services\Feeder\Mappers\FeederMapper;
use App\Services\Feeder\Mappers\MahasiswaMapper;
use App\Services\Feeder\Mappers\RiwayatPendidikanMapper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Takes over the students PDDIKTI already holds, without sending anything.
 *
 * A campus that moves here from another SIAKAD has every one of its students
 * at PDDIKTI already. The biodata mapper only knows how to insert, so the
 * first sync would try to register all of them a second time: refused at
 * best, a duplicate person at worst. Adoption reads the students back per
 * programme and intake term, matches them to local rows, and gives each
 * local row the identifiers PDDIKTI knows it by.
 *
 * Four rules keep it from adopting the wrong person:
 *
 *  1. **NIM finds, NIK confirms.** A NIM is the campus's number for an
 *     enrolment; a NIK is the state's number for a person. Matching on NIM
 *     alone would adopt a reused number; on NIK alone, a student under a NIM
 *     this campus no longer uses. A name never overrules a NIK — names are
 *     where typos live — and only stands in for one that is missing.
 *
 *  2. **Doubt is never resolved by the matcher.** Two candidates, a NIM
 *     whose NIK disagrees, a NIK under another NIM: each is reported as
 *     ambiguous and left for a person, who links or separates it with a
 *     reason. The validator then
 *     refuses the biodata push until they do.
 *
 *  3. **The ledger is told.** An adopted student gets a ledger row per entity
 *     with the hash of today's local payload, so the next push records
 *     Skipped instead of Insert — the same idempotency as a row this
 *     application sent itself. A student who already has a successful ledger
 *     row gets nothing new: adoption must not claim PDDIKTI holds a payload
 *     that was only ever sent in an older version.
 *
 *  4. **No NIK leaves the matcher.** It is compared in memory and recorded
 *     as matched or not, never stored in the report or shown on its screen.
 *
 * Rules 1 and 2 live in PencocokMahasiswaFeeder, which claiming past
 * students (FeederKlaimLampau) shares: a claimed student reaches this
 * report through the same matcher, not a second one.
 */
class FeederAdopsi
{
    public const DIRECTION = 'adopt';

    public function __construct(
        private readonly FeederClientInterface $client,
        private readonly MahasiswaMapper $biodata,
        private readonly RiwayatPendidikanMapper $riwayat,
        private readonly PencocokMahasiswaFeeder $pencocok,
    ) {}

    /**
     * Matches one programme's intake against PDDIKTI and adopts the clear cases.
     *
     * Safe to repeat: a student adopted by an earlier run is reported again
     * as adopted and gets no second ledger row, and a person's decision on an
     * ambiguous row is left exactly as it was made.
     *
     * @return array{batch: string, prodi: string, periode: string, lokal: int, feeder: int, diadopsi: int, sudah_tertaut: int, hanya_lokal: int, hanya_feeder: int, ambigu: int, diputuskan: int}
     */
    public function adopsi(Prodi $prodi, string $periode, TahunAkademik $term): array
    {
        if (!config('feeder.enabled')) {
            throw FeederException::dinonaktifkan();
        }

        if (blank($prodi->kode_pddikti)) {
            throw FeederException::prodiTanpaKodePddikti($prodi->nama);
        }

        $kandidat = $this->bacaFeeder((string) $prodi->kode_pddikti, $periode);

        $keputusan = FeederAdoption::query()
            ->untuk($prodi->id, $periode)
            ->whereIn('status', [StatusAdopsiFeeder::Ditautkan->value, StatusAdopsiFeeder::Dipisahkan->value])
            ->get(['mahasiswa_id', 'feeder_registrasi_id']);

        $lokal = Mahasiswa::query()
            ->with('prodi')
            ->where('prodi_id', $prodi->id)
            ->where('term_masuk', $periode)
            ->whereNotIn('id', $keputusan->pluck('mahasiswa_id')->filter())
            ->orderBy('nim')
            ->get();

        // A registration someone already linked by hand belongs to that
        // student; it is neither a candidate for anyone else nor "only at
        // PDDIKTI".
        $kandidat = array_diff_key($kandidat, array_flip($keputusan->pluck('feeder_registrasi_id')->filter()->all()));

        $usulan = $this->pencocok->cocokkan($lokal, $kandidat);

        $batch = (string) Str::uuid();
        $baris = [];
        $hitung = ['diadopsi' => 0, 'sudah_tertaut' => 0, 'hanya_lokal' => 0, 'ambigu' => 0];
        $terpakai = [];

        foreach ($usulan as $satu) {
            /** @var Mahasiswa $m */
            $m = $satu['mahasiswa'];

            foreach ($satu['kandidat'] as $k) {
                $terpakai[$k['id_registrasi_mahasiswa']] = true;
            }

            if ($satu['status'] === StatusAdopsiFeeder::Diadopsi) {
                $k = $satu['kandidat'][0];
                $baru = $this->terapkan($m, $k, $term, false);
                $hitung[$baru ? 'diadopsi' : 'sudah_tertaut']++;
            } else {
                $hitung[$satu['status']->value]++;
            }

            $baris[] = $this->baris($batch, $prodi, $periode, $satu['status'], $satu['alasan'], $m, $satu['kandidat']);
        }

        $hanyaFeeder = array_diff_key($kandidat, $terpakai);

        foreach ($hanyaFeeder as $k) {
            $baris[] = $this->baris($batch, $prodi, $periode, StatusAdopsiFeeder::HanyaFeeder, null, null, [$k]);
        }

        /*
         * The previous run's automatic findings are replaced, not added to —
         * the same reasoning as reconciliation: a run that finds everything
         * resolved must leave a screen that says so. A person's decisions are
         * not findings and stay.
         */
        DB::transaction(function () use ($prodi, $periode, $baris): void {
            FeederAdoption::query()
                ->untuk($prodi->id, $periode)
                ->whereNotIn('status', [StatusAdopsiFeeder::Ditautkan->value, StatusAdopsiFeeder::Dipisahkan->value])
                ->delete();

            foreach (array_chunk($baris, 200) as $potongan) {
                FeederAdoption::insert($potongan);
            }
        });

        return [
            'batch' => $batch,
            'prodi' => $prodi->nama,
            'periode' => $periode,
            'lokal' => $lokal->count(),
            'feeder' => count($kandidat),
            'diadopsi' => $hitung['diadopsi'],
            'sudah_tertaut' => $hitung['sudah_tertaut'],
            'hanya_lokal' => $hitung['hanya_lokal'],
            'hanya_feeder' => count($hanyaFeeder),
            'ambigu' => $hitung['ambigu'],
            'diputuskan' => $keputusan->count(),
        ];
    }

    /**
     * Links an ambiguous student to one of its candidates, on a person's word.
     *
     * Nothing is sent: the identifiers are recorded and the ledger is told,
     * exactly as for an automatic adoption. The reason is required and kept
     * on the row, and the change to the row and to the student are both in
     * the activity log under the operator's name.
     */
    public function tautkan(FeederAdoption $temuan, string $idRegistrasi, string $catatan, Staff $staff, TahunAkademik $term): FeederAdoption
    {
        $this->pastikanAmbigu($temuan);

        $kandidat = $temuan->kandidatDengan($idRegistrasi) ?? throw FeederException::kandidatTidakDikenal($idRegistrasi);

        $mahasiswa = $temuan->mahasiswa()->with('prodi')->firstOrFail();

        $dipakai = Mahasiswa::query()
            ->where('feeder_registrasi_id', $idRegistrasi)
            ->whereKeyNot($mahasiswa->getKey())
            ->value('nim');

        if ($dipakai !== null) {
            throw FeederException::registrasiSudahDipakai($dipakai);
        }

        if ($this->pencocok->tertautLain($mahasiswa, $kandidat)) {
            throw FeederException::tertautLain($mahasiswa->nim);
        }

        return DB::transaction(function () use ($temuan, $kandidat, $mahasiswa, $catatan, $staff, $term): FeederAdoption {
            $this->terapkan($mahasiswa, $kandidat, $term, true);

            $temuan->update([
                'status' => StatusAdopsiFeeder::Ditautkan,
                'feeder_mahasiswa_id' => $kandidat['id_mahasiswa'],
                'feeder_registrasi_id' => $kandidat['id_registrasi_mahasiswa'],
                'nim_feeder' => $kandidat['nim'] ?? null,
                'nama_feeder' => $kandidat['nama'] ?? null,
                'catatan' => $catatan,
                'diputuskan_by_staff_id' => $staff->id,
                'diputuskan_at' => now(),
            ]);

            return $temuan;
        });
    }

    /**
     * Declares an ambiguous student to be none of its candidates.
     *
     * The validator stops refusing their biodata, so the next sync registers
     * them as a new student. That is the point of the decision — and why it
     * needs a reason as much as a link does.
     */
    public function pisahkan(FeederAdoption $temuan, string $catatan, Staff $staff): FeederAdoption
    {
        $this->pastikanAmbigu($temuan);

        $temuan->update([
            'status' => StatusAdopsiFeeder::Dipisahkan,
            'catatan' => $catatan,
            'diputuskan_by_staff_id' => $staff->id,
            'diputuskan_at' => now(),
        ]);

        return $temuan;
    }

    /**
     * The latest report for one programme and intake, ambiguous rows first.
     *
     * @return Collection<int, FeederAdoption>
     */
    public function laporan(Prodi $prodi, string $periode): Collection
    {
        return FeederAdoption::query()
            ->with(['mahasiswa:id,uuid,nim,nama,feeder_id,feeder_registrasi_id', 'diputuskanOleh:id,nama'])
            ->untuk($prodi->id, $periode)
            ->orderByRaw('CASE status WHEN ? THEN 0 WHEN ? THEN 1 WHEN ? THEN 2 ELSE 3 END', [
                StatusAdopsiFeeder::Ambigu->value,
                StatusAdopsiFeeder::HanyaFeeder->value,
                StatusAdopsiFeeder::HanyaLokal->value,
            ])
            ->orderBy('nim_feeder')
            ->orderBy('id')
            ->get();
    }

    /* ---------------------------------------------------------------------
     | Reading PDDIKTI
     |-------------------------------------------------------------------- */

    /**
     * The registrations PDDIKTI holds for one programme and intake, each with
     * whatever the person list adds to it, keyed by id_registrasi_mahasiswa.
     *
     * Two lists, because neither carries everything on every Feeder build:
     * the enrolment list has the NIM and the registration, the person list
     * has the NIK. Joined on id_mahasiswa; a row that ends up without both
     * identifiers cannot be adopted and is not offered as a candidate.
     *
     * @return array<string, array{id_registrasi_mahasiswa: string, id_mahasiswa: string, nim: string|null, nik: string|null, nama: string|null, tanggal_lahir: string|null}>
     */
    private function bacaFeeder(string $idProdi, string $periode): array
    {
        $hasil = [];

        foreach ($this->bacaDaftar('riwayat', $idProdi, $periode) as $baris) {
            $this->gabung($hasil, $baris);
        }

        $orang = collect($this->bacaDaftar('biodata', $idProdi, $periode));

        foreach ($orang as $baris) {
            $this->gabung($hasil, $baris);
        }

        // Person rows that carry no registration of their own fill in the
        // registrations of the same person.
        $perOrang = $orang->filter(fn (array $b): bool => filled($b['id_mahasiswa'] ?? null))->keyBy('id_mahasiswa');

        foreach ($hasil as $kunci => $k) {
            $p = $perOrang->get($k['id_mahasiswa'] ?? '');

            if ($p !== null) {
                $hasil[$kunci] = $this->lengkapi($k, $p);
            }
        }

        return $this->lengkap($hasil);
    }

    /**
     * One list's rows as matcher candidates, keyed by id_registrasi_mahasiswa.
     *
     * For a list that carries the NIM, the name and — when the Feeder build
     * has it — the NIK on one row, such as the past students a campus can
     * claim. Rows sharing a registration are merged; a row without both
     * identifiers is no candidate.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, array{id_registrasi_mahasiswa: string, id_mahasiswa: string, nim: string|null, nik: string|null, nama: string|null, tanggal_lahir: string|null}>
     */
    public function sebagaiKandidat(array $rows): array
    {
        $hasil = [];

        foreach ($rows as $baris) {
            $this->gabung($hasil, $baris);
        }

        return $this->lengkap($hasil);
    }

    /**
     * @param array<string, array<string, mixed>> $hasil
     * @return array<string, array<string, mixed>>
     */
    private function lengkap(array $hasil): array
    {
        return array_filter(
            $hasil,
            fn (array $k): bool => filled($k['id_registrasi_mahasiswa']) && filled($k['id_mahasiswa']),
        );
    }

    /**
     * @param array<string, array<string, mixed>> $hasil
     * @param array<string, mixed> $baris
     */
    private function gabung(array &$hasil, array $baris): void
    {
        $idRegistrasi = $this->teks($baris['id_registrasi_mahasiswa'] ?? null);

        if ($idRegistrasi === null) {
            return;
        }

        $hasil[$idRegistrasi] = $this->lengkapi($hasil[$idRegistrasi] ?? [
            'id_registrasi_mahasiswa' => $idRegistrasi,
            'id_mahasiswa' => null,
            'nim' => null,
            'nik' => null,
            'nama' => null,
            'tanggal_lahir' => null,
        ], $baris);
    }

    /**
     * Fills the candidate's empty fields from one Feeder row.
     *
     * @param array<string, mixed> $kandidat
     * @param array<string, mixed> $baris
     * @return array<string, mixed>
     */
    private function lengkapi(array $kandidat, array $baris): array
    {
        $dari = [
            'id_mahasiswa' => $baris['id_mahasiswa'] ?? null,
            'nim' => $baris['nim'] ?? $baris['nipd'] ?? null,
            'nik' => $baris['nik'] ?? null,
            'nama' => $baris['nama_mahasiswa'] ?? null,
            'tanggal_lahir' => isset($baris['tanggal_lahir']) ? substr((string) $baris['tanggal_lahir'], 0, 10) : null,
        ];

        foreach ($dari as $field => $nilai) {
            if (blank($kandidat[$field] ?? null) && filled($nilai)) {
                $kandidat[$field] = $this->teks($nilai);
            }
        }

        return $kandidat;
    }

    /**
     * Every row of one list under `feeder.adopt`, page by page.
     *
     * A Feeder error stops the run, as in reconciliation: an act name this
     * build does not know returns no rows, and no rows would read as "every
     * student here is new" — the one conclusion adoption exists to prevent.
     *
     * @return array<int, array<string, mixed>>
     */
    public function bacaDaftar(string $daftar, string $idProdi, string $periode): array
    {
        $setelan = (array) config("feeder.adopt.{$daftar}");
        $act = (string) ($setelan['get_action'] ?? '');
        $filter = array_map(fn (mixed $nilai): mixed => match ($nilai) {
            ':prodi' => $idProdi,
            ':periode' => $periode,
            default => $nilai,
        }, (array) ($setelan['filter'] ?? []));

        $ukuran = (int) config('feeder.reconcile_page_size', 500);
        $hasil = [];

        for ($offset = 0, $halaman = 0; $halaman < 1000; $halaman++, $offset += $ukuran) {
            $response = $this->client->get($act, $filter, $ukuran, $offset);

            if ($response->gagal()) {
                throw FeederException::ditolak($act, $response->errorCode, $response->errorDesc);
            }

            $rows = $response->rows();
            array_push($hasil, ...$rows);

            if (count($rows) < $ukuran) {
                break;
            }
        }

        return $hasil;
    }

    private function teks(mixed $nilai): ?string
    {
        return blank($nilai) ? null : trim((string) $nilai);
    }

    /* ---------------------------------------------------------------------
     | Writing
     |-------------------------------------------------------------------- */

    /**
     * Records PDDIKTI's identifiers on the student and tells the ledger.
     *
     * Returns whether anything changed — false for a student an earlier run
     * (or an earlier push) already linked to the same registration.
     *
     * @param array<string, mixed> $kandidat
     */
    private function terapkan(Mahasiswa $m, array $kandidat, TahunAkademik $term, bool $diaudit): bool
    {
        $baru = blank($m->feeder_id) || blank($m->feeder_registrasi_id);

        if ($baru) {
            $m->forceFill([
                'feeder_id' => $kandidat['id_mahasiswa'],
                'feeder_registrasi_id' => $kandidat['id_registrasi_mahasiswa'],
                'feeder_synced_at' => now(),
            ]);

            // Bulk adoption is accounted for by the ledger and the report;
            // a link made by hand is also an audited change to the student.
            $diaudit ? $m->save() : $m->saveQuietly();
        }

        $this->catat('mahasiswa', $this->biodata, $m, $term, (string) config('feeder.adopt.biodata.get_action'));
        $this->catat('riwayat_pendidikan', $this->riwayat, $m, $term, (string) config('feeder.adopt.riwayat.get_action'));

        return $baru;
    }

    /**
     * One ledger row, only for a student the ledger has never seen succeed.
     *
     * A student with an earlier success already has the hash of what was
     * last accepted; replacing it with today's would claim PDDIKTI holds a
     * payload nobody sent, and the next push would skip a real change.
     */
    private function catat(string $entity, FeederMapper $mapper, Mahasiswa $m, TahunAkademik $term, string $act): void
    {
        $sudah = FeederSyncLog::query()
            ->entity($entity)
            ->where('local_type', $m->getMorphClass())
            ->where('local_id', $m->getKey())
            ->where('status', FeederSyncStatus::Success->value)
            ->exists();

        if ($sudah) {
            return;
        }

        $payload = $mapper->payload($m);

        FeederSyncLog::create([
            'entity' => $entity,
            'action' => $act,
            'direction' => self::DIRECTION,
            'local_type' => $m->getMorphClass(),
            'local_id' => $m->getKey(),
            'feeder_id' => $mapper->feederId($m),
            'tahun_akademik_id' => $term->id,
            'payload_hash' => FeederSyncLog::hashPayload($payload),
            'payload' => $payload,
            'status' => FeederSyncStatus::Success,
            'attempts' => 0,
            'synced_at' => now(),
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $kandidat
     * @return array<string, mixed>
     */
    private function baris(
        string $batch,
        Prodi $prodi,
        string $periode,
        StatusAdopsiFeeder $status,
        ?AlasanAmbiguAdopsi $alasan,
        ?Mahasiswa $m,
        array $kandidat,
    ): array {
        $nik = $m?->nik;

        // The NIK is reduced to whether it matched before anything is stored.
        $simpan = array_map(fn (array $k): array => [
            'id_mahasiswa' => $k['id_mahasiswa'],
            'id_registrasi_mahasiswa' => $k['id_registrasi_mahasiswa'],
            'nim' => $k['nim'],
            'nama' => $k['nama'],
            'tanggal_lahir' => $k['tanggal_lahir'],
            'nik_cocok' => $this->pencocok->nikSama($nik, $k['nik']),
        ], $kandidat);

        $tunggal = count($kandidat) === 1 ? $kandidat[0] : null;

        return [
            'uuid' => (string) Str::uuid(),
            'batch_id' => $batch,
            'prodi_id' => $prodi->id,
            'periode' => $periode,
            'status' => $status->value,
            'alasan' => $alasan?->value,
            'mahasiswa_id' => $m?->id,
            'feeder_mahasiswa_id' => $tunggal['id_mahasiswa'] ?? null,
            'feeder_registrasi_id' => $tunggal['id_registrasi_mahasiswa'] ?? null,
            'nim_feeder' => $tunggal === null ? null : Str::limit((string) $tunggal['nim'], 32, ''),
            'nama_feeder' => $tunggal === null ? null : Str::limit((string) $tunggal['nama'], 190, ''),
            'kandidat' => json_encode($simpan, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function pastikanAmbigu(FeederAdoption $temuan): void
    {
        if ($temuan->status !== StatusAdopsiFeeder::Ambigu || $temuan->mahasiswa_id === null) {
            throw FeederException::adopsiBukanAmbigu();
        }
    }
}
