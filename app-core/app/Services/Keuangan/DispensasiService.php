<?php

declare(strict_types=1);

namespace App\Services\Keuangan;

use App\DTOs\Keuangan\DispensasiData;
use App\Enums\InvoiceStatus;
use App\Enums\StatusDispensasi;
use App\Exceptions\AturanAkademikException;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Keuangan\DispensasiTagihan;
use App\Models\Keuangan\Tagihan;
use App\Models\Sdm\Staff;
use App\Notifications\Keuangan\DispensasiDiputus;
use App\Services\Notifikasi\Notifier;
use App\Support\Format;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Payment dispensations: a dated promise that a student may fill the KRS
 * before reaching the payment threshold.
 *
 * `tagihan.dispensasi_sampai` is what Tagihan::memenuhiSyaratKrs() and
 * DendaService read, and this service is the only code that writes it. Each
 * grant, refusal and revocation is a DispensasiTagihan row with the person
 * and the reason, plus an entry in the invoice's activity log.
 *
 * Two rules worth knowing (ADR-035 §2):
 *
 *  - A dispensation expires by date. Nothing runs at midnight; the gate
 *    simply compares the date with today.
 *  - Revoking ends it as of yesterday, rather than clearing the date. The
 *    gate closes today, and the late-fee clock starts from the revocation —
 *    the days the dispensation covered are never charged after the fact.
 */
class DispensasiService
{
    public function __construct(
        private readonly Notifier $notifier,
    ) {}

    /**
     * Grants a dispensation directly, replacing one still in force.
     */
    public function beri(Tagihan $tagihan, DispensasiData $data, Staff $staff): DispensasiTagihan
    {
        $this->pastikanData($data);
        $this->pastikanBelumLunas($tagihan);

        $dispensasi = DB::transaction(function () use ($tagihan, $data, $staff): DispensasiTagihan {
            $this->gantikanYangBerlaku($tagihan, $data, $staff);

            $dispensasi = DispensasiTagihan::create([
                'tagihan_id' => $tagihan->id,
                'status' => StatusDispensasi::Disetujui,
                'sampai' => $data->sampai->toDateString(),
                'alasan' => $data->alasan,
                'diputus_by_staff_id' => $staff->id,
                'diputus_at' => now(),
            ]);

            $this->terapkan($tagihan, $dispensasi, $staff);

            return $dispensasi;
        });

        $this->beritahu($dispensasi);

        return $dispensasi;
    }

    /**
     * A student asks for one. Nothing changes on the invoice until the finance
     * office decides.
     */
    public function ajukan(Tagihan $tagihan, Mahasiswa $mahasiswa, DispensasiData $data): DispensasiTagihan
    {
        $this->pastikanData($data);
        $this->pastikanBelumLunas($tagihan);

        if ($tagihan->dispensasi()->menunggu()->exists()) {
            throw new AturanAkademikException(__('keuangan.dispensasi.galat.sudah_ada_permohonan'));
        }

        if ($tagihan->dispensasiAktif()) {
            throw new AturanAkademikException(__('keuangan.dispensasi.galat.sudah_berlaku', [
                'tanggal' => Format::tanggal($tagihan->dispensasi_sampai),
            ]));
        }

        return DispensasiTagihan::create([
            'tagihan_id' => $tagihan->id,
            'status' => StatusDispensasi::Diajukan,
            'sampai' => $data->sampai->toDateString(),
            'alasan' => $data->alasan,
            'diajukan_by_mahasiswa_id' => $mahasiswa->id,
        ]);
    }

    /**
     * Approves a student's request, optionally with a different date than the
     * one asked for.
     */
    public function setujui(DispensasiTagihan $dispensasi, Staff $staff, ?Carbon $sampai = null, ?string $catatan = null): DispensasiTagihan
    {
        $this->pastikanMenunggu($dispensasi);

        $tagihan = $dispensasi->loadMissing('tagihan')->tagihan;
        $data = new DispensasiData($sampai ?? $dispensasi->sampai, $dispensasi->alasan);

        $this->pastikanData($data);
        $this->pastikanBelumLunas($tagihan);

        DB::transaction(function () use ($dispensasi, $tagihan, $data, $staff, $catatan): void {
            $this->gantikanYangBerlaku($tagihan, $data, $staff);

            $dispensasi->update([
                'status' => StatusDispensasi::Disetujui,
                'sampai' => $data->sampai->toDateString(),
                'diputus_by_staff_id' => $staff->id,
                'diputus_at' => now(),
                'catatan_keputusan' => $catatan,
            ]);

            $this->terapkan($tagihan, $dispensasi, $staff);
        });

        $this->beritahu($dispensasi->refresh());

        return $dispensasi;
    }

