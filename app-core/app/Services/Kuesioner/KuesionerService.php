<?php

declare(strict_types=1);

namespace App\Services\Kuesioner;

use App\DTOs\Kuesioner\KuesionerData;
use App\DTOs\Kuesioner\PertanyaanData;
use App\Enums\TipePertanyaan;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\Prodi;
use App\Models\Kuesioner\Kuesioner;
use App\Models\Kuesioner\KuesionerJawaban;
use App\Models\Kuesioner\KuesionerJawabanAnonim;
use App\Models\Kuesioner\KuesionerPartisipasi;
use App\Models\Kuesioner\KuesionerPertanyaan;
use App\Models\Sdm\Staff;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Writing, filling in, and reading back a questionnaire.
 *
 * The one thing this class must never do is let an anonymous form's answers be
 * traced. It cannot, structurally: the anonymous answer table has no respondent
 * column, and the choice of table is made from `kuesioner.anonim` in a single
 * place — here. The reading side keeps the same promise: results and exports
 * of an anonymous form carry no row ids, no timestamps, and no ordering that
 * follows the order people answered in.
 */
class KuesionerService
{
    /**
     * Writes a new questionnaire, as a draft.
     *
     * `anonim` is decided here and nowhere else: it picks the answer table,
     * and perbarui() does not read it.
     */
    public function buat(KuesionerData $data, Staff $oleh): Kuesioner
    {
        $this->pastikanSasaran($data);

        return Kuesioner::create([
            'kode' => $data->kode,
            'nama' => $data->nama,
            'deskripsi' => $data->deskripsi,
            'sasaran' => $data->sasaran,
            'prodi_id' => $data->prodiId,
            'anonim' => $data->anonim,
            'min_responden' => $data->minResponden ?? (int) config('kuesioner.min_responden'),
            'mulai' => $data->mulai,
            'selesai' => $data->selesai,
            'is_active' => false,
            'dibuat_oleh_staff_id' => $oleh->id,
        ]);
    }

    /**
     * Edits the header.
     *
     * Name, description and window may change at any time. Audience and
     * threshold only until the first publication: widening who may answer
     * after answers arrived changes what the results describe, and lowering
     * the threshold would open results that were held back for a reason.
     * `anonim` is never touched.
     */
    public function perbarui(Kuesioner $kuesioner, KuesionerData $data): Kuesioner
    {
        $perubahan = [
            'kode' => $data->kode,
            'nama' => $data->nama,
            'deskripsi' => $data->deskripsi,
            'mulai' => $data->mulai,
            'selesai' => $data->selesai,
        ];

        if (!$kuesioner->pertanyaanTerkunci()) {
            $this->pastikanSasaran($data);

            $perubahan += [
                'sasaran' => $data->sasaran,
                'prodi_id' => $data->prodiId,
                'min_responden' => $data->minResponden ?? $kuesioner->min_responden,
            ];
        }

        $kuesioner->update($perubahan);

        return $kuesioner;
    }

    public function tambahPertanyaan(Kuesioner $kuesioner, PertanyaanData $data): KuesionerPertanyaan
    {
        $this->pastikanBelumTerkunci($kuesioner);

        if (blank($data->teks)) {
            throw new AturanAkademikException(__('kuesioner.galat.teks_kosong'));
        }

        // One choice is not a choice: a respondent offered a single box has
        // been told the answer rather than asked.
        if ($data->tipe === TipePertanyaan::Pilihan && count($data->opsi) < 2) {
            throw new AturanAkademikException(__('kuesioner.galat.opsi_kurang'));
        }

        return $kuesioner->pertanyaan()->create([
            'urutan' => (int) $kuesioner->pertanyaan()->max('urutan') + 1,
            'teks' => $data->teks,
            'tipe' => $data->tipe,
            'opsi' => $data->tipe === TipePertanyaan::Pilihan ? $data->opsi : null,
            'wajib' => $data->wajib,
        ]);
    }

    public function hapusPertanyaan(KuesionerPertanyaan $pertanyaan): void
    {
        $this->pastikanBelumTerkunci($pertanyaan->kuesioner);

        $pertanyaan->delete();
    }

    /**
     * Opens the questionnaire to its audience.
     *
     * The first publication freezes the questions — an answer has to keep
     * meaning what it meant when it was given. Re-opening a closed
     * questionnaire is allowed; it was frozen already.
     */
    public function terbitkan(Kuesioner $kuesioner): Kuesioner
    {
        if (!$kuesioner->pertanyaan()->exists()) {
            throw new AturanAkademikException(__('kuesioner.galat.tanpa_pertanyaan'));
        }

        if ($kuesioner->selesai !== null && $kuesioner->selesai->lt(now()->startOfDay())) {
            throw new AturanAkademikException(__('kuesioner.galat.periode_lewat'));
        }

        $kuesioner->update([
            'is_active' => true,
            'diterbitkan_at' => $kuesioner->diterbitkan_at ?? now(),
        ]);

        return $kuesioner;
    }

