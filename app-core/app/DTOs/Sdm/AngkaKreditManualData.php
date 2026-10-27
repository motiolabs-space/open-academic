<?php

declare(strict_types=1);

namespace App\DTOs\Sdm;

use App\Enums\SumberAngkaKredit;
use App\Enums\UnsurAngkaKredit;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A credit line the personnel office enters by hand.
 *
 * Either a converted semester from before the application existed — SKS from
 * an old BKD, multiplied by the regulation's factor — or an adjustment with a
 * written reason. Amounts are already in hundredths.
 */
final readonly class AngkaKreditManualData
{
    public function __construct(
        public SumberAngkaKredit $sumber,
        public UnsurAngkaKredit $unsur,
        public ?string $semesterKode = null,
        public ?int $sksRatus = null,
        public ?int $nilaiRatus = null,
        public ?string $tanggal = null,
        public ?string $keterangan = null,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        $data = $request->validated();
        $ratus = fn (mixed $v): ?int => $v === null || $v === '' ? null : (int) round(((float) $v) * 100);

        return new self(
            sumber: SumberAngkaKredit::from($data['sumber']),
            unsur: UnsurAngkaKredit::from($data['unsur']),
            semesterKode: $data['semester_kode'] ?? null,
            sksRatus: $ratus($data['sks'] ?? null),
            nilaiRatus: $ratus($data['nilai'] ?? null),
            tanggal: $data['tanggal'] ?? null,
            keterangan: isset($data['keterangan']) ? trim((string) $data['keterangan']) : null,
        );
    }
}
