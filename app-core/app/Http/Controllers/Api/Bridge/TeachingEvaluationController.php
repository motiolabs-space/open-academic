<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Bridge;

use App\Http\Controllers\Controller;
use App\Models\Edom\EdomPeriode;
use App\Services\Edom\HasilEdom;
use Illuminate\Http\Request;

/**
 * Teaching-evaluation aggregates, for Open Campus and the lecturer workload
 * systems that report on them.
 *
 * **What this endpoint cannot do is the design.** There is no parameter, no
 * scope, and no code path here that returns an individual answer, a free-text
 * comment, or a respondent. Not because they are filtered out — because the
 * answers table holds no student and the comments are never selected.
 *
 * A class below its period's response threshold contributes nothing at all, not
 * even a zero. An external consumer that could see "0 of 4 responded, average
 * 2.1" would have learned more about a four-person class than anybody inside
 * Open Academic is allowed to.
 */
class TeachingEvaluationController extends Controller
{
    use ResolvesBridgeQuery;

    public function __construct(private readonly HasilEdom $hasil) {}

    public function __invoke(Request $request): array
    {
        $term = $this->term($request, wajib: true);

        $periode = EdomPeriode::query()
            ->where('tahun_akademik_id', $term->id)
            ->first();

        if ($periode === null) {
            return [
                'data' => [
                    'semester' => $term->kode,
                    'periode' => null,
                    'catatan' => 'Belum ada periode EDOM untuk semester ini.',
                    'dosen' => [],
                ],
            ];
        }

        $ringkasan = $this->hasil->ringkasanDosen($periode);

        return [
            'data' => [
                'semester' => $term->kode,
                'periode' => [
                    'nama' => $periode->nama,
                    'mulai' => $periode->mulai->toDateString(),
                    'selesai' => $periode->selesai->toDateString(),
                    'terbuka' => $periode->terbuka(),
                    'ambang_responden' => (int) $periode->min_responden,
                ],

                'catatan' => 'Rerata per dosen dari kelas yang memenuhi ambang responden. '
                    .'Jawaban individual dan komentar bebas tidak dipublikasikan melalui '
                    .'antarmuka mana pun.',

                'dosen' => $ringkasan
                    ->map(fn (array $baris): array => [
                        'uuid' => $baris['dosen']->uuid,
                        'nidn' => $baris['dosen']->nidn,
                        'nama' => $baris['dosen']->nama,
                        'kelas_dinilai' => $baris['kelas_dinilai'],
                        'jumlah_responden' => $baris['responden'],
                        'rerata' => $baris['rerata'],
                    ])
                    ->all(),
            ],
        ];
    }
}