    public function tutup(Kuesioner $kuesioner): Kuesioner
    {
        $kuesioner->update(['is_active' => false]);

        return $kuesioner;
    }

    /**
     * What this person is being asked to fill in, each marked with whether
     * they already have.
     *
     * Two queries whatever the number of questionnaires: the forms, then one
     * look at the participation table for all of them.
     *
     * @return Collection<int, array{kuesioner: Kuesioner, sudah: bool}>
     */
    public function untukResponden(Model $responden): Collection
    {
        $daftar = Kuesioner::query()
            ->untuk($responden)
            ->withCount('pertanyaan')
            ->orderByDesc('diterbitkan_at')
            ->orderByDesc('id')
            ->get();

        $sudah = KuesionerPartisipasi::query()
            ->whereIn('kuesioner_id', $daftar->modelKeys())
            ->where('responden_type', $responden::class)
            ->where('responden_id', $responden->getKey())
            ->pluck('kuesioner_id')
            ->flip();

        return $daftar->map(fn (Kuesioner $k): array => ['kuesioner' => $k, 'sudah' => $sudah->has($k->id)]);
    }

    /**
     * Records one respondent's answers.
     *
     * Participation and answers are written in one transaction, but into
     * different tables, and for an anonymous form the two are never joinable.
     * A crash between them would leave a form that can be answered twice, which
     * is recoverable; the alternative ordering would leave answers attributable
     * for as long as it took somebody to notice.
     *
     * @param array<int, array{nilai?: int|null, teks?: string|null}> $jawaban keyed by question id
     */
    public function isi(Kuesioner $kuesioner, object $responden, array $jawaban): void
    {
        if (!$kuesioner->terbuka()) {
            throw new AturanAkademikException('Kuesioner ini sedang tidak dibuka.');
        }

        // Checked here and not only by the policy: an artisan command or a test
        // reaches this method without passing a controller.
        if (!$responden instanceof Model || !$kuesioner->menyasar($responden)) {
            throw new AturanAkademikException(__('kuesioner.galat.bukan_sasaran'));
        }

        if ($this->sudahMengisi($kuesioner, $responden)) {
            throw new AturanAkademikException('Anda sudah mengisi kuesioner ini.');
        }

        $pertanyaan = $kuesioner->pertanyaan()->get();

        $this->pastikanLengkap($pertanyaan, $jawaban);
        $this->pastikanSah($pertanyaan, $jawaban);

        DB::transaction(function () use ($kuesioner, $responden, $pertanyaan, $jawaban): void {
            KuesionerPartisipasi::create([
                'kuesioner_id' => $kuesioner->id,
                'responden_type' => $responden::class,
                'responden_id' => $responden->getKey(),
                'diisi_at' => now(),
            ]);

            foreach ($pertanyaan as $satu) {
                $isi = $jawaban[$satu->id] ?? null;

                if ($isi === null) {
                    continue;
                }

                $baris = [
                    'kuesioner_id' => $kuesioner->id,
                    'kuesioner_pertanyaan_id' => $satu->id,
                    'nilai' => $satu->tipe->berangka() ? (int) ($isi['nilai'] ?? 0) : null,
                    'teks' => $satu->tipe->berangka() ? null : ($isi['teks'] ?? null),
                ];

                // An optional question left empty is not an answer. Storing a
                // zero for it would count as one in every mean.
                if ($satu->tipe->berangka() ? $baris['nilai'] === 0 : blank($baris['teks'])) {
                    continue;
                }

                /*
                 * The single place the two shapes diverge.
                 *
                 * Everything upstream is identical, which is the point: there is
                 * no second code path that could forget.
                 */
                if ($kuesioner->anonim) {
                    KuesionerJawabanAnonim::create($baris);
                } else {
                    KuesionerJawaban::create([
                        ...$baris,
                        'responden_type' => $responden::class,
                        'responden_id' => $responden->getKey(),
                    ]);
                }
            }
        });
    }

    public function sudahMengisi(Kuesioner $kuesioner, object $responden): bool
    {
        return KuesionerPartisipasi::query()
            ->where('kuesioner_id', $kuesioner->id)
            ->where('responden_type', $responden::class)
            ->where('responden_id', $responden->getKey())
            ->exists();
    }

