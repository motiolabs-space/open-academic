<?php

declare(strict_types=1);

namespace App\Services\Keuangan;

use App\Enums\JenisItemTagihan;
use App\Enums\KomponenTarif;
use App\Enums\KrsStatus;
use App\Models\Akademik\Krs;
use App\Models\Akademik\KrsDetail;
use App\Models\Akademik\TahunAkademik;
use App\Models\Keuangan\Tagihan;
use App\Models\Keuangan\TagihanItem;
use App\Models\Keuangan\Tarif;
use App\Notifications\Keuangan\TagihanDisesuaikan;
use App\Notifications\Keuangan\TagihanDiterbitkan;
use App\Services\Akuntansi\PenjurnalanService;
use App\Services\Notifikasi\Notifier;
use App\Support\Format;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Billing what the approved study plan contains: per credit, and per course.
 *
 * Called whenever an advisor approves a plan — the first approval and every
 * re-approval after a revision. It compares the plan with the KRS-derived lines
 * already on the term invoice and writes only the difference:
 *
 *  - a course in the plan with no charge yet gets one;
 *  - a charged course no longer in the plan gets a **correction line** naming
 *    the charge it corrects — the negative of it when the class was dropped
 *    before the deadline in `payment.krs.batas_pengurangan`, Rp0 when after,
 *    so the student can see why the figure did not fall.
 *
 * Nothing is ever edited or deleted. The invoice shows every charge and every
 * correction, and running this twice on the same plan writes nothing.
 *
 * Charges are matched by course, not by plan row: swapping class A for class B
 * of the same course is not a new purchase. And they are not re-priced — a
 * tariff changed after billing applies to the next term, not to a charge the
 * student may already have paid. See ADR-030.
 */
class TagihanKrsService
{
    public function __construct(
        private readonly TarifResolver $tarif,
        private readonly PenerbitanTagihanService $penerbitan,
        private readonly PotonganService $potongan,
        private readonly Notifier $notifier,
        private readonly PenjurnalanService $penjurnalan,
    ) {}

