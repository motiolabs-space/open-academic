<?php

declare(strict_types=1);

namespace App\Services\Sdm;

use App\Enums\StatusBkd;
use App\Models\Akademik\TahunAkademik;
use App\Models\Sdm\BkdLaporan;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\JabatanFungsionalDosen;
use App\Models\Sdm\PenugasanDosen;
use App\Models\Sdm\RiwayatPendidikanDosen;
use App\Models\Sdm\SertifikasiDosen;

/**
 * One lecturer's record, assembled in the shape ministry systems ask for.
 *
 * Written before there is anything to send it to, and that is the point. The
 * expensive half of an integration is never the HTTP call — it is discovering,
 * two weeks in, that the campus never recorded which country a degree came from
 * or what a research role was. This class is the list of questions SISTER asks,
 * answered from the database, so the gap shows up now instead of then.
 *
 * When credentials arrive, a client maps these arrays onto the real payload.
 * Nothing here knows or cares what that payload looks like — deliberately, since
 * guessing at it would bake today's assumption into the data model.
 *
 * @see docs/BKD-SISTER.md
 */
class PortofolioDosen
{
    /**
     * Everything about one lecturer that is not tied to a single semester.
     *
     * @return array<string, mixed>
     */
    public function identitas(Dosen $dosen): array
    {
        $jabatan = $dosen->riwayatJabatan()->aktif()->first();

        return [
            'uuid' => $dosen->uuid,
            'nidn' => $dosen->nidn,
            'nip' => $dosen->nip,
            'nama' => $dosen->nama,
            'nama_lengkap' => $dosen->namaLengkap(),
            'gelar_depan' => $dosen->gelar_depan,
            'gelar_belakang' => $dosen->gelar_belakang,

            /*
             * NIK is deliberately absent, as is the home address.
             *
             * SISTER holds them; this array is also what feeds Campus Bridge and
             * the CSV somebody emails to a faculty office. A payload that is
             * safe in one channel and not the other ends up leaking through the
             * careless one — so the identifier that is genuinely sensitive stays
             * out of the shared shape entirely.
             */

            'jenis_kelamin' => $dosen->jenis_kelamin?->value,
            'tempat_lahir' => $dosen->tempat_lahir,
            'tanggal_lahir' => $dosen->tanggal_lahir?->toDateString(),

            'prodi' => $dosen->prodi?->kode,
            'status_kepegawaian' => $dosen->status_kepegawaian,
            'is_praktisi' => (bool) $dosen->is_praktisi,
            'praktisi_instansi' => $dosen->praktisi_instansi,

            'jabatan_fungsional' => $jabatan?->jabatan->value ?? $dosen->jabatan_fungsional,
            'angka_kredit' => $jabatan?->angkaKredit(),
            'pendidikan_tertinggi' => $dosen->pendidikan_tertinggi?->value,

            'riwayat_pendidikan' => $dosen->riwayatPendidikan
                ->map(fn (RiwayatPendidikanDosen $r): array => [
                    'jenjang' => $r->jenjang->value,
                    'perguruan_tinggi' => $r->perguruan_tinggi,
                    'program_studi' => $r->program_studi,
                    'bidang_ilmu' => $r->bidang_ilmu,
                    'negara' => $r->negara,
                    'luar_negeri' => $r->luarNegeri(),
                    'tahun_masuk' => $r->tahun_masuk,
                    'tahun_lulus' => $r->tahun_lulus,
                    'gelar' => $r->gelar,
                    'nomor_ijazah' => $r->nomor_ijazah,
                ])->all(),

            'riwayat_jabatan' => $dosen->riwayatJabatan
                ->map(fn (JabatanFungsionalDosen $j): array => [
                    'jabatan' => $j->jabatan->value,
                    'angka_kredit' => $j->angkaKredit(),
                    'angka_kredit_mencukupi' => $j->angkaKreditMencukupi(),
                    'nomor_sk' => $j->nomor_sk,
                    'tanggal_sk' => $j->tanggal_sk?->toDateString(),
                    'tmt' => $j->tmt->toDateString(),
                    'berlaku' => $j->berlaku(),
                ])->all(),

            'sertifikasi' => $dosen->sertifikasi
                ->map(fn (SertifikasiDosen $s): array => [
                    'jenis' => $s->jenis->value,
                    'nama' => $s->nama,
                    'nomor' => $s->nomor,
                    'penyelenggara' => $s->penyelenggara,
                    'bidang' => $s->bidang,
                    'tanggal' => $s->tanggal->toDateString(),
                    'berlaku_sampai' => $s->berlaku_sampai?->toDateString(),
                    'berlaku' => $s->berlaku(),
                ])->all(),

            'wajib_bkd' => $dosen->wajibBkd(),
        ];
    }