    /**
     * @param Collection<int, KuesionerPertanyaan> $pertanyaan
     * @param array<int, mixed> $jawaban
     */
    private function pastikanLengkap(Collection $pertanyaan, array $jawaban): void
    {
        $kurang = $pertanyaan
            ->filter(fn (KuesionerPertanyaan $p): bool => $p->wajib)
            ->filter(function (KuesionerPertanyaan $p) use ($jawaban): bool {
                $isi = $jawaban[$p->id] ?? null;

                return $p->tipe->berangka()
                    ? blank($isi['nilai'] ?? null)
                    : blank($isi['teks'] ?? null);
            });

        if ($kurang->isNotEmpty()) {
            throw new AturanAkademikException(sprintf(
                '%d pertanyaan wajib belum dijawab.',
                $kurang->count(),
            ));
        }
    }

    /**
     * A scale answer is 1–5 and a choice is one of the listed options.
     *
     * The form offers nothing else, but a request is not a form; an off-scale
     * 9 would drag every mean it entered, silently.
     *
     * @param Collection<int, KuesionerPertanyaan> $pertanyaan
     * @param array<int, mixed> $jawaban
     */
    private function pastikanSah(Collection $pertanyaan, array $jawaban): void
    {
        foreach ($pertanyaan as $p) {
            $isi = $jawaban[$p->id] ?? null;

            if (!is_array($isi)) {
                continue;
            }

            $sah = match ($p->tipe) {
                TipePertanyaan::Skala => blank($isi['nilai'] ?? null)
                    || in_array(filter_var($isi['nilai'], FILTER_VALIDATE_INT), [1, 2, 3, 4, 5], true),
                TipePertanyaan::Pilihan => blank($isi['teks'] ?? null)
                    || in_array((string) $isi['teks'], (array) $p->opsi, true),
                TipePertanyaan::Teks => mb_strlen((string) ($isi['teks'] ?? '')) <= 5000,
            };

            if (!$sah) {
                throw new AturanAkademikException(__('kuesioner.galat.jawaban_tidak_sah', ['nomor' => $p->urutan]));
            }
        }
    }

    /**
     * Whether the results may be read yet.
     *
     * An identified questionnaire has names on every answer, so a threshold
     * protects nobody. An anonymous one stays closed until enough people have
     * answered that no one can be singled out by elimination — EDOM's rule.
     */
    public function hasilTerbuka(Kuesioner $kuesioner, ?int $responden = null): bool
    {
        return !$kuesioner->anonim
            || ($responden ?? $this->jumlahResponden($kuesioner)) >= max(1, (int) $kuesioner->min_responden);
    }

    /**
     * Aggregated results, in the same shape whichever table they came from.
     *
     * Free text is returned as a list and never summarised — a mean of prose is
     * not a thing, and a count of prose tells the reader nothing they can act
     * on.
     *
     * @return array<int, array<string, mixed>>
     */
    public function hasil(Kuesioner $kuesioner): array
    {
        $tabel = $kuesioner->anonim ? 'kuesioner_jawaban_anonim' : 'kuesioner_jawaban';

        $jawaban = DB::table($tabel)
            ->where('kuesioner_id', $kuesioner->id)
            ->get(['kuesioner_pertanyaan_id', 'nilai', 'teks'])
            ->groupBy('kuesioner_pertanyaan_id');

        return $kuesioner->pertanyaan()->get()
            ->map(function (KuesionerPertanyaan $p) use ($jawaban): array {
                $baris = $jawaban[$p->id] ?? collect();

                return [
                    'pertanyaan' => $p,
                    'jumlah' => $baris->count(),

                    'rerata' => $p->tipe === TipePertanyaan::Skala && $baris->isNotEmpty()
                        ? round($baris->avg('nilai'), 2)
                        : null,

                    'sebaran' => match ($p->tipe) {
                        // Every option, including those nobody picked: a zero
                        // answers "did anyone choose this".
                        TipePertanyaan::Pilihan => collect((array) $p->opsi)
                            ->mapWithKeys(fn (string $o): array => [$o => 0])
                            ->merge($baris->groupBy('teks')->map->count())
                            ->all(),
                        TipePertanyaan::Skala => collect(range(1, 5))
                            ->mapWithKeys(fn (int $n): array => [$n => $baris->where('nilai', $n)->count()])
                            ->all(),
                        TipePertanyaan::Teks => [],
                    },

                    // Sorted, not in the order given. Insertion order is the
                    // order people answered in, and that is the one thing that
                    // could line an anonymous comment up with a participation
                    // row.
                    'teks' => $p->tipe === TipePertanyaan::Teks
                        ? $baris->pluck('teks')->filter()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values()->all()
                        : [],
                ];
            })
            ->all();
    }

    /** Response rate needs only the participation table. */
    public function jumlahResponden(Kuesioner $kuesioner): int
    {
        return $kuesioner->partisipasi()->count();
    }

