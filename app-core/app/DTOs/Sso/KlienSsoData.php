<?php

declare(strict_types=1);

namespace App\DTOs\Sso;

use Illuminate\Http\Request;

/** An application that may sign people in with a campus account. */
final readonly class KlienSsoData
{
    /** @param array<int, string> $redirectUris */
    public function __construct(
        public string $nama,
        public array $redirectUris,
        public bool $pihakPertama = false,
    ) {}

    public static function fromRequest(Request $request): self
    {
        // One URI per line, as the form asks.
        $uris = collect(preg_split('/\R/', (string) $request->string('redirect_uris')) ?: [])
            ->map(fn (string $baris): string => trim($baris))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return new self(
            nama: trim((string) $request->string('nama')),
            redirectUris: $uris,
            pihakPertama: $request->boolean('pihak_pertama'),
        );
    }
}
