<?php

declare(strict_types=1);

namespace App\Services\Feeder;

use App\DTOs\Feeder\HasilKlaimLampau;
use App\DTOs\Feeder\KlaimLampauData;
use App\Enums\AlasanAmbiguAdopsi;
use App\Enums\FeederSyncStatus;
use App\Enums\MasalahKlaimLampau;
use App\Enums\StatusAdopsiFeeder;
use App\Enums\StatusLampauFeeder;
use App\Exceptions\FeederException;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Feeder\FeederAdoption;
use App\Models\Feeder\FeederPastStudent;
use App\Models\Feeder\FeederSyncLog;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Staff;
use App\Services\Feeder\Contracts\FeederClientInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Claims the past students PDDIKTI holds for this campus but its Feeder
 * does not, so adoption can take them over.
 *
 * A student reported years ago — before Neo Feeder, or from an installation
 * since replaced — can sit at PDDIKTI without appearing in either list
 * adoption reads. Until the campus's Feeder claims the record, the first
 * sync would try to register that student as new.
 *
 * Claiming is not linking. The web-service call only brings the record into
 * the campus's Feeder; which local student it belongs to is still decided by
 * adoption, through the same matcher and the same manual decisions, right
 * after the claim. There is one way a student gets PDDIKTI's identifiers, and
 * this is not a second one.
 *
 *  1. **Read, match, show.** The list is read per programme and intake term
 *     and each row is judged by the adoption matcher from its side: one
 *     local student (claimable), an ambiguous one (claimable with a reason),
 *     or nobody here (not claimable).
 *
 *  2. **A person selects.** Only rows an operator ticked are claimed; there
 *     is no "claim everything".
 *
 *  3. **Checked before sending.** The list is read again and every selected
 *     row is checked; one problem anywhere and nothing is sent, with every
 *     problem reported at once.
 *
 *  4. **The ledger is told.** Each call writes a `feeder_sync_logs` row. A
 *     success is never sent again; a refusal is recorded with Feeder's
 *     message and the row can be claimed again.
 */
class FeederKlaimLampau
{
    public const ENTITY = 'mahasiswa_lampau';

    public const DIRECTION = 'claim';

    public function __construct(
        private readonly FeederClientInterface $client,
        private readonly FeederAdopsi $adopsi,
        private readonly PencocokMahasiswaFeeder $pencocok,
    ) {}

    /**
     * Reads one programme's past students from PDDIKTI and replaces the report.
     *
     * Claimed rows are not findings and stay, and their registrations are
     * not reported a second time if PDDIKTI still lists them.
     *
     * @return array{batch: string, prodi: string, periode: string, feeder: int, cocok: int, ambigu: int, tanpa_lokal: int, diklaim: int, tidak_lengkap: int}
     */
    public function baca(Prodi $prodi, string $periode): array
    {
        $temuan = $this->temukan($prodi, $periode);

        $diklaim = FeederPastStudent::query()
            ->untuk($prodi->id, $periode)
            ->status(StatusLampauFeeder::Diklaim)
            ->pluck('feeder_registrasi_id')
            ->all();

        $baru = array_diff_key($temuan['evaluasi'], array_flip($diklaim));
        $batch = (string) Str::uuid();
        $baris = array_map(fn (array $e): array => $this->baris($batch, $prodi, $periode, $e), array_values($baru));

        DB::transaction(function () use ($prodi, $periode, $baris): void {
            FeederPastStudent::query()
                ->untuk($prodi->id, $periode)
                ->where('status', '!=', StatusLampauFeeder::Diklaim->value)
                ->delete();

            foreach (array_chunk($baris, 200) as $potongan) {
                FeederPastStudent::insert($potongan);
            }
        });

        $jumlah = collect($baru)->countBy(fn (array $e): string => $e['status']->value);

        return [
            'batch' => $batch,
            'prodi' => $prodi->nama,
            'periode' => $periode,
            'feeder' => count($temuan['evaluasi']),
            'cocok' => $jumlah[StatusLampauFeeder::Cocok->value] ?? 0,
            'ambigu' => $jumlah[StatusLampauFeeder::Ambigu->value] ?? 0,
            'tanpa_lokal' => $jumlah[StatusLampauFeeder::TanpaLokal->value] ?? 0,
            'diklaim' => count($diklaim),
            'tidak_lengkap' => $temuan['tidak_lengkap'],
        ];
    }

