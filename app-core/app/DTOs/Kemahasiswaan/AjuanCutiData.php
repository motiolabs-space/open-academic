<?php

declare(strict_types=1);

namespace App\DTOs\Kemahasiswaan;

use App\Enums\JenisCuti;
use App\Http\Requests\Kemahasiswaan\AjukanCutiRequest;
use Illuminate\Http\UploadedFile;

/** A leave application as the student filed it from the portal. */
final readonly class AjuanCutiData
{
    public function __construct(
        public int $tahunAkademikId,
        public JenisCuti $jenis,
        public string $alasan,
        public ?UploadedFile $dokumen = null,
    ) {}

    public static function fromRequest(AjukanCutiRequest $request): self
    {
        return new self(
            tahunAkademikId: $request->integer('tahun_akademik_id'),
            jenis: JenisCuti::from((string) $request->string('jenis')),
            alasan: trim((string) $request->string('alasan')),
            dokumen: $request->file('dokumen'),
        );
    }
}