    /**
     * Brings the term invoice in line with the approved plan.
     *
     * Returns the invoice, or null when there is nothing to bill — no tariff
     * prices anything in the plan and no invoice exists. A campus that does not
     * bill by credit therefore sees no invoice appear on approval.
     */
    public function sinkronkan(Krs $krs): ?Tagihan
    {
        $krs->loadMissing(['mahasiswa', 'tahunAkademik']);

        $mahasiswa = $krs->mahasiswa;
        $term = $krs->tahunAkademik;

        $tagihan = Tagihan::where('mahasiswa_id', $mahasiswa->id)
            ->where('tahun_akademik_id', $term->id)
            ->first();

        // Only an approved plan is a purchase. A draft under revision keeps the
        // charges of its last approval until it is approved again.
        if ($krs->status !== KrsStatus::Disetujui) {
            return $tagihan;
        }

        // In plan order, so the invoice lists courses as the student chose them.
        $detail = $krs->detail()->with('kelasKuliah.mataKuliah')->orderBy('id')->get();
        $diambil = $detail->map(fn (KrsDetail $d): int => (int) $d->kelasKuliah->mata_kuliah_id)->all();

        $sudahAda = $tagihan === null
            ? collect()
            : $tagihan->item()->dariKrs()->with('krsDetail')->orderBy('id')->get();

        // Corrections keyed by the charge they correct.
        $koreksi = $sudahAda->whereNotNull('koreksi_atas_item_id')->keyBy('koreksi_atas_item_id');
        $berlaku = $this->bebanBerlaku($sudahAda, $koreksi);

        $baru = $this->bebanDiinginkan($krs, $detail)
            ->reject(fn (array $b, string $kunci): bool => $berlaku->has($kunci));

        $dibatalkan = $berlaku->reject(
            fn (TagihanItem $item): bool => $koreksi->has($item->id)
                || in_array((int) $item->mata_kuliah_id, $diambil, true),
        );

        if ($baru->isEmpty() && $dibatalkan->isEmpty()) {
            return $tagihan;
        }

        $sebelum = $tagihan === null ? 0 : (int) $tagihan->total;
        $dibuat = $tagihan === null;

        /** @var array<int, TagihanItem> */
        $baris = [];

        $tagihan = DB::transaction(function () use ($mahasiswa, $term, $tagihan, $baru, $dibatalkan, &$baris): Tagihan {
            $tagihan ??= $this->penerbitan->buatTagihan($mahasiswa, $term, $this->tarif->untuk($mahasiswa, $term));

            foreach ($baru as $beban) {
                $baris[] = TagihanItem::create([
                    'tagihan_id' => $tagihan->id,
                    'tarif_id' => $beban['tarif']->id,
                    'jenis' => JenisItemTagihan::Tagihan,
                    'komponen' => $beban['komponen'],
                    'mata_kuliah_id' => $beban['detail']->kelasKuliah->mata_kuliah_id,
                    'krs_detail_id' => $beban['detail']->id,
                    'nama' => $beban['nama'],
                    'nominal' => $beban['nominal'],
                ]);
            }

            foreach ($dibatalkan as $item) {
                $baris[] = $this->koreksi($item, $term);
            }

            $perubahan = ['nominal_krs' => (int) $tagihan->item()->dariKrs()->sum('nominal')];

            // Due after approval, never earlier than the invoice already was.
            $tempo = Carbon::today()->addDays((int) config('payment.krs.jatuh_tempo_hari', 14));

            if ($baru->isNotEmpty() && $tagihan->jatuh_tempo->lt($tempo)) {
                $perubahan['jatuh_tempo'] = $tempo;
            }

            $tagihan->update($perubahan);

            // Scholarships and recomputation, in one place: a percentage award
            // covers the new lines the same way it covers the old ones, and
            // `total`, `terbayar` and `status` are derived again from the rows.
            $this->potongan->terapkan($tagihan);

            $tagihan->recordActivity('krs_billed', sprintf(
                'Tagihan disesuaikan dengan KRS %s yang disetujui: %d baris ditambahkan, %d dikoreksi.',
                $term->kode,
                $baru->count(),
                $dibatalkan->count(),
            ));

            return $tagihan->refresh();
        });

        /*
         * Announced and booked after the transaction, as in the cohort run: a
         * mail server or an accounting outbox that fails must not undo charges
         * that are already correct.
         */
        if ($dibuat) {
            $this->notifier->kirim($mahasiswa, new TagihanDiterbitkan($tagihan));
            $this->penjurnalan->tagihanTerbit($tagihan->fresh(['mahasiswa', 'item']));
        } else {
            $this->notifier->kirim($mahasiswa, new TagihanDisesuaikan($tagihan, (int) $tagihan->total - $sebelum));

            foreach ($baris as $item) {
                $this->penjurnalan->tagihanDisesuaikan($item->refresh()->load('tagihan.mahasiswa'));
            }
        }

        return $tagihan;
    }

    /**
     * Whether dropping a class on this date still takes its charge off.
     *
     * A term without the chosen date set has no deadline, and the drop reduces.
     */
    public function mengurangiTagihan(TahunAkademik $term, CarbonInterface $tanggal): bool
    {
        $aturan = (string) config('payment.krs.batas_pengurangan', 'krs_selesai');

        if ($aturan === 'tidak_pernah') {
            return false;
        }

        $batas = $aturan === 'krs_perubahan_selesai'
            ? ($term->krs_perubahan_selesai ?? $term->krs_selesai)
            : ($term->krs_selesai ?? $term->krs_perubahan_selesai);

        return $batas === null || Carbon::parse($tanggal)->startOfDay()->lte($batas->copy()->startOfDay());
    }

