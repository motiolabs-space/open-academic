<?php

declare(strict_types=1);

namespace App\Support\Sdm;

use App\Enums\EducationLevel;
use App\Enums\JenisDiklat;
use App\Enums\JenisPensiun;
use App\Enums\JenisRiwayat;
use App\Enums\JenisStudiLanjut;
use App\Enums\TingkatKegiatan;
use App\Models\Sdm\UnitKerja;
use App\Support\Format;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * The fields of each personnel history, in one place.
 *
 * One definition feeds the FormRequest's rules, both portals' forms, and the
 * one-line summary on every list — so a column added to a table is added here
 * once rather than in four templates that will drift.
 *
 * Field names are column names, with one exception: `angka_kredit` is typed as
 * a decimal and stored in hundredths (see atribut()).
 */
final class FormulirRiwayat
{
    /**
     * Civil-service grade → rank title.
     *
     * The title follows from the grade, so asking for both invites the two to
     * disagree on the first form somebody fills in a hurry.
     */
    public const PANGKAT = [
        'III/a' => 'Penata Muda',
        'III/b' => 'Penata Muda Tingkat I',
        'III/c' => 'Penata',
        'III/d' => 'Penata Tingkat I',
        'IV/a' => 'Pembina',
        'IV/b' => 'Pembina Tingkat I',
        'IV/c' => 'Pembina Utama Muda',
        'IV/d' => 'Pembina Utama Madya',
        'IV/e' => 'Pembina Utama',
    ];

