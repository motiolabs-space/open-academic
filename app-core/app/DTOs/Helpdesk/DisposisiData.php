<?php

declare(strict_types=1);

namespace App\DTOs\Helpdesk;

use App\Enums\PrioritasTiket;
use Illuminate\Http\Request;

/**
 * Routing a ticket: to which unit, optionally to whom, at what priority.
 *
 * At least one of unit or officer must be named — a disposition that names
 * nobody is not a disposition. The FormRequest enforces that; the service
 * repeats the check so a command or a test cannot slip past it.
 */
final readonly class DisposisiData
{
    public function __construct(
        public ?int $unitKerjaId = null,
        public ?int $petugasStaffId = null,
        public ?PrioritasTiket $prioritas = null,
        public ?string $catatan = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            unitKerjaId: $request->filled('unit_kerja_id') ? (int) $request->input('unit_kerja_id') : null,
            petugasStaffId: $request->filled('petugas_staff_id') ? (int) $request->input('petugas_staff_id') : null,
            prioritas: $request->filled('prioritas')
                ? PrioritasTiket::tryFrom((string) $request->string('prioritas'))
                : null,
            catatan: $request->filled('catatan') ? trim((string) $request->string('catatan')) : null,
        );
    }

    public function menamaiSeseorang(): bool
    {
        return $this->unitKerjaId !== null || $this->petugasStaffId !== null;
    }
}
