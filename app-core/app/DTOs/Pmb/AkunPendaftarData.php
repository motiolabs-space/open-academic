<?php

declare(strict_types=1);

namespace App\DTOs\Pmb;

use Illuminate\Http\Request;

/** What it takes to open an applicant account. */
final readonly class AkunPendaftarData
{
    public function __construct(
        public string $nama,
        public string $email,
        public string $password,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return self::fromArray($request->only(['nama', 'email', 'password']));
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            nama: trim((string) $data['nama']),
            // Lower-cased once, here: the unique index is case-sensitive on
            // some collations, and "Budi@" and "budi@" are one inbox.
            email: mb_strtolower(trim((string) $data['email'])),
            password: (string) $data['password'],
        );
    }
}
