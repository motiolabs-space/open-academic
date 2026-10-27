<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\DTOs\Keuangan\AturanDenda;
use App\Enums\BentukDenda;
use App\Enums\InvoiceStatus;
use App\Enums\KomponenTarif;
use App\Enums\KrsStatus;
use App\Enums\PaymentStatus;
use App\Enums\PeriodeDenda;
use App\Enums\StudentStatus;
use App\Models\Akademik\Krs;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Keuangan\Pembayaran;
use App\Models\Keuangan\Tagihan;
use App\Models\Keuangan\TagihanItem;
use App\Models\Keuangan\Tarif;
use App\Models\Sdm\Staff;
use App\Services\Keuangan\DendaService;
use App\Services\Keuangan\PembayaranService;
use App\Services\Keuangan\TagihanKrsService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Fee matrix, term invoices and payments.
 *
 * The demo cohort spans all three settlement states on purpose: the KRS lock
 * only means something if some students are actually blocked by it.
 *
 * Sistem Informasi bills by credit (#41, ADR-030): a fixed SPP issued up front,
 * then a rate per SKS and two paid labs charged when the advisor approves the
 * KRS. Its students with an approved plan carry those lines already; approving
 * one of the plans still waiting shows the invoice grow.
 */
class KeuanganSeeder extends Seeder
{
    /** Sistem Informasi: the programme that bills by credit. */
    private const PRODI_PER_SKS = '57201';

    public function run(): void
    {
        $this->seedTarif();

        $term = TahunAkademik::aktif();
        $tarif = Tarif::aktif()->get();

        $mahasiswa = Mahasiswa::where('status', '!=', StudentStatus::DropOut->value)->get();

        foreach ($mahasiswa as $index => $student) {
            $tagihan = $this->buatTagihan($student, $term, $tarif, $index);

            $this->buatPembayaran($tagihan, $index);
        }

        $this->seedTagihanKrs($term);

        $this->seedDenda();
    }

    /**
     * Late fees on the invoices nobody paid, and one of them forgiven.
     *
     * The demo invoices fell due at the start of term — weeks ago, by the
     * calendar the demo anchors to — so the fee has something to accrue on.
     * Charged through the service with an explicit rule, not written to the
     * table, so the seed exercises the same path the nightly job takes; the
     * campus's own `.env` policy is left untouched.
     */
    private function seedDenda(): void
    {
        $aturan = new AturanDenda(
            aktif: true,
            bentuk: BentukDenda::Nominal,
            periode: PeriodeDenda::Hari,
            nominal: 10_000,
            persen: 0,
            maksimum: 500_000,
            tenggangHari: 7,
        );

        /** @var DendaService $denda */
        $denda = app(DendaService::class);
        $denda->jalankan($aturan);

        $staf = Staff::where('email', 'keuangan@demo.test')->first();
        $contoh = Tagihan::query()->where('denda', '>', 0)->orderBy('id')->first();

        if ($staf !== null && $contoh !== null) {
            $denda->bebaskan(
                $contoh,
                $staf,
                'Mahasiswa menunjukkan bukti transfer yang tertahan di bank sejak sebelum jatuh tempo.',
            );
        }
    }

    /**
     * Per-credit charges for the plans already approved, through the same
     * service an advisor's approval calls — so the lines, the extended due date
     * and the recomputed status are exactly what the live path produces.
     */
    private function seedTagihanKrs(TahunAkademik $term): void
    {
        $prodi = Prodi::where('kode', self::PRODI_PER_SKS)->first();

        if ($prodi === null) {
            return;
        }

        /** @var TagihanKrsService $layanan */
        $layanan = app(TagihanKrsService::class);

        Krs::query()
            ->where('tahun_akademik_id', $term->id)
            ->where('status', KrsStatus::Disetujui->value)
            ->whereHas('mahasiswa', fn ($q) => $q->where('prodi_id', $prodi->id))
            ->get()
            ->each(function (Krs $krs) use ($layanan): void {
                $lunas = Tagihan::where('mahasiswa_id', $krs->mahasiswa_id)
                    ->where('tahun_akademik_id', $krs->tahun_akademik_id)
                    ->first()?->status === InvoiceStatus::Lunas;

                $tagihan = $layanan->sinkronkan($krs);

                // The settled share of the cohort stays settled: these students
                // paid the per-credit lines too. Without it the demo login —
                // a student of this programme — would be withheld an exam card.
                if ($lunas && $tagihan !== null && $tagihan->sisa() > 0) {
                    Pembayaran::create([
                        'tagihan_id' => $tagihan->id,
                        'mahasiswa_id' => $tagihan->mahasiswa_id,
                        'nomor_transaksi' => 'TRX-KRS-'.$tagihan->id,
                        'gateway' => 'manual',
                        'channel' => 'bca_va',
                        'nominal' => $tagihan->sisa(),
                        'status' => PaymentStatus::Settlement,
                        'paid_at' => now()->subDay(),
                    ]);

                    app(PembayaranService::class)->hitungUlang($tagihan);
                }
            });
    }

    private function seedTarif(): void
    {
        $perSks = Prodi::where('kode', self::PRODI_PER_SKS)->first();

        if ($perSks !== null) {
            $this->seedTarifPerSks($perSks);
        }

        foreach (Prodi::where('id', '!=', $perSks?->id ?? 0)->get() as $prodi) {
            // UKT bands: the same programme charges different amounts by the
            // household income band assigned at admission.
            $ukt = [
                'I' => 500_000,
                'II' => 1_500_000,
                'III' => 3_500_000,
                'IV' => 5_000_000,
                'V' => 7_500_000,
            ];

            foreach ($ukt as $golongan => $nominal) {
                Tarif::create([
                    'prodi_id' => $prodi->id,
                    'golongan_ukt' => $golongan,
                    'komponen' => 'ukt',
                    'nama' => 'UKT Golongan '.$golongan,
                    'nominal' => $nominal,
                    'is_active' => true,
                ]);
            }

            Tarif::create([
                'prodi_id' => $prodi->id,
                'komponen' => 'praktikum',
                'nama' => 'Biaya Praktikum',
                'nominal' => 350_000,
                'is_active' => true,
            ]);
        }

        Tarif::create([
            'komponen' => KomponenTarif::Registrasi,
            'nama' => 'Registrasi Ulang',
            'nominal' => 150_000,
            'is_active' => true,
        ]);
    }

    private function seedTarifPerSks(Prodi $prodi): void
    {
        Tarif::create([
            'prodi_id' => $prodi->id,
            'komponen' => KomponenTarif::Spp,
            'nama' => 'SPP Tetap',
            'nominal' => 1_250_000,
            'is_active' => true,
        ]);

        Tarif::create([
            'prodi_id' => $prodi->id,
            'komponen' => KomponenTarif::PerSks,
            'nama' => 'Biaya per SKS',
            'nominal' => 175_000,
            'is_active' => true,
        ]);

        $praktikum = [
            'SI1202' => 'Praktikum Sistem Basis Data',
            'SI2102' => 'Praktikum Pemrograman Berbasis Web',
        ];

        foreach ($praktikum as $kode => $nama) {
            $mk = MataKuliah::where('prodi_id', $prodi->id)->where('kode', $kode)->first();

            if ($mk !== null) {
                Tarif::create([
                    'prodi_id' => $prodi->id,
                    'komponen' => KomponenTarif::MataKuliah,
                    'mata_kuliah_id' => $mk->id,
                    'nama' => $nama,
                    'nominal' => 300_000,
                    'is_active' => true,
                ]);
            }
        }
    }

    /** @param Collection<int, Tarif> $tarif */
    private function buatTagihan(Mahasiswa $student, TahunAkademik $term, Collection $tarif, int $index): Tagihan
    {
        $golongan = ['I', 'II', 'III', 'IV', 'V'][$index % 5];

        $komponen = $tarif
            // Charges that follow from the plan are billed on approval, not here.
            ->reject(fn (Tarif $t): bool => $t->komponen->dariKrs())
            ->filter(fn (Tarif $t): bool => $t->prodi_id === null || $t->prodi_id === $student->prodi_id)
            ->filter(fn (Tarif $t): bool => $t->golongan_ukt === null || $t->golongan_ukt === $golongan);

        $total = (int) $komponen->sum('nominal');

        $tagihan = Tagihan::create([
            'nomor' => sprintf('INV/%s/%05d', $term->kode, $index + 1),
            'mahasiswa_id' => $student->id,
            'tahun_akademik_id' => $term->id,
            'keterangan' => 'Biaya Kuliah '.$term->nama,
            'total' => $total,
            'terbayar' => 0,
            'status' => InvoiceStatus::BelumBayar,
            'jatuh_tempo' => $term->tanggal_mulai,
        ]);

        foreach ($komponen as $t) {
            TagihanItem::create([
                'tagihan_id' => $tagihan->id,
                'tarif_id' => $t->id,
                'nama' => $t->nama,
                'nominal' => $t->nominal,
            ]);
        }

        return $tagihan;
    }

    private function buatPembayaran(Tagihan $tagihan, int $index): void
    {
        // 70% settled, 15% partial, 15% untouched — the last group is what the
        // KRS payment gate and the arrears report exist for.
        [$porsi, $status] = match (true) {
            $index % 20 < 14 => [1.0, InvoiceStatus::Lunas],
            $index % 20 < 17 => [0.5, InvoiceStatus::Sebagian],
            default => [0.0, InvoiceStatus::BelumBayar],
        };

        if ($porsi <= 0.0) {
            return;
        }

        $nominal = (int) round($tagihan->total * $porsi);

        Pembayaran::create([
            'tagihan_id' => $tagihan->id,
            'mahasiswa_id' => $tagihan->mahasiswa_id,
            'nomor_transaksi' => 'TRX'.str_pad((string) ($index + 1), 8, '0', STR_PAD_LEFT),
            'gateway' => 'midtrans',
            'channel' => ['bca_va', 'bni_va', 'qris', 'gopay'][$index % 4],
            'nominal' => $nominal,
            'status' => PaymentStatus::Settlement,
            'va_number' => (string) fake()->numerify('##########'),
            'paid_at' => now()->subDays(fake()->numberBetween(1, 40)),
        ]);

        $tagihan->update([
            'terbayar' => $nominal,
            'status' => $status,
        ]);
    }
}
