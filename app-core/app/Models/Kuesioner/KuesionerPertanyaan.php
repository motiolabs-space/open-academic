<?php

declare(strict_types=1);

namespace App\Models\Kuesioner;

use App\Enums\TipePertanyaan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KuesionerPertanyaan extends Model
{
    use HasFactory;

    protected $table = 'kuesioner_pertanyaan';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $attributes = ['wajib' => true];

    protected function casts(): array
    {
        return [
            'tipe' => TipePertanyaan::class,
            'opsi' => 'array',
            'wajib' => 'boolean',
        ];
    }

    public function kuesioner(): BelongsTo
    {
        return $this->belongsTo(Kuesioner::class);
    }
}
