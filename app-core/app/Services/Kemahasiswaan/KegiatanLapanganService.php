<?php

declare(strict_types=1);

namespace App\Services\Kemahasiswaan;

use App\DTOs\Kemahasiswaan\HasilKonversiKegiatan;
use App\DTOs\Kemahasiswaan\KegiatanLapanganData;
use App\DTOs\Kemahasiswaan\KelompokKegiatanData;
use App\DTOs\Kemahasiswaan\LokasiKegiatanData;
use App\Enums\GradeLetter;
use App\Enums\StudentActivityType;
use App\Enums\StudentStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\Krs;
use App\Models\Akademik\KrsDetail;
use App\Models\Akademik\Nilai;
use App\Models\Kemahasiswaan\AktivitasMahasiswa;
use App\Models\Kemahasiswaan\KegiatanLapangan;
use App\Models\Kemahasiswaan\KegiatanLapanganKelompok;
use App\Models\Kemahasiswaan\KegiatanLapanganLokasi;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Services\Akademik\IndeksPrestasiCalculator;
use App\Services\Akademik\KrsService;
use App\Support\Format;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * KKN and PKL as managed field programmes (#37).
 *
 * The rule this service keeps: a participant is an `aktivitas_mahasiswa` row
 * and nothing else. Programmes, locations and groups are scaffolding around
 * that row; what reaches PDDIKTI is the activity, its member, its supervisor
 * and — once marked — the credit conversion, along the route an MBKM
 * internship already travels (ADR-029). So every change to the scaffolding is
 * written through to the activity rows it describes (segarkanAktivitas): the
 * title, place, dates and field supervisor PDDIKTI sees are always the ones on
 * the screen.
 *
 * The mark becomes a course grade only through conversion: a KRS line naming
 * the activity, with a final grade. After that the activity is closed here —
 * correcting the grade is the audited course-grade correction, not a new mark.
 */
class KegiatanLapanganService
{
    public function __construct(
        private readonly PembentukKelompok $pembentuk,
        private readonly KrsService $krs,
        private readonly IndeksPrestasiCalculator $indeks,
    ) {}

    /* ---------------------------------------------------------------------
     | Programme runs and locations
     |-------------------------------------------------------------------- */

    public function simpan(KegiatanLapanganData $data, ?KegiatanLapangan $kegiatan = null): KegiatanLapangan
    {
        if (!in_array($data->jenis, StudentActivityType::kegiatanLapangan(), true)) {
            throw new AturanAkademikException(__('kegiatan_lapangan.galat.jenis_tidak_sah'));
        }

        if ($data->tanggalSelesai < $data->tanggalMulai) {
            throw new AturanAkademikException(__('kegiatan_lapangan.galat.tanggal_terbalik'));
        }

        if ($data->ukuranKelompok < 2) {
            throw new AturanAkademikException(__('kegiatan_lapangan.galat.ukuran_kelompok'));
        }

        if ($data->bobotMitra < 0 || $data->bobotMitra > 100) {
            throw new AturanAkademikException(__('kegiatan_lapangan.galat.bobot_mitra'));
        }

        if ($kegiatan === null) {
            return KegiatanLapangan::create($data->toAttributes());
        }

        // The activities already sit in the old term at PDDIKTI; moving the
        // programme would leave them describing a semester it no longer has.
        if ($kegiatan->tahun_akademik_id !== $data->tahunAkademikId && $kegiatan->peserta()->exists()) {
            throw new AturanAkademikException(__('kegiatan_lapangan.galat.term_berpeserta'));
        }

        return DB::transaction(function () use ($kegiatan, $data): KegiatanLapangan {
            $kegiatan->update($data->toAttributes());
            $this->segarkanSemua($kegiatan->peserta()->get());

            return $kegiatan->refresh();
        });
    }

    public function simpanLokasi(
        KegiatanLapangan $kegiatan,
        LokasiKegiatanData $data,
        ?KegiatanLapanganLokasi $lokasi = null,
    ): KegiatanLapanganLokasi {
        if ($lokasi === null) {
            return KegiatanLapanganLokasi::create($data->toAttributes() + ['kegiatan_lapangan_id' => $kegiatan->id]);
        }

        if ($data->kuotaKelompok < $lokasi->kelompok()->count()) {
            throw new AturanAkademikException(__('kegiatan_lapangan.galat.kuota_di_bawah_terisi'));
        }

        return DB::transaction(function () use ($lokasi, $data): KegiatanLapanganLokasi {
            $lokasi->update($data->toAttributes());

            $this->segarkanSemua(AktivitasMahasiswa::query()
                ->whereIn('kegiatan_lapangan_kelompok_id', $lokasi->kelompok()->pluck('id'))
                ->get());

            return $lokasi->refresh();
        });
    }

    public function hapusLokasi(KegiatanLapanganLokasi $lokasi): void
    {
        if ($lokasi->kelompok()->exists()) {
            throw new AturanAkademikException(__('kegiatan_lapangan.galat.lokasi_terpakai'));
        }

        $lokasi->delete();
    }

    /* ---------------------------------------------------------------------
     | Participants
     |-------------------------------------------------------------------- */

    /**
     * Enrols a student — by staff, or by the student while registration is open.
     *
     * The activity is created unverified: a participant becomes a PDDIKTI
     * record when their mark is converted, not when they sign up.
     */
    public function daftarkan(KegiatanLapangan $kegiatan, Mahasiswa $mahasiswa, bool $mandiri = false): AktivitasMahasiswa
    {
        if ($mandiri && !$kegiatan->pendaftaran_dibuka) {
            throw new AturanAkademikException(__('kegiatan_lapangan.galat.pendaftaran_ditutup'));
        }

        if ($mahasiswa->status !== StudentStatus::Aktif) {
            throw new AturanAkademikException(__('kegiatan_lapangan.galat.bukan_aktif', [
                'status' => $mahasiswa->status->label(),
            ]));
        }

        if ($kegiatan->min_sks > 0 && ($sks = $mahasiswa->sksKumulatif()) < $kegiatan->min_sks) {
            throw new AturanAkademikException(__('kegiatan_lapangan.galat.sks_kurang', [
                'min' => $kegiatan->min_sks,
                'sks' => $sks,
            ]));
        }

        if ($kegiatan->peserta()->where('mahasiswa_id', $mahasiswa->id)->exists()) {
            throw new AturanAkademikException(__('kegiatan_lapangan.galat.sudah_terdaftar'));
        }

        $aktivitas = new AktivitasMahasiswa([
            'mahasiswa_id' => $mahasiswa->id,
            'kegiatan_lapangan_id' => $kegiatan->id,
            'is_verified' => false,
        ]);

        $this->isiDariKegiatan($aktivitas, $kegiatan, null);
        $aktivitas->save();

        return $aktivitas;
    }

    /**
     * Enrols a list of students by NIM, reporting each refusal instead of
     * stopping at the first.
     *
     * @param array<int, string> $daftarNim
     * @return array{terdaftar: int, ditolak: array<string, string>}
     */
    public function daftarkanBanyak(KegiatanLapangan $kegiatan, array $daftarNim): array
    {
        $daftarNim = array_values(array_unique(array_filter(array_map('trim', $daftarNim))));
        $mahasiswa = Mahasiswa::query()->whereIn('nim', $daftarNim)->get()->keyBy('nim');

        $terdaftar = 0;
        $ditolak = [];

        foreach ($daftarNim as $nim) {
            $m = $mahasiswa->get($nim);

            if ($m === null) {
                $ditolak[$nim] = __('kegiatan_lapangan.galat.nim_tidak_dikenal');

                continue;
            }

            try {
                $this->daftarkan($kegiatan, $m);
                $terdaftar++;
            } catch (AturanAkademikException $e) {
                $ditolak[$nim] = $e->getMessage();
            }
        }

        return ['terdaftar' => $terdaftar, 'ditolak' => $ditolak];
    }

    /** Withdraws a participant who has not yet been marked. */
    public function keluarkan(AktivitasMahasiswa $aktivitas): void
    {
        $this->pastikanBelumDikonversi($aktivitas);

        if ($aktivitas->nilai_akhir !== null) {
            throw new AturanAkademikException(__('kegiatan_lapangan.galat.sudah_dinilai'));
        }

        $aktivitas->delete();
    }

    /* ---------------------------------------------------------------------
     | Groups
     |-------------------------------------------------------------------- */

    /**
     * Forms groups for every participant not yet in one, then places them at
     * the locations with room left, in name order.
     *
     * Participants already grouped by hand stay where they are. Groups that do
     * not fit any location are created without one, for staff to place.
     *
     * @return Collection<int, KegiatanLapanganKelompok>
     */
    public function bentukKelompokOtomatis(KegiatanLapangan $kegiatan): Collection
    {
        $peserta = $kegiatan->peserta()
            ->whereNull('kegiatan_lapangan_kelompok_id')
            ->with('mahasiswa')
            ->get();

        if ($peserta->isEmpty()) {
            throw new AturanAkademikException(__('kegiatan_lapangan.galat.tanpa_peserta_lepas'));
        }

        $susunan = $this->pembentuk->bentuk(
            $peserta,
            $kegiatan->ukuran_kelompok,
            $kegiatan->campur_prodi,
            $kegiatan->campur_jenis_kelamin,
        );

        return DB::transaction(function () use ($kegiatan, $susunan): Collection {
            $slot = $this->slotLokasi($kegiatan);
            $nomor = (int) $kegiatan->kelompok()->max('nomor');
            $hasil = collect();

            foreach ($susunan as $anggota) {
                $kelompok = KegiatanLapanganKelompok::create([
                    'kegiatan_lapangan_id' => $kegiatan->id,
                    'lokasi_id' => array_shift($slot),
                    'nomor' => ++$nomor,
                    'nama' => $this->namaKelompok($nomor),
                ]);

                foreach ($anggota as $aktivitas) {
                    $aktivitas->kegiatan_lapangan_kelompok_id = $kelompok->id;
                    $this->isiDariKegiatan($aktivitas, $kegiatan, $kelompok);
                    $aktivitas->save();
                }

                $hasil->push($kelompok);
            }

            return $hasil;
        });
    }

    /** An empty group, for staff forming groups by hand. */
    public function buatKelompok(KegiatanLapangan $kegiatan, KelompokKegiatanData $data): KegiatanLapanganKelompok
    {
        return DB::transaction(function () use ($kegiatan, $data): KegiatanLapanganKelompok {
            // Lock the parent, not the aggregate: PostgreSQL refuses FOR UPDATE
            // on MAX() outright (ADR-043). Holding the activity row serialises
            // group numbering for it on every engine.
            KegiatanLapangan::query()->whereKey($kegiatan->id)->lockForUpdate()->first();

            $nomor = (int) $kegiatan->kelompok()->max('nomor') + 1;

            $kelompok = KegiatanLapanganKelompok::create([
                'kegiatan_lapangan_id' => $kegiatan->id,
                'nomor' => $nomor,
                'nama' => $this->namaKelompok($nomor),
            ]);

            return $this->aturKelompok($kelompok, $data);
        });
    }

    /**
     * Places a group and names its supervisors; the field supervisor is
     * written through to every member's activity, which is what PDDIKTI reads.
     */
    public function aturKelompok(KegiatanLapanganKelompok $kelompok, KelompokKegiatanData $data): KegiatanLapanganKelompok
    {
        $kegiatan = $kelompok->loadMissing('kegiatanLapangan')->kegiatanLapangan;

        if ($data->lokasiId !== null && $data->lokasiId !== $kelompok->lokasi_id) {
            $lokasi = KegiatanLapanganLokasi::query()
                ->where('kegiatan_lapangan_id', $kegiatan->id)
                ->find($data->lokasiId);

            if ($lokasi === null) {
                throw new AturanAkademikException(__('kegiatan_lapangan.galat.lokasi_lain'));
            }

            if ($lokasi->kelompok()->count() >= $lokasi->kuota_kelompok) {
                throw new AturanAkademikException(__('kegiatan_lapangan.galat.lokasi_penuh', ['lokasi' => $lokasi->nama]));
            }
        }

        if ($data->dosenPembimbingId !== null) {
            $dosen = Dosen::find($data->dosenPembimbingId);

            if ($dosen === null || !$dosen->is_active) {
                throw new AturanAkademikException(__('kegiatan_lapangan.galat.dosen_nonaktif'));
            }
        }

        if ($data->ketuaAktivitasId !== null
            && !$kelompok->anggota()->whereKey($data->ketuaAktivitasId)->exists()
        ) {
            throw new AturanAkademikException(__('kegiatan_lapangan.galat.ketua_bukan_anggota'));
        }

        return DB::transaction(function () use ($kelompok, $kegiatan, $data): KegiatanLapanganKelompok {
            $kelompok->update([
                'lokasi_id' => $data->lokasiId,
                'dosen_pembimbing_id' => $data->dosenPembimbingId,
                'pembimbing_mitra_nama' => $data->pembimbingMitraNama,
                'pembimbing_mitra_jabatan' => $data->pembimbingMitraJabatan,
                'pembimbing_mitra_telepon' => $data->pembimbingMitraTelepon,
            ]);

            $kelompok->anggota()->update(['is_ketua_kelompok' => false]);

            if ($data->ketuaAktivitasId !== null) {
                $kelompok->anggota()->whereKey($data->ketuaAktivitasId)->update(['is_ketua_kelompok' => true]);
            }

            $kelompok->refresh()->load('lokasi');

            foreach ($kelompok->anggota()->get() as $aktivitas) {
                $this->isiDariKegiatan($aktivitas, $kegiatan, $kelompok);
                $aktivitas->save();
            }

            return $kelompok;
        });
    }

    /** Moves a participant into a group of the same programme, or out of any. */
    public function pindahkan(AktivitasMahasiswa $aktivitas, ?KegiatanLapanganKelompok $tujuan): AktivitasMahasiswa
    {
        $this->pastikanBelumDikonversi($aktivitas);

        if ($tujuan !== null && $tujuan->kegiatan_lapangan_id !== $aktivitas->kegiatan_lapangan_id) {
            throw new AturanAkademikException(__('kegiatan_lapangan.galat.kelompok_lain'));
        }

        $tujuan?->load('lokasi');
        $aktivitas->loadMissing('kegiatanLapangan');

        $aktivitas->kegiatan_lapangan_kelompok_id = $tujuan?->id;
        $aktivitas->is_ketua_kelompok = false;
        $this->isiDariKegiatan($aktivitas, $aktivitas->kegiatanLapangan, $tujuan);
        $aktivitas->save();

        return $aktivitas->refresh();
    }

    public function hapusKelompok(KegiatanLapanganKelompok $kelompok): void
    {
        if ($kelompok->anggota()->exists()) {
            throw new AturanAkademikException(__('kegiatan_lapangan.galat.kelompok_berisi'));
        }

        $kelompok->delete();
    }

    /* ---------------------------------------------------------------------
     | Marks and conversion
     |-------------------------------------------------------------------- */

    /**
     * Records the field supervisor's and the partner's marks.
     *
     * The final mark exists only once every mark the programme weighs is in:
     * with a partner share of 40, a field mark alone is not 60% of a grade,
     * it is half of one.
     */
    public function nilai(AktivitasMahasiswa $aktivitas, ?float $nilaiPembimbing, ?float $nilaiMitra): AktivitasMahasiswa
    {
        $this->pastikanBelumDikonversi($aktivitas);

        foreach ([$nilaiPembimbing, $nilaiMitra] as $angka) {
            if ($angka !== null && ($angka < 0 || $angka > 100)) {
                throw new AturanAkademikException(__('kegiatan_lapangan.galat.nilai_rentang'));
            }
        }

        $bobotMitra = $aktivitas->loadMissing('kegiatanLapangan')->kegiatanLapangan->bobot_mitra;

        $lengkap = ($bobotMitra === 100 || $nilaiPembimbing !== null)
            && ($bobotMitra === 0 || $nilaiMitra !== null);

        $akhir = $lengkap
            ? round(
                (float) ($nilaiPembimbing ?? 0) * (100 - $bobotMitra) / 100
                    + (float) ($nilaiMitra ?? 0) * $bobotMitra / 100,
                2,
            )
            : null;

        $aktivitas->update([
            'nilai_pembimbing' => $nilaiPembimbing,
            'nilai_mitra' => $nilaiMitra,
            'nilai_akhir' => $akhir,
            'dinilai_at' => $akhir !== null ? now() : null,
        ]);

        return $aktivitas->refresh();
    }

    /**
     * A whole group's marks at once, keyed by activity UUID.
     *
     * Members are read from the group itself, so a row naming somebody else's
     * activity is ignored rather than trusted.
     *
     * @param array<string, array{pembimbing: ?float, mitra: ?float}> $isian
     */
    public function nilaiKelompok(KegiatanLapanganKelompok $kelompok, array $isian): int
    {
        $jumlah = 0;

        DB::transaction(function () use ($kelompok, $isian, &$jumlah): void {
            foreach ($kelompok->anggota()->with('kegiatanLapangan')->get() as $aktivitas) {
                if (!array_key_exists($aktivitas->uuid, $isian)) {
                    continue;
                }

                $this->nilai($aktivitas, $isian[$aktivitas->uuid]['pembimbing'], $isian[$aktivitas->uuid]['mitra']);
                $jumlah++;
            }
        });

        return $jumlah;
    }

    /**
     * Turns final marks into the KKN/PKL course grade for one class.
     *
     * One class at a time because the course is the programme's own: a
     * cross-programme KKN carries a different course code in each curriculum,
     * so staff convert per class and the class's programme picks the
     * participants. A student who already took the course in their KRS keeps
     * that line and has the activity attached to it; anyone else gets a line
     * of their own. Either way the line names the activity — that is what
     * makes it a conversion at PDDIKTI (#23) — and carries a final grade, and
     * the activity is verified with the course's credits.
     */
    public function konversi(KegiatanLapangan $kegiatan, KelasKuliah $kelas, Staff $staff): HasilKonversiKegiatan
    {
        if ($kelas->tahun_akademik_id !== $kegiatan->tahun_akademik_id) {
            throw new AturanAkademikException(__('kegiatan_lapangan.galat.kelas_beda_term'));
        }

        $kelas->loadMissing(['mataKuliah', 'tahunAkademik']);

        $peserta = $kegiatan->peserta()
            ->with(['mahasiswa', 'kelompok', 'krsKonversi', 'kegiatanLapangan'])
            ->whereHas('mahasiswa', fn ($q) => $q->where('prodi_id', $kelas->prodi_id))
            ->get()
            ->sortBy(fn (AktivitasMahasiswa $a): string => (string) $a->mahasiswa->nim);

        $dikonversi = collect();
        $dilewati = [];

        foreach ($peserta as $aktivitas) {
            $nim = (string) $aktivitas->mahasiswa->nim;

            if ($aktivitas->krsKonversi !== null) {
                $dilewati[$nim] = __('kegiatan_lapangan.galat.sudah_dikonversi');

                continue;
            }

            if ($aktivitas->nilai_akhir === null) {
                $dilewati[$nim] = __('kegiatan_lapangan.galat.belum_dinilai');

                continue;
            }

            try {
                DB::transaction(fn () => $this->konversiSatu($aktivitas, $kelas, $staff));
                $dikonversi->push($aktivitas->mahasiswa);
            } catch (AturanAkademikException $e) {
                $dilewati[$nim] = $e->getMessage();
            }
        }

        foreach ($dikonversi as $mahasiswa) {
            $this->indeks->hitungUlang($mahasiswa, $kelas->tahunAkademik);
        }

        return new HasilKonversiKegiatan($dikonversi->count(), $dilewati);
    }

    /* ---------------------------------------------------------------------
     | Internals
     |-------------------------------------------------------------------- */

    private function konversiSatu(AktivitasMahasiswa $aktivitas, KelasKuliah $kelas, Staff $staff): void
    {
        $mahasiswa = $aktivitas->mahasiswa;

        $krs = Krs::query()
            ->where('mahasiswa_id', $mahasiswa->id)
            ->where('tahun_akademik_id', $kelas->tahun_akademik_id)
            ->first() ?? $this->krs->bukaAtauAmbil($mahasiswa, $kelas->tahunAkademik);

        $detail = $krs->detail()
            ->with('nilai')
            ->whereHas('kelasKuliah', fn ($q) => $q->where('mata_kuliah_id', $kelas->mata_kuliah_id))
            ->first();

        if ($detail?->nilai?->is_final) {
            throw new AturanAkademikException(__('kegiatan_lapangan.galat.mk_sudah_final', [
                'mk' => $kelas->mataKuliah->nama,
            ]));
        }

        if ($detail !== null) {
            $detail->update(['aktivitas_mahasiswa_id' => $aktivitas->id]);
        } else {
            $detail = KrsDetail::create([
                'krs_id' => $krs->id,
                'kelas_kuliah_id' => $kelas->id,
                'sks' => $kelas->sks,
                'aktivitas_mahasiswa_id' => $aktivitas->id,
            ]);

            // Recognition, not a seat: the class quota is not checked, but
            // the counters stay true to the lines that exist.
            $kelas->increment('terisi');
            $krs->increment('total_sks', $kelas->sks);
        }

        $angka = (float) $aktivitas->nilai_akhir;
        $huruf = GradeLetter::fromScore($angka);

        $nilai = Nilai::updateOrCreate(
            ['krs_detail_id' => $detail->id],
            [
                'kelas_kuliah_id' => $detail->kelas_kuliah_id,
                'mahasiswa_id' => $mahasiswa->id,
                'nilai_angka' => $angka,
                'nilai_huruf' => $huruf,
                'bobot' => $huruf->weight(),
                'is_final' => true,
                'finalized_at' => now(),
                'finalized_by_dosen_id' => $aktivitas->kelompok?->dosen_pembimbing_id,
            ],
        );

        $nilai->recordActivity('converted', __('kegiatan_lapangan.log.konversi', [
            'kegiatan' => $aktivitas->kegiatanLapangan->nama,
            'nilai' => Format::angka($angka),
            'huruf' => $huruf->value,
            'staf' => $staff->nama,
        ]));

        $aktivitas->update([
            'sks_konversi' => $detail->sks,
            'is_verified' => true,
            'verified_by_staff_id' => $aktivitas->verified_by_staff_id ?? $staff->id,
            'verified_at' => $aktivitas->verified_at ?? now(),
        ]);
    }

    /**
     * Copies what PDDIKTI will read from the programme, the group and its
     * location onto the activity row. Does not save.
     */
    private function isiDariKegiatan(
        AktivitasMahasiswa $aktivitas,
        KegiatanLapangan $kegiatan,
        ?KegiatanLapanganKelompok $kelompok,
    ): void {
        $lokasi = $kelompok?->lokasi;

        $aktivitas->fill([
            'tahun_akademik_id' => $kegiatan->tahun_akademik_id,
            'jenis' => $kegiatan->jenis,
            'judul' => collect([$kegiatan->nama, $kelompok?->nama, $lokasi?->nama])->filter()->implode(' · '),
            'mitra_nama' => $lokasi?->nama,
            'mitra_jenis' => $lokasi?->jenis->value,
            'lokasi' => $lokasi?->wilayah(),
            'tanggal_mulai' => $kegiatan->tanggal_mulai,
            'tanggal_selesai' => $kegiatan->tanggal_selesai,
            'dosen_pembimbing_id' => $kelompok?->dosen_pembimbing_id,
        ]);
    }

    /** @param Collection<int, AktivitasMahasiswa> $daftar */
    private function segarkanSemua(Collection $daftar): void
    {
        $daftar->load(['kegiatanLapangan', 'kelompok.lokasi']);

        foreach ($daftar as $aktivitas) {
            $this->isiDariKegiatan($aktivitas, $aktivitas->kegiatanLapangan, $aktivitas->kelompok);
            $aktivitas->save();
        }
    }

    /**
     * Free group places, one entry per group a location can still take.
     *
     * @return list<int>
     */
    private function slotLokasi(KegiatanLapangan $kegiatan): array
    {
        $slot = [];

        foreach ($kegiatan->lokasi()->withCount('kelompok')->get() as $lokasi) {
            for ($i = $lokasi->kelompok_count; $i < $lokasi->kuota_kelompok; $i++) {
                $slot[] = $lokasi->id;
            }
        }

        return $slot;
    }

    private function namaKelompok(int $nomor): string
    {
        return __('kegiatan_lapangan.nama_kelompok', ['nomor' => str_pad((string) $nomor, 2, '0', STR_PAD_LEFT)]);
    }

    private function pastikanBelumDikonversi(AktivitasMahasiswa $aktivitas): void
    {
        if ($aktivitas->krsKonversi()->exists()) {
            throw new AturanAkademikException(__('kegiatan_lapangan.galat.terkunci_konversi'));
        }
    }
}