    /**
     * The latest report for one programme and intake: claimable first, then
     * ambiguous, then the rest.
     *
     * @return Collection<int, FeederPastStudent>
     */
    public function laporan(Prodi $prodi, string $periode): Collection
    {
        return FeederPastStudent::query()
            ->with(['mahasiswa:id,uuid,nim,nama,feeder_id,feeder_registrasi_id', 'diklaimOleh:id,nama'])
            ->untuk($prodi->id, $periode)
            ->orderByRaw('CASE status WHEN ? THEN 0 WHEN ? THEN 1 WHEN ? THEN 2 ELSE 3 END', [
                StatusLampauFeeder::Cocok->value,
                StatusLampauFeeder::Ambigu->value,
                StatusLampauFeeder::TanpaLokal->value,
            ])
            ->orderBy('nim_feeder')
            ->orderBy('id')
            ->get();
    }

    /**
     * The last claim attempt for each of these rows, keyed by registration.
     *
     * One query for the whole report: a refusal is shown beside its row with
     * Feeder's message and the number of attempts so far.
     *
     * @param Collection<int, FeederPastStudent> $baris
     * @return Collection<string, array{log: FeederSyncLog, percobaan: int}>
     */
    public function percobaanTerakhir(Collection $baris): Collection
    {
        $registrasi = $baris->pluck('feeder_registrasi_id')->filter()->unique()->values();

        if ($registrasi->isEmpty()) {
            return collect();
        }

        return FeederSyncLog::query()
            ->entity(self::ENTITY)
            ->whereIn('feeder_id', $registrasi)
            ->orderByDesc('id')
            ->get(['id', 'feeder_id', 'status', 'error_code', 'error_message', 'attempts', 'created_at'])
            ->groupBy('feeder_id')
            ->map(fn (Collection $logs): array => ['log' => $logs->first(), 'percobaan' => $logs->count()]);
    }

    /**
     * Every problem the selected rows would meet, from a fresh read of PDDIKTI.
     *
     * Nothing is sent. A row an earlier claim already went through is not a
     * problem — it is skipped — and is not looked up again.
     *
     * @return list<array{nim: string|null, nama: string|null, pesan: string}>
     */
    public function periksa(KlaimLampauData $data): array
    {
        [$sisa, , $hilang] = $this->pilih($data);

        if ($sisa->isEmpty()) {
            return $hilang;
        }

        return [...$hilang, ...$this->galat($data, $sisa, $this->temukan($data->prodi, $data->periode)['evaluasi'])];
    }

