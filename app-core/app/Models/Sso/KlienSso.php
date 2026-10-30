<?php

declare(strict_types=1);

namespace App\Models\Sso;

use App\Models\Sdm\Staff;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What the campus decided about one SSO client. The client itself — name,
 * redirect URIs, hashed secret — stays in Passport's `oauth_clients`.
 */
class KlienSso extends Model
{
    use HasLogAktivitas;
    use HasUuid;

    protected $table = 'sso_klien';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'is_first_party' => 'boolean',
            'rahasia_dirotasi_at' => 'datetime',
            'dicabut_at' => 'datetime',
        ];
    }

    public function klien(): BelongsTo
    {
        return $this->belongsTo(KlienOAuth::class, 'oauth_client_id');
    }

    public function pendaftar(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'didaftarkan_oleh_staff_id');
    }

    protected function activityLabel(): ?string
    {
        return $this->klien?->name;
    }
}
