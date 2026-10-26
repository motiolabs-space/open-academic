<?php

declare(strict_types=1);

namespace App\DTOs\Sdm;

use App\Enums\JenisRiwayat;
use App\Support\Sdm\FormulirRiwayat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * One personnel-history entry as filled in, whichever portal it came from.
 *
 * `atribut` holds columns, not form fields — the hundredths conversion and the
 * rank title derived from the grade have already happened.
 */
final readonly class RiwayatKepegawaianData
{
    /** @param array<string, mixed> $atribut */
    public function __construct(
        public JenisRiwayat $jenis,
        public array $atribut,
        public ?UploadedFile $dokumen = null,
    ) {}

    public static function fromRequest(FormRequest $request, JenisRiwayat $jenis): self
    {
        $dokumen = $request->file('dokumen');

        return new self(
            jenis: $jenis,
            atribut: FormulirRiwayat::atribut($jenis, $request->validated()),
            dokumen: $dokumen instanceof UploadedFile ? $dokumen : null,
        );
    }

    /** @param array<string, mixed> $isian form fields, as FormulirRiwayat names them */
    public static function fromArray(JenisRiwayat $jenis, array $isian, ?UploadedFile $dokumen = null): self
    {
        return new self($jenis, FormulirRiwayat::atribut($jenis, $isian), $dokumen);
    }
}