    /**
     * Claims the selected rows, then lets adoption take the claimed students over.
     *
     * All-or-nothing on the pre-flight check, row by row on Feeder's answer:
     * one refusal does not stop the others, and is claimable again later.
     */
    public function klaim(KlaimLampauData $data, Staff $staff, TahunAkademik $term): HasilKlaimLampau
    {
        [$sisa, $dilewati, $hilang] = $this->pilih($data);

        if ($hilang !== []) {
            return new HasilKlaimLampau(galat: $hilang);
        }

        if ($sisa->isEmpty()) {
            return new HasilKlaimLampau(dilewati: $dilewati);
        }

        $evaluasi = $this->temukan($data->prodi, $data->periode)['evaluasi'];
        $galat = $this->galat($data, $sisa, $evaluasi);

        if ($galat !== []) {
            return new HasilKlaimLampau(galat: $galat);
        }

        $act = (string) config('feeder.adopt.lampau.claim_action');
        $diklaim = [];
        $ditolak = [];

        foreach ($sisa as $baris) {
            $e = $evaluasi[$baris->feeder_registrasi_id];
            [$gagal, $kode, $pesan] = $this->kirim($act, $e['kunci']);

            $this->catat($act, $e, $term, $gagal, $kode, $pesan);

            if ($gagal) {
                $ditolak[] = ['nim' => $e['kandidat']['nim'], 'pesan' => $pesan];

                continue;
            }

            $baris->update([
                'status' => StatusLampauFeeder::Diklaim,
                'alasan' => $e['alasan'],
                'mahasiswa_id' => $e['mahasiswa']->id,
                'catatan' => $data->catatan,
                'diklaim_by_staff_id' => $staff->id,
                'diklaim_at' => now(),
            ]);

            $diklaim[$baris->feeder_registrasi_id] = $e['mahasiswa'];
        }

        if ($diklaim === []) {
            return new HasilKlaimLampau(dilewati: $dilewati, ditolak: $ditolak);
        }

        // The claimed records are now in the lists adoption reads. Adoption
        // decides who they belong to, as for every other student.
        try {
            $adopsi = $this->adopsi->adopsi($data->prodi, $data->periode, $term);
        } catch (FeederException $e) {
            return new HasilKlaimLampau(
                diklaim: count($diklaim),
                dilewati: $dilewati,
                ditolak: $ditolak,
                adopsiGagal: $e->getMessage(),
                belumTeradopsi: array_values(array_map(fn (Mahasiswa $m): string => $m->nim, $diklaim)),
            );
        }

        [$menunggu, $belum] = $this->hasilAdopsi($data, $diklaim);

        return new HasilKlaimLampau(
            diklaim: count($diklaim),
            dilewati: $dilewati,
            ditolak: $ditolak,
            adopsi: $adopsi,
            menungguKeputusan: $menunggu,
            belumTeradopsi: $belum,
        );
    }

    /* ---------------------------------------------------------------------
     | Reading and matching
     |-------------------------------------------------------------------- */

    /**
     * What PDDIKTI lists for one programme and intake, judged row by row.
     *
     * @return array{evaluasi: array<string, array{kandidat: array<string, mixed>, status: StatusLampauFeeder, alasan: AlasanAmbiguAdopsi|null, mahasiswa: Mahasiswa|null, kunci: array<string, mixed>|null}>, tidak_lengkap: int}
     */
    private function temukan(Prodi $prodi, string $periode): array
    {
        if (!config('feeder.enabled')) {
            throw FeederException::dinonaktifkan();
        }

        if (blank($prodi->kode_pddikti)) {
            throw FeederException::prodiTanpaKodePddikti($prodi->nama);
        }

        $rows = $this->adopsi->bacaDaftar('lampau', (string) $prodi->kode_pddikti, $periode);
        $kandidat = $this->adopsi->sebagaiKandidat($rows);

        $mentah = [];

        foreach ($rows as $baris) {
            $reg = trim((string) ($baris['id_registrasi_mahasiswa'] ?? ''));

            if ($reg !== '' && !isset($mentah[$reg])) {
                $mentah[$reg] = $baris;
            }
        }

        $lokal = Mahasiswa::query()
            ->where('prodi_id', $prodi->id)
            ->where('term_masuk', $periode)
            ->orderBy('nim')
            ->get();

        $evaluasi = [];

        // Seen from the Feeder row's side: a clear match with any local
        // student wins over being one of several candidates for another.
        foreach ($this->pencocok->cocokkan($lokal, $kandidat) as $usulan) {
            $cocok = $usulan['status'] === StatusAdopsiFeeder::Diadopsi;

            foreach ($usulan['kandidat'] as $k) {
                $reg = $k['id_registrasi_mahasiswa'];
                $sekarang = $evaluasi[$reg] ?? null;

                if ($sekarang !== null && ($sekarang['status'] === StatusLampauFeeder::Cocok || !$cocok)) {
                    continue;
                }

                $evaluasi[$reg] = [
                    'kandidat' => $k,
                    'status' => $cocok ? StatusLampauFeeder::Cocok : StatusLampauFeeder::Ambigu,
                    'alasan' => $cocok ? null : $usulan['alasan'],
                    'mahasiswa' => $usulan['mahasiswa'],
                ];
            }
        }

        foreach ($kandidat as $reg => $k) {
            $evaluasi[$reg] ??= ['kandidat' => $k, 'status' => StatusLampauFeeder::TanpaLokal, 'alasan' => null, 'mahasiswa' => null];
            $evaluasi[$reg]['kunci'] = $this->kunci($mentah[$reg] ?? []);
        }

        $tidakLengkap = collect($rows)
            ->filter(fn (array $b): bool => blank($b['id_registrasi_mahasiswa'] ?? null) || blank($b['id_mahasiswa'] ?? null))
            ->count();

        return ['evaluasi' => $evaluasi, 'tidak_lengkap' => $tidakLengkap];
    }

