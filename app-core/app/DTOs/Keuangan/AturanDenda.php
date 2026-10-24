<?php

declare(strict_types=1);

namespace App\DTOs\Keuangan;

use App\Enums\BentukDenda;
use App\Enums\PeriodeDenda;
use App\Support\Format;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * A campus's late-fee policy, read once from config and passed around whole.
 *
 * Exists so the service, the command, the reminder and the student's screen
 * all describe the same rule — and so a test can hand the service a rule
 * without touching global config.
 */
final readonly class AturanDenda
{
    public function __construct(
        public bool $aktif,
        public BentukDenda $bentuk,
        public PeriodeDenda $periode,
        public int $nominal,
        public int $persen,
        public int $maksimum,
        public int $tenggangHari,
    ) {
        if ($nominal < 0 || $persen < 0 || $maksimum < 0 || $tenggangHari < 0) {
            throw new InvalidArgumentException('Aturan denda tidak menerima angka negatif.');
        }
    }

    public static function dariConfig(): self
    {
        $c = (array) config('payment.denda', []);

        return new self(
            aktif: (bool) ($c['aktif'] ?? false),
            bentuk: BentukDenda::from((string) ($c['bentuk'] ?? 'nominal')),
            periode: PeriodeDenda::from((string) ($c['periode'] ?? 'hari')),
            nominal: (int) ($c['nominal'] ?? 0),
            persen: (int) ($c['persen'] ?? 0),
            maksimum: (int) ($c['maksimum'] ?? 0),
            tenggangHari: (int) ($c['tenggang_hari'] ?? 0),
        );
    }

    /** The last day a payment is still free of charge. */
    public function batasBebas(Carbon $jatuhTempo): Carbon
    {
        return $jatuhTempo->copy()->startOfDay()->addDays($this->tenggangHari);
    }

    /** Chargeable periods that have begun by `$hari` for an invoice due `$jatuhTempo`. */
    public function periodeSampai(Carbon $jatuhTempo, Carbon $hari): int
    {
        $batas = $this->batasBebas($jatuhTempo);
        $hari = $hari->copy()->startOfDay();

        if ($hari->lte($batas)) {
            return 0;
        }

        return $this->periode->dariHari((int) $batas->diffInDays($hari));
    }

    /**
     * The fee for one period, in whole rupiah.
     *
     * Percent is of the outstanding principal, rounded down — a fee is never
     * rounded in the campus's favour, and no float is involved.
     */
    public function perPeriode(int $sisaPokok): int
    {
        return match ($this->bentuk) {
            BentukDenda::Nominal => $this->nominal,
            BentukDenda::Persen => intdiv(max(0, $sisaPokok) * $this->persen, 100),
        };
    }

    /** One sentence a student can act on, for reminders and the invoice screen. */
    public function deskripsi(): string
    {
        $tarif = match ($this->bentuk) {
            BentukDenda::Nominal => __('keuangan.denda.tarif_nominal', [
                'nominal' => Format::rupiah($this->nominal),
                'periode' => $this->periode->label(),
            ]),
            BentukDenda::Persen => __('keuangan.denda.tarif_persen', [
                'persen' => $this->persen,
                'periode' => $this->periode->label(),
            ]),
        };

        $kalimat = __('keuangan.denda.aturan', ['tarif' => $tarif]);

        if ($this->tenggangHari > 0) {
            $kalimat .= ' '.__('keuangan.denda.tenggang', ['hari' => $this->tenggangHari]);
        }

        if ($this->maksimum > 0) {
            $kalimat .= ' '.__('keuangan.denda.maksimum', ['maksimum' => Format::rupiah($this->maksimum)]);
        }

        return $kalimat;
    }
}
