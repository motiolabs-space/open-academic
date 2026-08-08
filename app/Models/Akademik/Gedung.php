<?php

declare(strict_types=1);

namespace App\Models\Akademik;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Gedung extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'gedung';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    public function ruang(): HasMany
    {
        return $this->hasMany(Ruang::class);
    }
}
