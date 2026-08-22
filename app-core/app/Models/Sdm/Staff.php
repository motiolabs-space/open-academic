<?php

declare(strict_types=1);

namespace App\Models\Sdm;

use App\Traits\AuthenticatesWithUuid;
use App\Traits\DapatDicari;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Database\Factories\StaffFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * Administrative staff: BAAK, finance, the PDDIKTI operator, leadership.
 * Authenticates on the "staff" guard.
 */
class Staff extends Authenticatable implements OAuthenticatable
{
    use AuthenticatesWithUuid;
    use DapatDicari;

    /** @use HasFactory<StaffFactory> */
    use HasApiTokens;

    use HasFactory;

    // A staff account is a permission grant. Changing whose account can push to
    // PDDIKTI or issue Bridge tokens is exactly what an auditor asks about
    // months later, so these rows carry a trail like academic records do.
    use HasLogAktivitas;
    use HasRoles;
    use HasUuid;
    use Notifiable;
    use SoftDeletes;

    protected $table = 'staff';

    /** Never let a password hash or a session token reach the activity log. */
    protected array $logExcept = ['password', 'remember_token', 'last_login_at'];

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    /*
     * The 2FA secret is a password equivalent — anyone holding it can mint
     * valid codes indefinitely — so it is hidden for the same reason the
     * password hash is: one careless toJson() should not publish it.
     */
    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery'];

    /** Spatie Permission resolves roles against this guard. */
    protected string $guard_name = 'staff';

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',

            // Encrypted at rest: a database dump would otherwise hand over the
            // second factor along with the first.
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /** Whether this account has a confirmed second factor. */
    public function duaFaktorAktif(): bool
    {
        return $this->two_factor_confirmed_at !== null && filled($this->two_factor_secret);
    }

    public function namaLengkap(): string
    {
        return $this->nama;
    }

    /**
     * The work unit this person belongs to.
     *
     * The older `unit` text column is still on the row and still holds whatever
     * was typed there before the org chart existed. It is kept as evidence of
     * how somebody was filed, not read: everything downstream goes through this
     * relation.
     */
    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class, 'unit_kerja_id');
    }

    protected static function newFactory(): StaffFactory
    {
        return StaffFactory::new();
    }
}
