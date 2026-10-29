<?php

declare(strict_types=1);

namespace App\DTOs\Kemahasiswaan;

use App\Http\Requests\Kemahasiswaan\KontakLulusanRequest;

/** A graduate's contact details as staff confirmed them with the graduate. */
final readonly class KontakLulusanData
{
    public function __construct(
        public ?string $emailPribadi,
        public ?string $telepon,
        public bool $tidakDibagikan = false,
    ) {}

    public static function fromRequest(KontakLulusanRequest $request): self
    {
        return new self(
            emailPribadi: $request->filled('email_pribadi') ? strtolower(trim((string) $request->string('email_pribadi'))) : null,
            telepon: $request->filled('telepon') ? trim((string) $request->string('telepon')) : null,
            tidakDibagikan: $request->boolean('kontak_tidak_dibagikan'),
        );
    }
}