    /**
     * @return array<string, array{tipe: string, wajib: bool, aturan: array<int, mixed>, pilihan?: array<string, string>, lebar?: bool}>
     */
    public static function bidang(JenisRiwayat $jenis): array
    {
        $tahun = ['integer', 'min:1950', 'max:'.(date('Y') + 1)];
        $teks = fn (int $maks = 255): array => ['string', 'max:'.$maks];

        $golongan = array_combine(array_keys(self::PANGKAT), array_map(
            fn (string $g, string $p): string => "{$g} — {$p}",
            array_keys(self::PANGKAT),
            self::PANGKAT,
        ));

        return match ($jenis) {
            JenisRiwayat::Pangkat => [
                'golongan' => self::pilih($golongan, wajib: true),
                'tmt' => self::tanggal(wajib: true),
                'nomor_sk' => self::isi($teks(128)),
                'tanggal_sk' => self::tanggal(),
            ],
            JenisRiwayat::Inpassing => [
                'golongan' => self::pilih($golongan, wajib: true),
                'angka_kredit' => self::isi(['numeric', 'min:0', 'max:2000'], tipe: 'number'),
                'nomor_sk' => self::isi($teks(128), wajib: true),
                'tanggal_sk' => self::tanggal(),
                'tmt' => self::tanggal(wajib: true),
            ],
            JenisRiwayat::JabatanStruktural => [
                'nama_jabatan' => self::isi($teks(), wajib: true, lebar: true),
                'unit_kerja_id' => self::pilih(self::unit(), aturan: [Rule::exists('unit_kerja', 'id')]),
                'nomor_sk' => self::isi($teks(128)),
                'tanggal_sk' => self::tanggal(),
                'tmt' => self::tanggal(wajib: true),
                'tmt_selesai' => self::tanggal(aturan: ['after_or_equal:tmt']),
            ],
            JenisRiwayat::TugasBelajar => [
                'jenis' => self::pilih(JenisStudiLanjut::options(), wajib: true),
                'jenjang' => self::pilih(
                    collect(EducationLevel::cases())->mapWithKeys(fn ($j) => [$j->value => $j->label()])->all(),
                    wajib: true,
                ),
                'perguruan_tinggi' => self::isi($teks(), wajib: true),
                'program_studi' => self::isi($teks()),
                'negara' => self::isi($teks(64), wajib: true),
                'sumber_biaya' => self::isi($teks()),
                'nomor_sk' => self::isi($teks(128)),
                'tanggal_sk' => self::tanggal(),
                'tanggal_mulai' => self::tanggal(wajib: true),
                'tanggal_selesai' => self::tanggal(aturan: ['after_or_equal:tanggal_mulai']),
            ],
            JenisRiwayat::Diklat => [
                'nama' => self::isi($teks(), wajib: true, lebar: true),
                'jenis' => self::pilih(JenisDiklat::options(), wajib: true),
                'penyelenggara' => self::isi($teks()),
                'tingkat' => self::pilih(TingkatKegiatan::options()),
                'tanggal_mulai' => self::tanggal(wajib: true),
                'tanggal_selesai' => self::tanggal(aturan: ['after_or_equal:tanggal_mulai']),
                'jumlah_jam' => self::isi(['integer', 'min:1', 'max:5000'], tipe: 'number'),
                'nomor_sertifikat' => self::isi($teks(128)),
            ],
            JenisRiwayat::PengalamanKerja => [
                'instansi' => self::isi($teks(), wajib: true),
                'jabatan' => self::isi($teks(), wajib: true),
                'bidang' => self::isi($teks()),
                'tanggal_mulai' => self::tanggal(wajib: true),
                'tanggal_selesai' => self::tanggal(aturan: ['after_or_equal:tanggal_mulai']),
                'keterangan' => self::isi($teks(500), tipe: 'textarea', lebar: true),
            ],
            JenisRiwayat::TugasDinas => [
                'tujuan' => self::isi($teks(), wajib: true),
                'instansi_tujuan' => self::isi($teks()),
                'keperluan' => self::isi($teks(500), wajib: true, tipe: 'textarea', lebar: true),
                'nomor_surat_tugas' => self::isi($teks(128)),
                'tanggal_mulai' => self::tanggal(wajib: true),
                'tanggal_selesai' => self::tanggal(aturan: ['after_or_equal:tanggal_mulai']),
                'is_luar_negeri' => self::isi(['boolean'], tipe: 'checkbox'),
            ],
            JenisRiwayat::PenghargaanSanksi => [
                'jenis' => self::pilih([
                    'penghargaan' => __('kepegawaian.penghargaan_sanksi.penghargaan'),
                    'sanksi' => __('kepegawaian.penghargaan_sanksi.sanksi'),
                ], wajib: true),
                'nama' => self::isi($teks(), wajib: true),
                'tingkat' => self::pilih(TingkatKegiatan::options()),
                'pemberi' => self::isi($teks()),
                'tanggal' => self::tanggal(wajib: true),
                'nomor_sk' => self::isi($teks(128)),
                'keterangan' => self::isi($teks(500), tipe: 'textarea', lebar: true),
            ],
            JenisRiwayat::Organisasi => [
                'nama' => self::isi($teks(), wajib: true, lebar: true),
                'peran' => self::isi($teks(64)),
                'tingkat' => self::pilih(TingkatKegiatan::options()),
                'tahun_mulai' => self::isi($tahun, wajib: true, tipe: 'number'),
                'tahun_selesai' => self::isi([...$tahun, 'gte:tahun_mulai'], tipe: 'number'),
            ],
            JenisRiwayat::Bahasa => [
                'bahasa' => self::isi($teks(64), wajib: true),
                'kemampuan' => self::pilih(__('kepegawaian.kemampuan')),
                'sertifikat' => self::isi($teks(128)),
                'skor' => self::isi(['integer', 'min:0', 'max:9999'], tipe: 'number'),
            ],
            JenisRiwayat::Keluarga => [
                'nama' => self::isi($teks(), wajib: true),
                'hubungan' => self::pilih(__('kepegawaian.hubungan'), wajib: true),
                'tanggal_lahir' => self::tanggal(aturan: ['before_or_equal:today']),
                'pekerjaan' => self::isi($teks()),
                'is_tanggungan' => self::isi(['boolean'], tipe: 'checkbox'),
            ],
            JenisRiwayat::Mutasi => [
                'jenis' => self::pilih(__('kepegawaian.mutasi'), wajib: true),
                'unit_tujuan_id' => self::pilih(self::unit(), aturan: [
                    'required_unless:jenis,keluar',
                    Rule::exists('unit_kerja', 'id'),
                ]),
                'tmt' => self::tanggal(wajib: true),
                'nomor_sk' => self::isi($teks(128)),
                'keterangan' => self::isi($teks(500), tipe: 'textarea', lebar: true),
            ],
            JenisRiwayat::Kontrak => [
                'nomor_kontrak' => self::isi($teks(128), wajib: true),
                'jenis' => self::isi($teks(64)),
                'tanggal_mulai' => self::tanggal(wajib: true),
                'tanggal_selesai' => self::tanggal(wajib: true, aturan: ['after_or_equal:tanggal_mulai']),
                'keterangan' => self::isi($teks(500), tipe: 'textarea', lebar: true),
            ],
            JenisRiwayat::Pensiun => [
                'jenis' => self::pilih(JenisPensiun::options(), wajib: true),
                'tmt' => self::tanggal(wajib: true),
                'nomor_sk' => self::isi($teks(128)),
                'tanggal_sk' => self::tanggal(),
                'keterangan' => self::isi($teks(500), tipe: 'textarea', lebar: true),
            ],
        };
    }

