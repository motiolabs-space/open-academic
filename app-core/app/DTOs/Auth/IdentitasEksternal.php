<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

/**
 * What an identity provider asserts about the person who just signed in there.
 *
 * Only the claims the application acts on. The raw token never leaves the
 * provider class: nothing downstream needs it, and a DTO that carried it would
 * end up in a log line sooner or later.
 */
final readonly class IdentitasEksternal
{
    public function __construct(
        public string $issuer,
        public string $subject,
        public ?string $email,
        public bool $emailVerified,
        public ?string $name,
    ) {}

    /**
     * @param array<string, mixed> $claims already validated ID-token claims
     */
    public static function fromClaims(array $claims): self
    {
        $email = isset($claims['email']) && is_string($claims['email'])
            ? mb_strtolower(trim($claims['email']))
            : null;

        return new self(
            issuer: (string) $claims['iss'],
            subject: (string) $claims['sub'],
            email: $email !== '' ? $email : null,
            // Absent counts as unverified. Entra ID omits the claim; a campus
            // on Entra relaxes idp.require_verified_email knowingly.
            emailVerified: ($claims['email_verified'] ?? false) === true,
            name: isset($claims['name']) && is_string($claims['name']) ? $claims['name'] : null,
        );
    }
}