    /**
     * One lecturer's semester: activities, outputs, and the workload report.
     *
     * @return array<string, mixed>
     */
    public function semester(Dosen $dosen, TahunAkademik $term): array
    {
        $laporan = BkdLaporan::query()
            ->with('baris')
            ->where('dosen_id', $dosen->id)
            ->where('tahun_akademik_id', $term->id)
            ->first();

        return [
            'semester' => $term->kode,
            'kegiatan' => $this->kegiatan($dosen, $term),
            'bkd' => $laporan === null ? null : $this->bkd($laporan),
        ];
    }

    /**
     * Activities and their outputs.
     *
     * The verification flag travels with each row rather than being filtered on.
     * Whether unverified self-reports count is the consumer's rule — the ministry
     * asks for everything with evidence attached, Open Campus counts only what a
     * staff member checked. Filtering here would force one of those to be wrong.
     *
     * @return array<int, array<string, mixed>>
     */
    public function kegiatan(Dosen $dosen, TahunAkademik $term): array
    {
        return PenugasanDosen::query()
            ->where('dosen_id', $dosen->id)
            ->where('tahun_akademik_id', $term->id)
            ->orderBy('tanggal_mulai')
            ->get()
            ->map(fn (PenugasanDosen $p): array => [
                'uuid' => $p->uuid,
                'jenis' => $p->jenis->value,
                'unsur' => $p->unsur?->value,
                'judul' => $p->judul,
                'peran' => $p->peran?->value,
                'tingkat' => $p->tingkat?->value,
                'mitra_nama' => $p->mitra_nama,
                'mitra_jenis' => $p->mitra_jenis,
                'lokasi' => $p->lokasi,
                'tanggal_mulai' => $p->tanggal_mulai->toDateString(),
                'tanggal_selesai' => $p->tanggal_selesai?->toDateString(),
                'sks_ekuivalen' => $p->sks_ekuivalen === null ? null : (float) $p->sks_ekuivalen,
                'luaran_jenis' => $p->luaran_jenis?->value,
                'luaran_identitas' => $p->luaran_identitas,
                'luaran_tahun' => $p->luaran_tahun,
                'luaran_iku5' => $p->luaran_jenis?->luaranIku5() ?? false,
                'terverifikasi' => (bool) $p->is_verified,
            ])
            ->all();
    }

    /**
     * A workload report, with its lines.
     *
     * @return array<string, mixed>
     */
    public function bkd(BkdLaporan $laporan): array
    {
        return [
            'uuid' => $laporan->uuid,
            'status' => $laporan->status->value,
            'sks' => [
                'pendidikan' => $laporan->sks_pendidikan / 100,
                'penelitian' => $laporan->sks_penelitian / 100,
                'pengabdian' => $laporan->sks_pengabdian / 100,
                'penunjang' => $laporan->sks_penunjang / 100,
                'total' => $laporan->sksTotal(),
            ],
            'kesimpulan' => $laporan->kesimpulan?->value,
            'catatan_asesor' => $laporan->catatan_asesor,
            'diajukan_at' => $laporan->diajukan_at?->toIso8601String(),
            'dinilai_at' => $laporan->dinilai_at?->toIso8601String(),
            'disahkan_at' => $laporan->disahkan_at?->toIso8601String(),

            'baris' => $laporan->baris->map(fn ($b): array => [
                'unsur' => $b->unsur->value,
                'kegiatan' => $b->kegiatan,
                'rincian' => $b->rincian,
                'sks' => $b->sks(),

                // Kept in the payload because it is the first thing any reviewer
                // wants: derived from records, or typed by the person being
                // assessed.
                'otomatis' => (bool) $b->otomatis,
            ])->all(),
        ];
    }

    /**
     * The campus-wide recap, one row per lecturer.
     *
     * What a faculty office actually opens on deadline week — and what the CSV
     * export is built from.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rekap(TahunAkademik $term): array
    {
        $laporan = BkdLaporan::query()
            ->where('tahun_akademik_id', $term->id)
            ->get()
            ->keyBy('dosen_id');

        return app(BkdService::class)->wajibMelapor()
            ->with('prodi')
            ->orderBy('nama')
            ->get()
            ->map(function (Dosen $dosen) use ($laporan, $term): array {
                $satu = $laporan->get($dosen->id);

                return [
                    'nidn' => $dosen->nidn,
                    'nama' => $dosen->namaLengkap(),
                    'prodi' => $dosen->prodi?->nama,
                    'semester' => $term->kode,
                    'status' => ($satu?->status ?? StatusBkd::Draft)->label(),
                    'sks_pendidikan' => ($satu?->sks_pendidikan ?? 0) / 100,
                    'sks_penelitian' => ($satu?->sks_penelitian ?? 0) / 100,
                    'sks_pengabdian' => ($satu?->sks_pengabdian ?? 0) / 100,
                    'sks_penunjang' => ($satu?->sks_penunjang ?? 0) / 100,
                    'sks_total' => ($satu?->sks_total ?? 0) / 100,
                    'kesimpulan' => $satu?->kesimpulan?->label() ?? '',
                    'catatan_asesor' => $satu?->catatan_asesor ?? '',
                ];
            })
            ->all();
    }
}
