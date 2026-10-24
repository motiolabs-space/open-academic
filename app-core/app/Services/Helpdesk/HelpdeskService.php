<?php

declare(strict_types=1);

namespace App\Services\Helpdesk;

use App\DTOs\Helpdesk\BalasanData;
use App\DTOs\Helpdesk\DisposisiData;
use App\DTOs\Helpdesk\TiketBaruData;
use App\Enums\StatusTiket;
use App\Exceptions\AturanAkademikException;
use App\Models\Helpdesk\HelpdeskBalasan;
use App\Models\Helpdesk\HelpdeskKategori;
use App\Models\Helpdesk\HelpdeskTiket;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Models\Sdm\UnitKerja;
use App\Notifications\Helpdesk\TiketDibalas;
use App\Notifications\Helpdesk\TiketDidisposisi;
use App\Notifications\Helpdesk\TiketSelesai;
use App\Services\Berkas\BerkasService;
use App\Services\Notifikasi\Notifier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Tickets from students and lecturers to the campus, and what happens to them.
 *
 * Two rules shape everything here.
 *
 * **Status moves only along the table in StatusTiket.** A screen can only ask;
 * this class decides, and it refuses anything the table does not allow. The
 * same guard runs whether the request came from a form, a command, or a test.
 *
 * **The applicant is told when something happens to them, not when something
 * happens internally.** Disposition, a public reply, and resolution notify; an
 * internal note and a priority change do not. The notification category is one
 * the person may switch off — missing a helpdesk reply costs them a slow
 * answer, not a semester, which is the line NOTIFIKASI.md draws.
 */
class HelpdeskService
{
    public function __construct(
        private readonly BerkasService $berkas,
        private readonly Notifier $notifier,
    ) {}

    /**
     * Opens a ticket.
     *
     * The category decides the first stop and the clock: the ticket lands in
     * the category's default unit, and the SLA deadline is fixed now rather
     * than recomputed later, so a category edited next month does not move the
     * goalposts on tickets already in the queue.
     */
    public function buat(Mahasiswa|Dosen $pemohon, TiketBaruData $data): HelpdeskTiket
    {
        $kategori = HelpdeskKategori::query()->aktif()->find($data->kategoriId);

        if ($kategori === null) {
            throw new AturanAkademikException('Kategori tiket tidak dikenal atau sudah tidak aktif.');
        }

        $slaJam = $kategori->slaJamEfektif();

        $lampiran = $data->lampiran === null
            ? null
            : $this->berkas->simpan($data->lampiran, config('helpdesk.folder_lampiran').'/tiket');

        return $this->denganNomorUnik(fn (string $nomor): HelpdeskTiket => HelpdeskTiket::create([
            'nomor' => $nomor,
            'pemohon_type' => $pemohon->getMorphClass(),
            'pemohon_id' => $pemohon->getKey(),
            'helpdesk_kategori_id' => $kategori->id,
            'judul' => $data->judul,
            'isi' => $data->isi,
            'status' => StatusTiket::Baru,
            'prioritas' => $data->prioritas,
            'unit_kerja_id' => $kategori->unit_kerja_id,
            'lampiran_path' => $lampiran,
            'lampiran_nama' => $data->lampiran?->getClientOriginalName(),
            'tenggat_sla_at' => $slaJam === null ? null : now()->addHours($slaJam),
            'balasan_terakhir_at' => now(),
        ]));
    }