    /**
     * Validation rules for a history, as a FormRequest wants them.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function aturan(JenisRiwayat $jenis): array
    {
        return collect(self::bidang($jenis))
            ->map(fn (array $b): array => [$b['wajib'] ? 'required' : 'nullable', ...$b['aturan']])
            ->all();
    }

    /**
     * Validated input → the columns to write.
     *
     * @param array<string, mixed> $isian
     * @return array<string, mixed>
     */
    public static function atribut(JenisRiwayat $jenis, array $isian): array
    {
        $atribut = collect($isian)->only(array_keys(self::bidang($jenis)))->all();

        foreach (self::bidang($jenis) as $nama => $b) {
            if ($b['tipe'] === 'checkbox') {
                $atribut[$nama] = (bool) ($atribut[$nama] ?? false);
            }
        }

        if (array_key_exists('golongan', $atribut)) {
            $atribut['pangkat'] = self::PANGKAT[$atribut['golongan']] ?? $atribut['golongan'];
        }

        if (array_key_exists('angka_kredit', $atribut)) {
            $nilai = $atribut['angka_kredit'];
            unset($atribut['angka_kredit']);

            // Hundredths, rounded once at the edge — never a float in the table.
            $atribut['angka_kredit_ratus'] = $nilai === null ? null : (int) round(((float) $nilai) * 100);
        }

        return $atribut;
    }

    /**
     * A stored row → the values its form shows when edited.
     *
     * @return array<string, mixed>
     */
    public static function nilaiAwal(JenisRiwayat $jenis, ?Model $baris): array
    {
        if ($baris === null) {
            return [];
        }

        $nilai = [];

        foreach (array_keys(self::bidang($jenis)) as $nama) {
            $isi = $nama === 'angka_kredit'
                ? ($baris->angka_kredit_ratus === null ? null : $baris->angka_kredit_ratus / 100)
                : $baris->getAttribute($nama);

            $nilai[$nama] = $isi instanceof \BackedEnum ? $isi->value
                : ($isi instanceof \DateTimeInterface ? $isi->format('Y-m-d') : $isi);
        }

        return $nilai;
    }

