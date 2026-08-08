<?php

declare(strict_types=1);

namespace App\Services\Keuangan;

use App\Enums\InvoiceStatus;
use App\Enums\JenisItemTagihan;
use App\Enums\StudentStatus;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Keuangan\Tagihan;
use App\Models\Keuangan\TagihanItem;
use App\Models\Keuangan\Tarif;
use App\Notifications\Keuangan\TagihanDiterbitkan;
use App\Services\Notifikasi\Notifier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Issuing a term's invoices to a whole cohort at once.
 *
 * The alternative — a finance clerk creating several thousand invoices by hand
 * every semester — is how campuses end up with students who were never billed
 * and only discover it at graduation.
 *
 * Two properties matter more than speed:
 *
 *  1. **Idempotent.** Running it twice must not double-bill anyone. The unique
 *     index on (mahasiswa_id, tahun_akademik_id) is the real guarantee; this
 *     skips rather than fails so a partially-completed run can simply be
 *     repeated.
 *  2. **Previewable.** Nobody should discover what a run does by running it.
 */
class PenerbitanTagihanService
{
    public function __construct(
        private readonly TarifResolver $tarif,
        private readonly Notifier $notifier,
        private readonly PotonganService $potongan,
    ) {}

    /**
     * What a run would do, without doing it.
     *
     * @return array{akan_terbit: int, sudah_ada: int, tanpa_tarif: int, total_rupiah: int}
     */
    public function pratinjau(TahunAkademik $term, ?int $angkatan = null, ?int $prodiId = null): array
    {
        $ringkas = ['akan_terbit' => 0, 'sudah_ada' => 0, 'tanpa_tarif' => 0, 'total_rupiah' => 0];

        foreach ($this->sasaran($term, $angkatan, $prodiId) as $mahasiswa) {
            if ($this->sudahDitagih($mahasiswa, $term)) {
                $ringkas['sudah_ada']++;

                continue;
            }

            $tarif = $this->tarifUntuk($mahasiswa, $term);

            if ($tarif->isEmpty()) {
                $ringkas['tanpa_tarif']++;

                continue;
            }

            $ringkas['akan_terbit']++;
            $ringkas['total_rupiah'] += (int) $tarif->sum('nominal');
        }

        return $ringkas;
    }

    /**
     * Issues the invoices.
     *
     * @return array{terbit: int, dilewati: int, tanpa_tarif: int, total_rupiah: int}
     */
    public function terbitkan(TahunAkademik $term, ?int $angkatan = null, ?int $prodiId = null): array
    {
        $hasil = ['terbit' => 0, 'dilewati' => 0, 'tanpa_tarif' => 0, 'total_rupiah' => 0];

        /** @var array<int, Tagihan> */
        $terbit = [];

        foreach ($this->sasaran($term, $angkatan, $prodiId) as $mahasiswa) {
            if ($this->sudahDitagih($mahasiswa, $term)) {
                $hasil['dilewati']++;

                continue;
            }

            $tarif = $this->tarifUntuk($mahasiswa, $term);

            if ($tarif->isEmpty()) {
                // Silently billing nothing would look like a settled account on
                // every screen; leaving no invoice at all is visible.
                $hasil['tanpa_tarif']++;

                continue;
            }

            // Per student, not one transaction for the cohort: a single bad row
            // must not roll back four thousand correct invoices.
            DB::transaction(function () use ($mahasiswa, $term, $tarif, &$hasil): void {
                $total = (int) $tarif->sum('nominal');

                $tagihan = Tagihan::create([
                    'nomor' => 'INV-'.$term->kode.'-'.Str::upper(Str::random(8)),
                    'mahasiswa_id' => $mahasiswa->id,
                    'tahun_akademik_id' => $term->id,
                    'keterangan' => 'Biaya kuliah '.$term->nama,
                    'total' => $total,
                    'terbayar' => 0,
                    'status' => InvoiceStatus::BelumBayar,
                    'jatuh_tempo' => now()->addDays((int) config('payment.invoice.due_days', 30)),
                ]);

                foreach ($tarif as $baris) {
                    TagihanItem::create([
                        'tagihan_id' => $tagihan->id,
                        'tarif_id' => $baris->id,
                        'jenis' => JenisItemTagihan::Tagihan,
                        'nama' => $baris->nama,
                        'nominal' => $baris->nominal,
                    ]);
                }

                /*
                 * Reductions are applied before the invoice is announced.
                 *
                 * A scholarship holder must never receive a bill for the gross
                 * amount followed by a correction — they will have paid the
                 * first one, and the overpayment then has to be chased back out
                 * of the system by hand.
                 */
                $this->potongan->terapkan($tagihan);

                $hasil['terbit']++;
                $hasil['total_rupiah'] += (int) $tagihan->fresh()->total;

                $terbit[] = $tagihan;
            });
        }

        /*
         * Announced after the loop, not inside each transaction.
         *
         * Issuing four thousand invoices is one administrative act; sending
         * inside the per-student transaction would put four thousand queue
         * writes in the middle of it, and a mail failure on student 300 would
         * roll back an invoice that was already correct.
         */
        foreach ($terbit as $tagihan) {
            $this->notifier->kirim($tagihan->mahasiswa, new TagihanDiterbitkan($tagihan));
        }

        return $hasil;
    }

    /** @return Collection<int, Mahasiswa> */
    private function sasaran(TahunAkademik $term, ?int $angkatan, ?int $prodiId): Collection
    {
        return Mahasiswa::query()
            // Students on leave or already graduated are not billed for a term
            // they are not studying in.
            ->where('status', StudentStatus::Aktif->value)
            ->when($angkatan !== null, fn ($q) => $q->where('angkatan', $angkatan))
            ->when($prodiId !== null, fn ($q) => $q->where('prodi_id', $prodiId))
            ->get();
    }

    private function sudahDitagih(Mahasiswa $mahasiswa, TahunAkademik $term): bool
    {
        return Tagihan::where('mahasiswa_id', $mahasiswa->id)
            ->where('tahun_akademik_id', $term->id)
            ->exists();
    }

    /**
     * Delegated so the matching rule has exactly one definition.
     *
     * This method used to run its own query and sum every matching row, which
     * double-billed anybody covered by both a general schedule and a more
     * specific override.
     *
     * @return Collection<int, Tarif>
     */
    private function tarifUntuk(Mahasiswa $mahasiswa, TahunAkademik $term): Collection
    {
        return $this->tarif->untuk($mahasiswa, $term);
    }
}
