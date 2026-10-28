<?php

declare(strict_types=1);

namespace App\Models\Pmb;

use App\Notifications\Pmb\AturUlangSandiPendaftar;
use App\Notifications\Pmb\VerifikasiSurelPendaftar;
use App\Traits\AuthenticatesWithUuid;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use App\Traits\PutarTokenIngat;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * A prospective student's sign-in, behind the `pendaftar` guard.
 *
 * Not a campus identity. It holds no role and no permission, is unknown to
 * the SSO provider, and no guard but its own resolves it — so nothing a
 * prospective student can do here reaches the student portal. The student
 * account is created separately, by the registrar, when the NIM is issued.
 *
 * @property string $nama
 * @property string $email
 */
class PmbAkun extends Authenticatable implements MustVerifyEmail
{
    use AuthenticatesWithUuid;
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;
    use Notifiable;
    use PutarTokenIngat;

    protected $table = 'pmb_akun';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected $hidden = ['password', 'remember_token'];

    /** @var array<int, string> */
    protected array $logExcept = ['last_login_at'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function pendaftaran(): HasMany
    {
        return $this->hasMany(PmbPendaftar::class, 'pmb_akun_id');
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifikasiSurelPendaftar);
    }

    /** @param string $token */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new AturUlangSandiPendaftar($token));
    }
}