    /**
     * One line naming the row, and one line of particulars, for any list.
     *
     * @return array{judul: string, rincian: string}
     */
    public static function ringkasan(JenisRiwayat $jenis, Model $b): array
    {
        $rentang = fn (?\DateTimeInterface $mulai, ?\DateTimeInterface $selesai): string => Format::tanggal($mulai)
            .' – '.($selesai ? Format::tanggal($selesai) : __('kepegawaian.sekarang'));

        [$judul, $rincian] = match ($jenis) {
            JenisRiwayat::Pangkat => ["{$b->pangkat} ({$b->golongan})", ['TMT '.Format::tanggal($b->tmt), $b->nomor_sk]],
            JenisRiwayat::Inpassing => ["{$b->pangkat} ({$b->golongan})", [
                'TMT '.Format::tanggal($b->tmt),
                $b->angka_kredit_ratus === null ? null : 'AK '.Format::angka($b->angka_kredit_ratus / 100),
                $b->nomor_sk,
            ]],
            JenisRiwayat::JabatanStruktural => [$b->nama_jabatan, [
                $b->unitKerja?->nama, $rentang($b->tmt, $b->tmt_selesai), $b->nomor_sk,
            ]],
            JenisRiwayat::TugasBelajar => ["{$b->jenis->label()} {$b->jenjang->label()} — {$b->perguruan_tinggi}", [
                $b->program_studi, $b->negara, $rentang($b->tanggal_mulai, $b->tanggal_selesai),
            ]],
            JenisRiwayat::Diklat => [$b->nama, [
                $b->jenis->label(), $b->penyelenggara, $rentang($b->tanggal_mulai, $b->tanggal_selesai),
                $b->jumlah_jam ? __('kepegawaian.jam', ['jumlah' => $b->jumlah_jam]) : null,
            ]],
            JenisRiwayat::PengalamanKerja => ["{$b->jabatan} — {$b->instansi}", [
                $b->bidang, $rentang($b->tanggal_mulai, $b->tanggal_selesai),
            ]],
            JenisRiwayat::TugasDinas => [$b->tujuan, [
                $b->instansi_tujuan, $rentang($b->tanggal_mulai, $b->tanggal_selesai ?? $b->tanggal_mulai),
                $b->is_luar_negeri ? __('kepegawaian.luar_negeri') : null, $b->nomor_surat_tugas,
            ]],
            JenisRiwayat::PenghargaanSanksi => [$b->nama, [
                __('kepegawaian.penghargaan_sanksi.'.$b->jenis), $b->pemberi, Format::tanggal($b->tanggal),
            ]],
            JenisRiwayat::Organisasi => [$b->nama, [
                $b->peran, $b->tahun_mulai.' – '.($b->tahun_selesai ?? __('kepegawaian.sekarang')),
            ]],
            JenisRiwayat::Bahasa => [$b->bahasa, [
                $b->kemampuan ? (__('kepegawaian.kemampuan')[$b->kemampuan] ?? $b->kemampuan) : null,
                $b->sertifikat, $b->skor,
            ]],
            JenisRiwayat::Keluarga => [$b->nama, [
                __('kepegawaian.hubungan')[$b->hubungan] ?? $b->hubungan,
                $b->tanggal_lahir ? Format::tanggal($b->tanggal_lahir) : null,
                $b->is_tanggungan ? __('kepegawaian.tanggungan') : null,
            ]],
            JenisRiwayat::Mutasi => [__('kepegawaian.mutasi')[$b->jenis] ?? $b->jenis, [
                $b->unitAsal?->nama ? __('kepegawaian.dari', ['unit' => $b->unitAsal->nama]) : null,
                $b->unitTujuan?->nama ? __('kepegawaian.ke', ['unit' => $b->unitTujuan->nama]) : null,
                'TMT '.Format::tanggal($b->tmt),
            ]],
            JenisRiwayat::Kontrak => [$b->nomor_kontrak, [$b->jenis, $rentang($b->tanggal_mulai, $b->tanggal_selesai)]],
            JenisRiwayat::Pensiun => [$b->jenis->label(), ['TMT '.Format::tanggal($b->tmt), $b->nomor_sk]],
        };

        return [
            'judul' => (string) $judul,
            'rincian' => collect($rincian)->filter(fn ($v) => filled($v))->implode(' · '),
        ];
    }

    /** Relations the summaries read, so a list loads them up front. */
    public static function relasiRingkasan(JenisRiwayat $jenis): array
    {
        return match ($jenis) {
            JenisRiwayat::JabatanStruktural => ['unitKerja'],
            JenisRiwayat::Mutasi => ['unitAsal', 'unitTujuan'],
            default => [],
        };
    }

    /* ------------------------------------------------------------------ */

    /** @return array{tipe: string, wajib: bool, aturan: array<int, mixed>, lebar: bool} */
    private static function isi(array $aturan, bool $wajib = false, string $tipe = 'text', bool $lebar = false): array
    {
        return ['tipe' => $tipe, 'wajib' => $wajib, 'aturan' => $aturan, 'lebar' => $lebar];
    }

    /** @return array{tipe: string, wajib: bool, aturan: array<int, mixed>, lebar: bool} */
    private static function tanggal(bool $wajib = false, array $aturan = []): array
    {
        return self::isi(['date', ...$aturan], $wajib, 'date');
    }

    /**
     * @param array<string|int, string> $pilihan
     * @return array{tipe: string, wajib: bool, aturan: array<int, mixed>, pilihan: array<string|int, string>, lebar: bool}
     */
    private static function pilih(array $pilihan, bool $wajib = false, ?array $aturan = null): array
    {
        return [
            'tipe' => 'select',
            'wajib' => $wajib,
            'aturan' => $aturan ?? [Rule::in(array_map('strval', array_keys($pilihan)))],
            'pilihan' => $pilihan,
            'lebar' => false,
        ];
    }

    /**
     * Active units, memoised for the request.
     *
     * Two of the fourteen forms ask for one; the memo keeps a screen that
     * renders both from asking twice.
     *
     * @return array<int, string>
     */
    private static function unit(): array
    {
        return once(fn (): array => UnitKerja::query()
            ->where('is_active', true)
            ->orderBy('nama')
            ->pluck('nama', 'id')
            ->all());
    }
}
