<?php

declare(strict_types=1);

namespace App\DTOs\Feeder;

/**
 * What the dictionary says about one field name the campus sets.
 *
 * `status`: sesuai (the configured name is in the dictionary), ubah (it is
 * not, and the dictionary has a likely one), isi (not set, the dictionary
 * has one), kosongkan (set, but the dictionary has nothing like it),
 * tidak_ditemukan (neither), tidak_terbaca (the act's dictionary could not
 * be read). Only ubah and isi carry a suggestion.
 */
final readonly class SaranMedanFeeder
{
    public function __construct(
        public string $env,
        public string $act,
        public ?string $sekarang,
        public ?string $disarankan,
        public string $status,
    ) {}

    public function perluTindakan(): bool
    {
        return in_array($this->status, ['ubah', 'isi', 'kosongkan'], true);
    }

    /** The line to put in .env, when there is one to put. */
    public function barisEnv(): ?string
    {
        return match ($this->status) {
            'ubah', 'isi' => $this->env.'='.$this->disarankan,
            'kosongkan' => $this->env.'=',
            default => null,
        };
    }
}