    public function tolak(DispensasiTagihan $dispensasi, Staff $staff, string $catatan): DispensasiTagihan
    {
        $this->pastikanMenunggu($dispensasi);

        if (blank($catatan)) {
            throw new AturanAkademikException(__('keuangan.dispensasi.galat.catatan_tolak_wajib'));
        }

        $dispensasi->update([
            'status' => StatusDispensasi::Ditolak,
            'diputus_by_staff_id' => $staff->id,
            'diputus_at' => now(),
            'catatan_keputusan' => $catatan,
        ]);

        $this->beritahu($dispensasi->refresh());

        return $dispensasi;
    }

    /**
     * Ends a dispensation in force. See the class comment for why the date is
     * moved to yesterday rather than cleared.
     */
    public function cabut(DispensasiTagihan $dispensasi, Staff $staff, string $alasan): DispensasiTagihan
    {
        if (!$dispensasi->masihBerlaku()) {
            throw new AturanAkademikException(__('keuangan.dispensasi.galat.tidak_berlaku'));
        }

        if (blank($alasan)) {
            throw new AturanAkademikException(__('keuangan.dispensasi.galat.alasan_wajib'));
        }

        DB::transaction(function () use ($dispensasi, $staff, $alasan): void {
            $this->akhiri($dispensasi, $staff, $alasan);

            $dispensasi->loadMissing('tagihan')->tagihan->recordActivity('dispensation_revoked', sprintf(
                'Dispensasi sampai %s dicabut oleh %s. Alasan: %s',
                Format::tanggal($dispensasi->sampai),
                $staff->nama,
                $alasan,
            ));
        });

        $this->beritahu($dispensasi->refresh());

        return $dispensasi;
    }

    private function pastikanData(DispensasiData $data): void
    {
        if (!$data->sampai->isAfter(Carbon::today())) {
            throw new AturanAkademikException(__('keuangan.dispensasi.galat.tanggal_lampau'));
        }

        if (blank($data->alasan)) {
            throw new AturanAkademikException(__('keuangan.dispensasi.galat.alasan_wajib'));
        }
    }

    private function pastikanBelumLunas(Tagihan $tagihan): void
    {
        if ($tagihan->status === InvoiceStatus::Lunas) {
            throw new AturanAkademikException(__('keuangan.dispensasi.galat.tagihan_lunas', ['nomor' => $tagihan->nomor]));
        }
    }

    private function pastikanMenunggu(DispensasiTagihan $dispensasi): void
    {
        if ($dispensasi->status !== StatusDispensasi::Diajukan) {
            throw new AturanAkademikException(__('keuangan.dispensasi.galat.bukan_menunggu'));
        }
    }

    /** A new grant supersedes the one in force, which is closed as revoked. */
    private function gantikanYangBerlaku(Tagihan $tagihan, DispensasiData $baru, Staff $staff): void
    {
        $tagihan->dispensasi()->berlaku()->get()->each(fn (DispensasiTagihan $lama) => $lama->update([
            'status' => StatusDispensasi::Dicabut,
            'dicabut_by_staff_id' => $staff->id,
            'dicabut_at' => now(),
            'alasan_cabut' => __('keuangan.dispensasi.digantikan', ['tanggal' => Format::tanggal($baru->sampai)]),
        ]));
    }

    private function terapkan(Tagihan $tagihan, DispensasiTagihan $dispensasi, Staff $staff): void
    {
        $tagihan->update([
            'dispensasi_sampai' => $dispensasi->sampai->toDateString(),
            'dispensasi_catatan' => mb_substr($dispensasi->alasan, 0, 255),
        ]);

        $tagihan->recordActivity('dispensation_granted', sprintf(
            'Dispensasi sampai %s diberikan oleh %s. Alasan: %s',
            Format::tanggal($dispensasi->sampai),
            $staff->nama,
            $dispensasi->alasan,
        ));
    }

    private function akhiri(DispensasiTagihan $dispensasi, Staff $staff, string $alasan): void
    {
        $dispensasi->update([
            'status' => StatusDispensasi::Dicabut,
            'dicabut_by_staff_id' => $staff->id,
            'dicabut_at' => now(),
            'alasan_cabut' => $alasan,
        ]);

        $kemarin = Carbon::yesterday();
        $tagihan = $dispensasi->loadMissing('tagihan')->tagihan;

        // Only the live date moves; an older, already-lapsed date stays put.
        if ($tagihan->dispensasi_sampai !== null && $tagihan->dispensasi_sampai->gt($kemarin)) {
            $tagihan->update(['dispensasi_sampai' => $kemarin->toDateString()]);
        }
    }

    private function beritahu(DispensasiTagihan $dispensasi): void
    {
        $dispensasi->loadMissing('tagihan.mahasiswa');

        $this->notifier->kirim($dispensasi->tagihan->mahasiswa, new DispensasiDiputus($dispensasi));
    }
}
