<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\DTOs\Keuangan\AturanDenda;
use App\Enums\BentukDenda;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\PeriodeDenda;
use App\Enums\StudentStatus;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Keuangan\Pembayaran;
use App\Models\Keuangan\Tagihan;
use App\Models\Keuangan\TagihanItem;
use App\Models\Keuangan\Tarif;
use App\Models\Sdm\Staff;
use App\Services\Keuangan\DendaService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Fee matrix, term invoices and payments.
 *
 * The demo cohort spans all three settlement states on purpose: the KRS lock
 * only means something if some students are actually blocked by it.
 */
class KeuanganSeeder extends Seeder
{
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

    private function seedTarif(): void
    {
        foreach (Prodi::all() as $prodi) {
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
            'komponen' => 'registrasi',
            'nama' => 'Registrasi Ulang',
            'nominal' => 150_000,
            'is_active' => true,
        ]);
    }

    /** @param Collection<int, Tarif> $tarif */
    private function buatTagihan(Mahasiswa $student, TahunAkademik $term, Collection $tarif, int $index): Tagihan
    {
        $golongan = ['I', 'II', 'III', 'IV', 'V'][$index % 5];

        $komponen = $tarif
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
