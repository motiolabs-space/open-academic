<?php

declare(strict_types=1);

namespace App\Services\Feeder;

use App\DTOs\Feeder\LaporanKamusEntitas;
use App\DTOs\Feeder\TemuanKamus;
use App\Enums\JenisTemuanKamus;
use App\Exceptions\FeederException;
use App\Models\Feeder\FeederDictionaryAct;
use App\Models\Feeder\FeederDictionaryRun;
use App\Models\Sdm\Staff;
use App\Services\Feeder\Contracts\FeederClientInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Checks what the mappers send against the campus Feeder's own dictionary.
 *
 * Some field names cannot be settled from anything public — the mobile
 * number on the biodata, the diploma date on Lulus/DO (ROADMAP #52, #53). A
 * live Neo Feeder answers GetDictionary for each act it serves; this reads
 * that answer for every act we send, stores it, and lays it beside each
 * mapper's field list.
 *
 * It reports and changes nothing. A field Feeder does not know, a required
 * one we never send, and the pair of them that look like one field renamed
 * are shown to a person, who fixes the mapper or the config — or decides
 * the reading is the one that is wrong.
 */
class PemeriksaKamusFeeder
{
    /**
     * Names that mean the same field across Feeder builds and SIAKADs.
     * Matching on spelling alone would never pair `telepon_seluler` with
     * `handphone`.
     */
    private const SINONIM = [
        ['telepon_seluler', 'handphone', 'hp', 'no_hp', 'nomor_hp', 'no_handphone', 'nomor_handphone', 'ponsel', 'nomor_ponsel', 'telepon_genggam'],
    ];

    /** Below this, two names are not offered as one field renamed. */
    private const AMBANG_MIRIP = 0.8;

    public function __construct(private readonly FeederClientInterface $client) {}

    /**
     * The acts this application sends, in sync order, each once.
     *
     * @return array<int, string>
     */
    public function aksi(): array
    {
        return collect(FeederSyncService::MAPPERS)
            ->map(fn (string $kelas): string => app($kelas)->act())
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Reads the dictionary of every act (or the ones named) and stores it.
     *
     * One act failing does not stop the others: an act the build does not
     * serve is itself a finding. A transport failure is recorded the same
     * way, against the act it happened on.
     *
     * @param array<int, string>|null $hanya
     */
    public function periksa(?Staff $staff = null, ?string $versi = null, ?array $hanya = null): FeederDictionaryRun
    {
        if (!config('feeder.enabled')) {
            throw FeederException::dinonaktifkan();
        }

        $aksi = $hanya !== null && $hanya !== [] ? array_values(array_intersect($this->aksi(), $hanya)) : $this->aksi();
        $versiFeeder = null;
        $baris = [];

        foreach ($aksi as $act) {
            [$hasil, $versiBalasan] = $this->baca($act);
            $versiFeeder ??= $versiBalasan;
            $baris[] = $hasil;
        }

        return DB::transaction(function () use ($baris, $staff, $versi, $versiFeeder): FeederDictionaryRun {
            $run = FeederDictionaryRun::create([
                'versi_feeder' => $versiFeeder ?? (filled($versi) ? mb_substr(trim($versi), 0, 32) : null),
                'driver' => (string) config('feeder.driver'),
                'jumlah_aksi' => count($baris),
                'jumlah_gagal' => count(array_filter($baris, fn (array $b): bool => $b['galat'] !== null)),
                'dijalankan_by_staff_id' => $staff?->id,
                'diperiksa_at' => now(),
            ]);

            $run->aksi()->createMany($baris);

            return $run->load('aksi');
        });
    }

    public function terakhir(): ?FeederDictionaryRun
    {
        return FeederDictionaryRun::query()->with(['aksi', 'dijalankanOleh'])->latest('id')->first();
    }

    /**
     * Every entity checked in a reading, measured against it.
     *
     * Derived on each call rather than stored: a mapper fixed after the
     * reading is measured against the same dictionary without asking Feeder
     * again.
     *
     * @return Collection<int, LaporanKamusEntitas>
     */
    public function laporan(FeederDictionaryRun $run): Collection
    {
        $kamus = $run->aksi->keyBy('act');

        return collect(FeederSyncService::MAPPERS)
            ->map(function (string $kelas, string $entity) use ($kamus): ?LaporanKamusEntitas {
                $mapper = app($kelas);
                /** @var FeederDictionaryAct|null $aksi */
                $aksi = $kamus->get($mapper->act());

                if ($aksi === null) {
                    return null;
                }

                $label = (string) config("feeder.entities.{$entity}.label", $entity);

                if (!$aksi->terbaca()) {
                    return new LaporanKamusEntitas(
                        $entity, $label, $aksi->act, false,
                        [new TemuanKamus(JenisTemuanKamus::AksiTidakTerbaca, keterangan: $aksi->galat)],
                        galat: $aksi->galat,
                    );
                }

                [$temuan, $opsional] = $this->bandingkan($mapper->medan(), $aksi->medan ?? []);

                return new LaporanKamusEntitas($entity, $label, $aksi->act, true, $temuan, $opsional);
            })
            ->filter()
            ->values();
    }

    /**
     * What one mapper sends against what one act's dictionary lists.
     *
     * Renames are paired first, best match first and each name used once,
     * so a field that was merely renamed is reported once as a rename rather
     * than twice as "unknown" and "missing".
     *
     * @param array<int, string> $kita
     * @param array<int, array{nama: string, wajib: bool, kunci: bool, tipe?: ?string, keterangan?: ?string}> $kamus
     * @return array{0: array<int, TemuanKamus>, 1: array<int, string>} findings, and optional fields we do not send
     */
    public function bandingkan(array $kita, array $kamus): array
    {
        $diKamus = collect($kamus)->keyBy('nama');
        $tidakDikenal = array_values(array_diff($kita, $diKamus->keys()->all()));
        $tidakDikirim = $diKamus->except($kita);

        $pasangan = [];

        foreach ($tidakDikenal as $nama) {
            foreach ($tidakDikirim as $kandidat => $medan) {
                $skor = $this->kemiripan($nama, (string) $kandidat);

                if ($skor >= self::AMBANG_MIRIP) {
                    $pasangan[] = [$skor, $nama, (string) $kandidat];
                }
            }
        }

        usort($pasangan, fn (array $a, array $b): int => $b[0] <=> $a[0] ?: strcmp($a[1], $b[1]));

        $temuan = [];
        $terpakai = [];

        foreach ($pasangan as [, $nama, $kandidat]) {
            if (isset($terpakai['kita:'.$nama]) || isset($terpakai['feeder:'.$kandidat])) {
                continue;
            }

            $terpakai['kita:'.$nama] = $terpakai['feeder:'.$kandidat] = true;
            $temuan[] = new TemuanKamus(JenisTemuanKamus::BedaNama, $nama, $kandidat, (bool) $tidakDikirim[$kandidat]['wajib']);
        }

        foreach ($tidakDikenal as $nama) {
            if (!isset($terpakai['kita:'.$nama])) {
                $temuan[] = new TemuanKamus(JenisTemuanKamus::TidakDikenal, kita: $nama);
            }
        }

        $opsional = [];

        foreach ($tidakDikirim as $nama => $medan) {
            if (isset($terpakai['feeder:'.$nama])) {
                continue;
            }

            // A primary key is what Feeder hands back, never what it is sent.
            if ($medan['wajib'] && !$medan['kunci']) {
                $temuan[] = new TemuanKamus(JenisTemuanKamus::WajibTidakDikirim, feeder: (string) $nama, wajib: true, keterangan: $medan['keterangan'] ?? null);
            } elseif (!$medan['kunci']) {
                $opsional[] = (string) $nama;
            }
        }

        return [$temuan, $opsional];
    }

    /**
     * How likely two names are one field: 1 for known synonyms, high when
     * one name's words are all in the other (`nama_ibu` / `nama_ibu_kandung`),
     * otherwise by spelling.
     */
    public function kemiripan(string $a, string $b): float
    {
        $a = mb_strtolower($a);
        $b = mb_strtolower($b);

        foreach (self::SINONIM as $kelompok) {
            if (in_array($a, $kelompok, true) && in_array($b, $kelompok, true)) {
                return 1.0;
            }
        }

        $kataA = explode('_', $a);
        $kataB = explode('_', $b);

        if (min(count($kataA), count($kataB)) >= 2
            && (array_diff($kataA, $kataB) === [] || array_diff($kataB, $kataA) === [])) {
            return 0.85;
        }

        similar_text($a, $b, $persen);

        return $persen / 100;
    }

    /**
     * One act's dictionary, as a row to store, plus any version it reports.
     *
     * @return array{0: array{act: string, medan: ?array, galat_kode: ?string, galat: ?string}, 1: ?string}
     */
    private function baca(string $act): array
    {
        try {
            $response = $this->client->request(
                (string) config('feeder.kamus.act', 'GetDictionary'),
                [(string) config('feeder.kamus.parameter', 'fungsi') => $act],
            );
        } catch (FeederException $e) {
            return [['act' => $act, 'medan' => null, 'galat_kode' => 'transport', 'galat' => $e->getMessage()], null];
        }

        if ($response->gagal()) {
            return [['act' => $act, 'medan' => null, 'galat_kode' => (string) $response->errorCode, 'galat' => $response->errorDesc ?: __('feeder.kamus.galat.ditolak')], null];
        }

        $medan = KamusFeeder::uraikan($response->data, $act);

        return [[
            'act' => $act,
            'medan' => $medan,
            'galat_kode' => $medan === null ? 'bentuk' : null,
            'galat' => $medan === null ? __('feeder.kamus.galat.bentuk') : null,
        ], KamusFeeder::versi($response->raw)];
    }
}