    /**
     * Routes a ticket to a unit and/or a named officer.
     *
     * Re-disposition is allowed from any open state: a ticket that reached the
     * wrong office is the commonest reason this method is called. Named officer
     * must belong to the named unit when both are given — otherwise the queue
     * shows a ticket in one office handled by somebody from another, and
     * neither office thinks it is theirs.
     */
    public function disposisi(HelpdeskTiket $tiket, Staff $oleh, DisposisiData $data): HelpdeskTiket
    {
        if (!$data->menamaiSeseorang()) {
            throw new AturanAkademikException('Disposisi wajib menyebut unit kerja atau petugas yang menangani.');
        }

        $this->pastikanTransisi($tiket, StatusTiket::Didisposisi);

        $petugas = $data->petugasStaffId === null ? null : Staff::find($data->petugasStaffId);

        if ($data->petugasStaffId !== null && $petugas === null) {
            throw new AturanAkademikException('Petugas yang dipilih tidak ditemukan.');
        }

        $unitId = $data->unitKerjaId ?? $petugas?->unit_kerja_id ?? $tiket->unit_kerja_id;

        if ($unitId === null) {
            throw new AturanAkademikException('Petugas tersebut belum ditempatkan di unit kerja mana pun; sebutkan unitnya.');
        }

        $unit = UnitKerja::query()->aktif()->find($unitId);

        if ($unit === null) {
            throw new AturanAkademikException('Unit kerja tujuan tidak ditemukan atau sudah nonaktif.');
        }

        if ($petugas !== null && $data->unitKerjaId !== null && $petugas->unit_kerja_id !== $unit->id) {
            throw new AturanAkademikException(sprintf(
                '%s bukan staf %s. Pilih petugas dari unit yang sama, atau biarkan unitnya mengikuti petugas.',
                $petugas->nama,
                $unit->nama,
            ));
        }

        DB::transaction(function () use ($tiket, $oleh, $data, $unit, $petugas): void {
            $tiket->forceFill([
                'status' => StatusTiket::Didisposisi,
                'unit_kerja_id' => $unit->id,
                'petugas_staff_id' => $petugas?->id,
                'prioritas' => $data->prioritas ?? $tiket->prioritas,
                'didisposisi_at' => now(),
            ])->save();

            $this->catatInternal($tiket, $oleh, sprintf(
                'Didisposisi ke %s%s oleh %s.%s',
                $unit->nama,
                $petugas !== null ? ' ('.$petugas->nama.')' : '',
                $oleh->nama,
                $data->catatan !== null ? ' Catatan: '.$data->catatan : '',
            ));
        });

        $this->notifier->kirim($tiket->pemohon, new TiketDidisposisi($tiket->refresh()));

        return $tiket;
    }

