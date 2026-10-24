<?php

declare(strict_types=1);

namespace App\Services\Feeder;

use App\Enums\JalurPerubahanFeeder;
use App\Enums\JenisPerubahanFeeder;
use App\Models\Kemahasiswaan\EvaluasiStudi;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\WisudaPeserta;
use App\Models\Kemahasiswaan\Yudisium;

/**
 * The columns PDDIKTI locks once a student is reported, and which local
 * column each one lives in.
 *
 * Six identity columns are frozen when the biodata is accepted (PDM); seven
 * exit columns when the Lulus/DO record is (PJK). The catalogue names them in
 * PDDIKTI's vocabulary — `nama_ibu_kandung`, `id_periode_keluar` — because
 * that is the vocabulary of the form the operator ends up filling in.
 *
 * Which route a column takes is a fact about Feeder, not a preference: the
 * enrolment record's own fields go through UpdateRiwayatPendidikanMahasiswa;
 * the biodata identity and everything about the exit do not.
 */
final class KolomTerkunci
{
    /**
     * local model => local column => [PDDIKTI column, kind, route, label]
     *
     * @var array<class-string, array<string, array{0: string, 1: JenisPerubahanFeeder, 2: JalurPerubahanFeeder, 3: string}>>
     */
    private const KATALOG = [
        Mahasiswa::class => [
            'nim' => ['nim', JenisPerubahanFeeder::Pdm, JalurPerubahanFeeder::WebService, 'NIM'],
            'term_masuk' => ['id_periode_masuk', JenisPerubahanFeeder::Pdm, JalurPerubahanFeeder::WebService, 'Periode masuk'],
            'nama' => ['nama_mahasiswa', JenisPerubahanFeeder::Pdm, JalurPerubahanFeeder::PddiktiAdmin, 'Nama'],
            'nama_ibu' => ['nama_ibu_kandung', JenisPerubahanFeeder::Pdm, JalurPerubahanFeeder::PddiktiAdmin, 'Nama ibu kandung'],
            'tempat_lahir' => ['tempat_lahir', JenisPerubahanFeeder::Pdm, JalurPerubahanFeeder::PddiktiAdmin, 'Tempat lahir'],
            'tanggal_lahir' => ['tanggal_lahir', JenisPerubahanFeeder::Pdm, JalurPerubahanFeeder::PddiktiAdmin, 'Tanggal lahir'],
            'status' => ['id_jenis_keluar', JenisPerubahanFeeder::Pjk, JalurPerubahanFeeder::PddiktiAdmin, 'Jenis keluar'],
        ],
        Yudisium::class => [
            'tanggal_lulus' => ['tanggal_keluar', JenisPerubahanFeeder::Pjk, JalurPerubahanFeeder::PddiktiAdmin, 'Tanggal keluar'],
            'tahun_akademik_id' => ['id_periode_keluar', JenisPerubahanFeeder::Pjk, JalurPerubahanFeeder::PddiktiAdmin, 'Periode keluar'],
            'tanggal_yudisium' => ['tanggal_sk_yudisium', JenisPerubahanFeeder::Pjk, JalurPerubahanFeeder::PddiktiAdmin, 'Tanggal SK yudisium'],
            'ipk' => ['ipk', JenisPerubahanFeeder::Pjk, JalurPerubahanFeeder::PddiktiAdmin, 'IPK'],
        ],
        WisudaPeserta::class => [
            'nomor_ijazah' => ['nomor_ijazah', JenisPerubahanFeeder::Pjk, JalurPerubahanFeeder::PddiktiAdmin, 'Nomor ijazah'],
        ],
        EvaluasiStudi::class => [
            'keputusan' => ['keterangan', JenisPerubahanFeeder::Pjk, JalurPerubahanFeeder::PddiktiAdmin, 'Keterangan keluar'],
        ],
    ];

    /**
     * The locked columns of one local model, keyed by local column.
     *
     * @return array<string, array{0: string, 1: JenisPerubahanFeeder, 2: JalurPerubahanFeeder, 3: string}>
     */
    public static function untuk(string $modelClass): array
    {
        return self::KATALOG[$modelClass] ?? [];
    }

    public static function label(string $kolomPddikti): string
    {
        foreach (self::KATALOG as $kolom) {
            foreach ($kolom as [$pddikti, , , $label]) {
                if ($pddikti === $kolomPddikti) {
                    return $label;
                }
            }
        }

        return $kolomPddikti;
    }
}
