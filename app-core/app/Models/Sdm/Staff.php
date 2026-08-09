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

    protected $hidden = ['password', 'remember_token'];

    /** Spatie Permission resolves roles against this guard. */
    protected string $guard_name = 'staff';

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
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
