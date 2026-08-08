<?php

declare(strict_types=1);

namespace App\Models\Pmb;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmbBerkas extends Model
{
    use HasFactory;

    protected $table = 'pmb_berkas';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
        ];
    }

    public function pendaftar(): BelongsTo
    {
        return $this->belongsTo(PmbPendaftar::class, 'pmb_pendaftar_id');
    }
}