    /**
     * Adds a reply or an internal note.
     *
     * Who wrote it decides what it does to the status:
     *
     *  - staff, public → the ball is in the applicant's court (menunggu pemohon)
     *  - staff, internal → nothing moves, nobody outside is told
     *  - applicant → the ball is back with the campus (diproses), and a
     *    resolved ticket reopens rather than silently accepting a reply that
     *    nobody will read
     *
     * A closed ticket takes no replies from anyone. The applicant opens a new
     * one, which keeps the old trail as it was when it was closed.
     */
    public function balas(HelpdeskTiket $tiket, Staff|Dosen|Mahasiswa $penulis, BalasanData $data): HelpdeskBalasan
    {
        if ($tiket->status === StatusTiket::Ditutup) {
            throw new AturanAkademikException(
                'Tiket sudah ditutup dan tidak menerima balasan. Buka tiket baru bila masalahnya berlanjut.',
            );
        }

        if (blank($data->isi)) {
            throw new AturanAkademikException('Isi balasan tidak boleh kosong.');
        }

        $dariStaf = $penulis instanceof Staff;

        if ($data->internal && !$dariStaf) {
            throw new AturanAkademikException('Catatan internal hanya dapat ditulis oleh staf.');
        }

        if (!$dariStaf && !$tiket->milik($penulis)) {
            throw new AturanAkademikException('Hanya pemohon tiket ini yang dapat membalasnya.');
        }

        $lampiran = $data->lampiran === null
            ? null
            : $this->berkas->simpan($data->lampiran, config('helpdesk.folder_lampiran').'/balasan');

        $balasan = DB::transaction(function () use ($tiket, $penulis, $data, $dariStaf, $lampiran): HelpdeskBalasan {
            $balasan = $tiket->balasan()->create([
                'penulis_type' => $penulis->getMorphClass(),
                'penulis_id' => $penulis->getKey(),
                'isi' => $data->isi,
                'is_internal' => $data->internal,
                'lampiran_path' => $lampiran,
                'lampiran_nama' => $data->lampiran?->getClientOriginalName(),
            ]);

            if ($data->internal) {
                return $balasan;
            }

            $perubahan = ['balasan_terakhir_at' => now()];

            if ($dariStaf) {
                $perubahan['ditanggapi_at'] = $tiket->ditanggapi_at ?? now();

                // The first person to answer takes the ticket, unless somebody
                // was already named. A reply with no owner is how tickets get
                // answered twice by two offices.
                $perubahan['petugas_staff_id'] = $tiket->petugas_staff_id ?? $penulis->id;
                $perubahan['unit_kerja_id'] = $tiket->unit_kerja_id ?? $penulis->unit_kerja_id;

                if ($tiket->status->dapatKe(StatusTiket::MenungguPemohon)) {
                    $perubahan['status'] = StatusTiket::MenungguPemohon;
                }
            } elseif (in_array($tiket->status, [StatusTiket::MenungguPemohon, StatusTiket::Selesai], true)) {
                // Only these two mean "the campus was waiting on me": answering
                // them hands the ticket back. A reply on a new or freshly
                // dispositioned ticket is extra information, not a hand-back,
                // and must not pretend somebody has started processing it.
                $perubahan['status'] = StatusTiket::Diproses;
                $perubahan['selesai_at'] = null;
            }

            $tiket->forceFill($perubahan)->save();

            return $balasan;
        });

        if ($dariStaf && !$data->internal) {
            $this->notifier->kirim($tiket->pemohon, new TiketDibalas($tiket->refresh(), $balasan));
        }

        return $balasan;
    }

    /**
     * Moves a ticket to another state by hand.
     *
     * Disposition and replies move state as a side effect; this is for the
     * remaining moves — picking a ticket up, resolving it, closing it — and it
     * runs through the same transition table as everything else.
     */
    public function ubahStatus(HelpdeskTiket $tiket, Staff $oleh, StatusTiket $ke, ?string $catatan = null): HelpdeskTiket
    {
        $this->pastikanTransisi($tiket, $ke);

        if ($ke === StatusTiket::Ditutup && blank($catatan)) {
            throw new AturanAkademikException('Menutup tiket wajib disertai alasan yang dapat dibaca pemohon.');
        }

        DB::transaction(function () use ($tiket, $oleh, $ke, $catatan): void {
            $perubahan = ['status' => $ke];

            if ($ke === StatusTiket::Diproses) {
                $perubahan['petugas_staff_id'] = $tiket->petugas_staff_id ?? $oleh->id;
                $perubahan['unit_kerja_id'] = $tiket->unit_kerja_id ?? $oleh->unit_kerja_id;
                $perubahan['selesai_at'] = null;
            }

            if ($ke === StatusTiket::Selesai) {
                $perubahan['selesai_at'] = now();
            }

            if ($ke === StatusTiket::Ditutup) {
                $perubahan['ditutup_at'] = now();
            }

            $tiket->forceFill($perubahan)->save();

            if (filled($catatan)) {
                // Visible to the applicant: a resolution or closure note is the
                // answer they were waiting for, not an aside between colleagues.
                $tiket->balasan()->create([
                    'penulis_type' => $oleh->getMorphClass(),
                    'penulis_id' => $oleh->id,
                    'isi' => $catatan,
                    'is_internal' => $ke === StatusTiket::Diproses,
                ]);
            }
        });

        if ($ke === StatusTiket::Selesai || $ke === StatusTiket::Ditutup) {
            $this->notifier->kirim($tiket->pemohon, new TiketSelesai($tiket->refresh()));
        }

        return $tiket->refresh();
    }

