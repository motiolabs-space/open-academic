<?php

declare(strict_types=1);

namespace App\Models\Kemahasiswaan;

use App\Enums\HasilEvaluasi;
use App\Enums\KeputusanEvaluasi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Sdm\Staff;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One evaluation of one student at one checkpoint.
 *
 * Holds both halves side by side: what the numbers said (`temuan`) and what a
 * person decided (`keputusan`). The figures and the thresholds are copies, not
 * references — a rule changed next year must not rewrite why somebody was
 * warned this year.
 */
class EvaluasiStudi extends Model
{
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;

    protected $table = 'evaluasi_studi';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'temuan' => HasilEvaluasi::class,
            'keputusan' => KeputusanEvaluasi::class,
            'ipk' => 'decimal:2',
            'ips' => 'decimal:2',
            'syarat_ipk' => 'decimal:2',
            'diputuskan_at' => 'datetime',
        ];
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class);
    }

    public function tahunAkademik(): BelongsTo
    {
        return $this->belongsTo(TahunAkademik::class);
    }

    public function diputuskanOleh(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'diputuskan_by_staff_id');
    }

    /** Findings nobody has acted on yet. */
    public function scopeMenunggu(Builder $query): Builder
    {
        return $query->where('keputusan', KeputusanEvaluasi::Menunggu->value);
    }

    /** Findings that need somebody to look — the queue this screen exists for. */
    public function scopePerluDitindak(Builder $query): Builder
    {
        return $query
            ->where('keputusan', KeputusanEvaluasi::Menunggu->value)
            ->whereIn('temuan', [
                HasilEvaluasi::Peringatan->value,
                HasilEvaluasi::TidakMemenuhi->value,
            ]);
    }

    /**
     * Why the finding came out the way it did, in words.
     *
     * Always states the threshold beside the figure. A reader has to be able to
     * disagree with the rule rather than with the student, and "IPK 1,85"
     * alone does not let them.
     */
    public function alasan(): string
    {
        $bagian = [];

        if ($this->syarat_sks !== null) {
            $bagian[] = sprintf('SKS kumulatif %d dari syarat %d', $this->sks_kumulatif, $this->syarat_sks);
        }

        if ($this->syarat_ipk !== null) {
            $bagian[] = sprintf('IPK %.2f dari syarat %.2f', (float) $this->ipk, (float) $this->syarat_ipk);
        }

        if ($bagian === []) {
            $bagian[] = sprintf('IPS %.2f', (float) $this->ips);
        }

        return implode(' · ', $bagian);
    }
}