    /**
     * The fields the claim act is sent, copied from the listed row, or null
     * when the row lacks one of them.
     *
     * @param array<string, mixed> $baris
     * @return array<string, mixed>|null
     */
    private function kunci(array $baris): ?array
    {
        $kunci = [];

        foreach ((array) config('feeder.adopt.lampau.claim_key', []) as $field) {
            if (blank($baris[$field] ?? null)) {
                return null;
            }

            $kunci[$field] = $baris[$field];
        }

        return $kunci === [] ? null : $kunci;
    }

    /**
     * The selected rows, split into those still to send, how many an earlier
     * claim already covers, and any that are not in this report at all.
     *
     * @return array{0: Collection<int, FeederPastStudent>, 1: int, 2: list<array{nim: string|null, nama: string|null, pesan: string}>}
     */
    private function pilih(KlaimLampauData $data): array
    {
        $baris = FeederPastStudent::query()
            ->untuk($data->prodi->id, $data->periode)
            ->whereIn('uuid', $data->baris)
            ->orderBy('nim_feeder')
            ->orderBy('id')
            ->get();

        $hilang = count($data->baris) > $baris->count()
            ? [['nim' => null, 'nama' => null, 'pesan' => MasalahKlaimLampau::TidakTerdaftarLagi->label()]]
            : [];

        $sudah = FeederSyncLog::query()
            ->entity(self::ENTITY)
            ->where('status', FeederSyncStatus::Success->value)
            ->whereIn('feeder_id', $baris->pluck('feeder_registrasi_id'))
            ->pluck('feeder_id')
            ->all();

        [$dilewati, $sisa] = $baris->partition(
            fn (FeederPastStudent $b): bool => $b->status === StatusLampauFeeder::Diklaim || in_array($b->feeder_registrasi_id, $sudah, true),
        );

        return [$sisa->values(), $dilewati->count(), $hilang];
    }

    /**
     * Every problem among the rows still to send, against a fresh evaluation.
     *
     * @param Collection<int, FeederPastStudent> $sisa
     * @param array<string, array<string, mixed>> $evaluasi
     * @return list<array{nim: string|null, nama: string|null, pesan: string}>
     */
    private function galat(KlaimLampauData $data, Collection $sisa, array $evaluasi): array
    {
        $galat = [];

        foreach ($sisa as $baris) {
            $e = $evaluasi[$baris->feeder_registrasi_id] ?? null;

            $masalah = $e === null
                ? MasalahKlaimLampau::TidakTerdaftarLagi
                : MasalahKlaimLampau::untuk($e['status'], $e['kunci'] !== null, $data->catatan !== null);

            if ($masalah === null && $this->dipakaiLain($baris->feeder_registrasi_id, $e['mahasiswa'])) {
                $masalah = MasalahKlaimLampau::RegistrasiDipakai;
            }

            if ($masalah !== null) {
                $galat[] = [
                    'nim' => $e['kandidat']['nim'] ?? $baris->nim_feeder,
                    'nama' => $e['kandidat']['nama'] ?? $baris->nama_feeder,
                    'pesan' => $masalah->label(),
                ];
            }
        }

        return $galat;
    }

    private function dipakaiLain(string $idRegistrasi, ?Mahasiswa $m): bool
    {
        return Mahasiswa::query()
            ->where('feeder_registrasi_id', $idRegistrasi)
            ->when($m !== null, fn ($q) => $q->whereKeyNot($m->getKey()))
            ->exists();
    }

    /* ---------------------------------------------------------------------
     | Sending
     |-------------------------------------------------------------------- */

