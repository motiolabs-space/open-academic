<?php

declare(strict_types=1);

namespace App\DTOs\Keuangan;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * A dispensation as asked for or granted: until when, and why.
 *
 * Both are required. A dispensation without a date is a waiver of the KRS
 * gate for good, which is not what the finance office means to give; one
 * without a reason cannot be told apart from a favour.
 */
final readonly class DispensasiData
{
    public function __construct(
        public Carbon $sampai,
        public string $alasan,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            sampai: Carbon::parse((string) $request->string('sampai'))->startOfDay(),
            alasan: trim((string) $request->string('alasan')),
        );
    }
}