    public function selesaikan(HelpdeskTiket $tiket, Staff $oleh, ?string $catatan = null): HelpdeskTiket
    {
        return $this->ubahStatus($tiket, $oleh, StatusTiket::Selesai, $catatan);
    }

    public function tutup(HelpdeskTiket $tiket, Staff $oleh, string $alasan): HelpdeskTiket
    {
        return $this->ubahStatus($tiket, $oleh, StatusTiket::Ditutup, $alasan);
    }

    /**
     * Stamps every ticket whose deadline has passed while the campus still owes
     * an answer, and returns how many were newly stamped.
     *
     * Never closes, never reassigns, never notifies the applicant — a missed
     * deadline is information for the office, not a decision about the ticket.
     * Tickets waiting on the applicant are not late, whatever the clock says.
     */
    public function tandaiLewatSla(): int
    {
        return HelpdeskTiket::query()
            ->menungguKampus()
            ->whereNull('lewat_sla_at')
            ->whereNotNull('tenggat_sla_at')
            ->where('tenggat_sla_at', '<', now())
            ->update(['lewat_sla_at' => now()]);
    }

    /**
     * Tickets a given staff member may work on.
     *
     * Everyone with the manage permission sees the whole campus; everyone else
     * sees their own unit's queue plus anything assigned to them by name.
     *
     * @return Builder<HelpdeskTiket>
     */
    public function antreanUntuk(Staff $staf): Builder
    {
        $query = HelpdeskTiket::query();

        if ($this->bolehSemua($staf)) {
            return $query;
        }

        return $query->where(function ($q) use ($staf): void {
            $q->where('petugas_staff_id', $staf->id);

            if ($staf->unit_kerja_id !== null) {
                $q->orWhere('unit_kerja_id', $staf->unit_kerja_id);
            }
        });
    }

    public function bolehSemua(Staff $staf): bool
    {
        try {
            return $staf->hasPermissionTo('helpdesk.manage', 'staff');
        } catch (\Throwable) {
            return false;
        }
    }

    private function pastikanTransisi(HelpdeskTiket $tiket, StatusTiket $ke): void
    {
        if (!$tiket->status->dapatKe($ke)) {
            throw new AturanAkademikException(sprintf(
                'Tiket berstatus %s tidak dapat diubah menjadi %s.',
                $tiket->status->label(),
                $ke->label(),
            ));
        }
    }

    private function catatInternal(HelpdeskTiket $tiket, Staff $oleh, string $isi): void
    {
        $tiket->balasan()->create([
            'penulis_type' => $oleh->getMorphClass(),
            'penulis_id' => $oleh->id,
            'isi' => $isi,
            'is_internal' => true,
        ]);
    }

    /**
     * Claims the next number in the year's sequence.
     *
     * Read-then-insert would race under two simultaneous filings; the unique
     * index on `nomor` is the real guard, and this simply retries behind it.
     *
     * @template T of Model
     *
     * @param callable(string): T $buat
     * @return T
     */
    private function denganNomorUnik(callable $buat): Model
    {
        $tahun = now()->year;

        for ($percobaan = 0; $percobaan < 5; $percobaan++) {
            $urut = HelpdeskTiket::withTrashed()
                ->whereYear('created_at', $tahun)
                ->count() + 1 + $percobaan;

            $nomor = str_replace(
                ['{tahun}', '{urut}'],
                [(string) $tahun, str_pad((string) $urut, (int) config('helpdesk.panjang_urut'), '0', STR_PAD_LEFT)],
                (string) config('helpdesk.pola_nomor'),
            );

            try {
                return $buat($nomor);
            } catch (UniqueConstraintViolationException) {
                continue;
            }
        }

        throw new AturanAkademikException('Nomor tiket tidak dapat ditetapkan. Coba lagi sesaat lagi.');
    }
}
