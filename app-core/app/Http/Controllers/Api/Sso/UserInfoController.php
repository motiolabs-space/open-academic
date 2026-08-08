<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Sso;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Who the bearer of this token is.
 *
 * The one endpoint an SSO consumer cannot do without: an access token proves
 * somebody authorised the app, but not which person, and Open Campus renders a
 * different experience for a student than for a lecturer.
 *
 * Scoped deliberately. `identitas` returns enough to greet someone and to key a
 * local record against them — nothing more. NIK, home address, parents' names
 * and household income are never serialised here, exactly as in the Bridge read
 * API, and for the same reason: a contract that carries them will be used.
 */
class UserInfoController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_if($user === null, 401);

        $role = $this->peran($user);

        abort_if($role === null, 401);

        return response()->json([
            // The OAuth subject. Stable for the life of the record and unique
            // across all three identity tables — safe for a consumer to key on.
            'sub' => $user->getAuthIdentifier(),

            'peran' => $role->value,
            'nama' => $user->nama,
            'email' => $user->email,

            // The national/institutional number for this population. Consumers
            // reconciling against PDDIKTI need it; which field it is depends on
            // who the person is.
            'nomor_induk' => $this->nomorInduk($user, $role),

            ...$this->tambahanPeran($user, $role),
        ]);
    }

    private function peran(Authenticatable $user): ?UserRole
    {
        return match (true) {
            $user instanceof Mahasiswa => UserRole::Mahasiswa,
            $user instanceof Dosen => UserRole::Dosen,
            $user instanceof Staff => UserRole::Staff,
            default => null,
        };
    }

    private function nomorInduk(Authenticatable $user, UserRole $role): ?string
    {
        return match ($role) {
            UserRole::Mahasiswa => $user->nim,
            UserRole::Dosen => $user->nidn,
            UserRole::Staff => $user->nip,
        };
    }

    /** @return array<string, mixed> */
    private function tambahanPeran(Authenticatable $user, UserRole $role): array
    {
        // Programme membership is what a consumer needs to scope a student or
        // lecturer to a faculty; it is not sensitive and it saves every
        // consumer a second call.
        return match ($role) {
            UserRole::Mahasiswa => [
                'prodi' => $user->prodi?->only(['kode', 'nama']),
                'angkatan' => $user->angkatan,
                'status' => $user->status?->value,
            ],
            UserRole::Dosen => [
                'prodi' => $user->prodi?->only(['kode', 'nama']),
            ],
            UserRole::Staff => [
                'unit' => $user->unit,
                'jabatan' => $user->jabatan,
            ],
        };
    }
}