    /**
     * @param array<string, mixed> $kunci
     * @return array{0: bool, 1: string|null, 2: string|null}
     */
    private function kirim(string $act, array $kunci): array
    {
        try {
            $response = $this->client->request($act, ['key' => $kunci]);
        } catch (FeederException $e) {
            return [true, (string) ($e->feederErrorCode ?? 'transport'), $e->getMessage()];
        }

        return $response->gagal()
            ? [true, (string) $response->errorCode, $response->errorDesc]
            : [false, null, null];
    }

    /** @param array<string, mixed> $e */
    private function catat(string $act, array $e, TahunAkademik $term, bool $gagal, ?string $kode, ?string $pesan): void
    {
        $reg = $e['kandidat']['id_registrasi_mahasiswa'];

        $sebelumnya = FeederSyncLog::query()
            ->entity(self::ENTITY)
            ->where('feeder_id', $reg)
            ->count();

        FeederSyncLog::create([
            'entity' => self::ENTITY,
            'action' => $act,
            'direction' => self::DIRECTION,
            'local_type' => $e['mahasiswa']->getMorphClass(),
            'local_id' => $e['mahasiswa']->getKey(),
            'feeder_id' => $reg,
            'tahun_akademik_id' => $term->id,
            'payload_hash' => FeederSyncLog::hashPayload($e['kunci']),
            'payload' => ['key' => $e['kunci']],
            'status' => $gagal ? FeederSyncStatus::Failed : FeederSyncStatus::Success,
            'error_code' => $gagal ? $kode : null,
            'error_message' => $gagal ? $pesan : null,
            'attempts' => min(255, $sebelumnya + 1),
            'synced_at' => $gagal ? null : now(),
        ]);
    }

    /**
     * Where each student claimed now ended up after adoption.
     *
     * @param array<string, Mahasiswa> $diklaim registration => student
     * @return array{0: list<string>, 1: list<string>} awaiting a decision, not adopted
     */
    private function hasilAdopsi(KlaimLampauData $data, array $diklaim): array
    {
        $ambigu = FeederAdoption::query()
            ->untuk($data->prodi->id, $data->periode)
            ->status(StatusAdopsiFeeder::Ambigu)
            ->whereIn('mahasiswa_id', collect($diklaim)->map->getKey()->values())
            ->pluck('mahasiswa_id')
            ->all();

        $tertaut = Mahasiswa::query()
            ->whereIn('id', collect($diklaim)->map->getKey()->values())
            ->pluck('feeder_registrasi_id', 'id');

        $menunggu = [];
        $belum = [];

        foreach ($diklaim as $reg => $m) {
            if ($tertaut->get($m->getKey()) === $reg) {
                continue;
            }

            if (in_array($m->getKey(), $ambigu, true)) {
                $menunggu[] = $m->nim;
            } else {
                $belum[] = $m->nim;
            }
        }

        return [$menunggu, $belum];
    }

    /**
     * @param array<string, mixed> $e
     * @return array<string, mixed>
     */
    private function baris(string $batch, Prodi $prodi, string $periode, array $e): array
    {
        $k = $e['kandidat'];
        $m = $e['mahasiswa'];

        return [
            'uuid' => (string) Str::uuid(),
            'batch_id' => $batch,
            'prodi_id' => $prodi->id,
            'periode' => $periode,
            'status' => $e['status']->value,
            'alasan' => $e['alasan']?->value,
            'mahasiswa_id' => $m?->id,
            'feeder_mahasiswa_id' => $k['id_mahasiswa'],
            'feeder_registrasi_id' => $k['id_registrasi_mahasiswa'],
            'nim_feeder' => $k['nim'] === null ? null : Str::limit((string) $k['nim'], 32, ''),
            'nama_feeder' => $k['nama'] === null ? null : Str::limit((string) $k['nama'], 190, ''),
            'tanggal_lahir_feeder' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $k['tanggal_lahir']) ? $k['tanggal_lahir'] : null,
            // The NIK is reduced to whether it matched before anything is stored.
            'nik_cocok' => $m === null ? null : $this->pencocok->nikSama($m->nik, $k['nik']),
            'kunci_klaim' => $e['kunci'] === null ? null : json_encode($e['kunci'], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