    /**
     * What the plan should be billed, keyed `komponen:mata_kuliah_id`.
     *
     * @param Collection<int, KrsDetail> $detail
     * @return Collection<string, array{tarif: Tarif, komponen: KomponenTarif, detail: KrsDetail, nama: string, nominal: int}>
     */
    private function bebanDiinginkan(Krs $krs, Collection $detail): Collection
    {
        $perSks = $this->tarif->perSks($krs->mahasiswa, $krs->tahunAkademik);
        $perMk = $this->tarif->perMataKuliah($krs->mahasiswa, $krs->tahunAkademik);

        $beban = collect();

        foreach ($detail as $d) {
            $mk = $d->kelasKuliah->mataKuliah;

            if ($perSks !== null && (int) $d->sks > 0 && (int) $perSks->nominal > 0) {
                $beban->put(KomponenTarif::PerSks->value.':'.$mk->id, [
                    'tarif' => $perSks,
                    'komponen' => KomponenTarif::PerSks,
                    'detail' => $d,
                    'nama' => __('keuangan.krs.baris_per_sks', [
                        'mk' => $mk->nama,
                        'sks' => $d->sks,
                        'tarif' => Format::rupiah($perSks->nominal),
                    ]),
                    // Integer times integer: whole rupiah, no rounding anywhere.
                    'nominal' => (int) $d->sks * (int) $perSks->nominal,
                ]);
            }

            $tarifMk = $perMk->get($mk->id);

            if ($tarifMk !== null && (int) $tarifMk->nominal > 0) {
                $beban->put(KomponenTarif::MataKuliah->value.':'.$mk->id, [
                    'tarif' => $tarifMk,
                    'komponen' => KomponenTarif::MataKuliah,
                    'detail' => $d,
                    'nama' => $tarifMk->nama,
                    'nominal' => (int) $tarifMk->nominal,
                ]);
            }
        }

        return $beban;
    }

    /**
     * KRS-derived charges the student still owes, keyed like bebanDiinginkan().
     *
     * A charge stays owed until a correction with a non-zero amount reverses
     * it. One answered by a Rp0 correction — dropped after the deadline — is
     * still paid for, so re-adding the course does not bill it a second time.
     *
     * @param Collection<int, TagihanItem> $sudahAda
     * @param Collection<int, TagihanItem> $koreksi keyed by koreksi_atas_item_id
     * @return Collection<string, TagihanItem>
     */
    private function bebanBerlaku(Collection $sudahAda, Collection $koreksi): Collection
    {
        return $sudahAda
            ->filter(fn (TagihanItem $i): bool => $i->koreksi_atas_item_id === null && (int) $i->nominal > 0)
            ->reject(fn (TagihanItem $i): bool => (int) ($koreksi->get($i->id)?->nominal ?? 0) !== 0)
            ->keyBy(fn (TagihanItem $i): string => $i->komponen->value.':'.$i->mata_kuliah_id);
    }

    /** The correction line for a charged course that left the plan. */
    private function koreksi(TagihanItem $item, TahunAkademik $term): TagihanItem
    {
        // Plan rows are soft-deleted, so the drop date survives the drop.
        $tanggal = $item->krsDetail?->deleted_at ?? Carbon::now();
        $mengurangi = $this->mengurangiTagihan($term, $tanggal);

        return TagihanItem::create([
            'tagihan_id' => $item->tagihan_id,
            'tarif_id' => $item->tarif_id,
            'jenis' => JenisItemTagihan::Tagihan,
            'komponen' => $item->komponen,
            'mata_kuliah_id' => $item->mata_kuliah_id,
            'krs_detail_id' => $item->krs_detail_id,
            'koreksi_atas_item_id' => $item->id,

            // Same name as the charge, so a scholarship that covers the charge
            // by name nets the correction against it.
            'nama' => $item->nama,
            'nominal' => $mengurangi ? -((int) $item->nominal) : 0,
            'alasan' => __($mengurangi ? 'keuangan.krs.alasan_dikurangi' : 'keuangan.krs.alasan_tidak_dikurangi', [
                'tanggal' => Format::tanggal($tanggal),
            ]),
        ]);
    }
}