    /**
     * The rows of the CSV export.
     *
     * An identified questionnaire exports one row per respondent, named — that
     * is what it was set up for. An anonymous one exports per question only:
     * counts per value, and free text sorted. No row ids, no timestamps, and
     * nothing that would tie two answers to one person, let alone to the
     * participation list.
     *
     * @return iterable<int, array<int, mixed>>
     */
    public function barisEkspor(Kuesioner $kuesioner): iterable
    {
        $responden = $this->jumlahResponden($kuesioner);

        if (!$this->hasilTerbuka($kuesioner, $responden)) {
            throw new AturanAkademikException(__('kuesioner.galat.hasil_tertahan', ['ambang' => $kuesioner->min_responden]));
        }

        $kepala = [
            [__('kuesioner.ekspor.kuesioner'), $kuesioner->kode.' — '.$kuesioner->nama],
            [__('kuesioner.ekspor.responden'), $responden],
            [__('kuesioner.ekspor.bentuk'), $kuesioner->anonim ? __('kuesioner.ekspor.anonim') : __('kuesioner.ekspor.beridentitas')],
            [],
        ];

        return [...$kepala, ...($kuesioner->anonim ? $this->barisAnonim($kuesioner) : $this->barisBeridentitas($kuesioner))];
    }

    /** @return array<int, array<int, mixed>> */
    private function barisAnonim(Kuesioner $kuesioner): array
    {
        $baris = [];

        foreach ($this->hasil($kuesioner) as $h) {
            /** @var KuesionerPertanyaan $p */
            $p = $h['pertanyaan'];

            $baris[] = [$p->urutan.'. '.$p->teks, $p->tipe->label()];

            if ($p->tipe === TipePertanyaan::Teks) {
                foreach ($h['teks'] as $teks) {
                    $baris[] = ['', $teks];
                }
            } else {
                foreach ($h['sebaran'] as $nilai => $jumlah) {
                    $baris[] = ['', $nilai, $jumlah];
                }

                if ($h['rerata'] !== null) {
                    $baris[] = ['', __('kuesioner.hasil.rerata'), $h['rerata']];
                }
            }

            $baris[] = [];
        }

        return $baris;
    }

    /** @return array<int, array<int, mixed>> */
    private function barisBeridentitas(Kuesioner $kuesioner): array
    {
        $pertanyaan = $kuesioner->pertanyaan()->get();

        $baris = [[
            __('kuesioner.ekspor.nama'),
            __('kuesioner.ekspor.nomor_induk'),
            __('kuesioner.ekspor.diisi'),
            ...$pertanyaan->map(fn (KuesionerPertanyaan $p): string => $p->urutan.'. '.$p->teks)->all(),
        ]];

        $jawaban = KuesionerJawaban::query()
            ->where('kuesioner_id', $kuesioner->id)
            ->get(['responden_type', 'responden_id', 'kuesioner_pertanyaan_id', 'nilai', 'teks'])
            ->groupBy(fn (KuesionerJawaban $j): string => $j->responden_type.':'.$j->responden_id);

        $partisipasi = $kuesioner->partisipasi()->with('responden')->orderBy('diisi_at')->orderBy('id')->get();

        foreach ($partisipasi as $satu) {
            $milik = ($jawaban[$satu->responden_type.':'.$satu->responden_id] ?? collect())
                ->keyBy('kuesioner_pertanyaan_id');

            $orang = $satu->responden;

            $baris[] = [
                $orang?->getAttribute('nama') ?? '—',
                $orang?->getAttribute('nim') ?? $orang?->getAttribute('nidn') ?? $orang?->getAttribute('nip') ?? $orang?->getAttribute('email') ?? '',
                $satu->diisi_at?->format('Y-m-d H:i'),
                ...$pertanyaan->map(function (KuesionerPertanyaan $p) use ($milik): mixed {
                    $j = $milik[$p->id] ?? null;

                    return $j === null ? '' : ($p->tipe->berangka() ? $j->nilai : $j->teks);
                })->all(),
            ];
        }

        return $baris;
    }

    private function pastikanBelumTerkunci(Kuesioner $kuesioner): void
    {
        if ($kuesioner->pertanyaanTerkunci()) {
            throw new AturanAkademikException(__('kuesioner.galat.terkunci'));
        }
    }

    private function pastikanSasaran(KuesionerData $data): void
    {
        if ($data->prodiId === null) {
            return;
        }

        if (!$data->sasaran->berprodi()) {
            throw new AturanAkademikException(__('kuesioner.galat.staf_tanpa_prodi'));
        }

        if (!Prodi::query()->whereKey($data->prodiId)->exists()) {
            throw new AturanAkademikException(__('kuesioner.galat.prodi_tidak_dikenal'));
        }
    }
}
