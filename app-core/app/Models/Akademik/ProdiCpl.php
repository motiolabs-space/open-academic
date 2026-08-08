<?php

declare(strict_types=1);

namespace App\Models\Akademik;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One programme learning outcome (capaian pembelajaran lulusan).
 *
 * Stated on the diploma supplement as what a graduate of this programme is able
 * to do. It belongs to the programme, not to the student — which is why it is
 * stored once here instead of being retyped into every supplement, where the
 * English half would quietly stop being filled in.
 */
class ProdiCpl extends Model
{
    use HasFactory;

    protected $table = 'prodi_cpl';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    /** @var array<int, string> */
    public const KATEGORI = [
        'sikap' => 'Sikap',
        'pengetahuan' => 'Pengetahuan',
        'keterampilan_umum' => 'Keterampilan Umum',
        'keterampilan_khusus' => 'Keterampilan Khusus',
    ];

    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class);
    }

    public function labelKategori(): string
    {
        return self::KATEGORI[$this->kategori] ?? $this->kategori;
    }
}
