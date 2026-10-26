<?php

declare(strict_types=1);

namespace App\Models\Akademik;

use App\Enums\JenisUjianKelas;
use App\Enums\StatusJadwalUjian;
use App\Support\Format;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * One mid-term or final examination session of a class.
 *
 * Dated, unlike JadwalKuliah: a weekly slot recurs, an exam happens once. The
 * accessors below (mulai, selesai, scopeDalamRentang) are what anything reading
 * exams as events on a calendar should use, rather than re-assembling a
 * timestamp from the date and time columns itself.
 *
 * @property JenisUjianKelas $jenis
 * @property StatusJadwalUjian $status
 * @property Carbon $tanggal
 * @property Collection<int, JadwalUjianRuang> $ruang
 * @property Collection<int, JadwalUjianPengawas> $pengawas
 */
class JadwalUjian extends Model
{
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'jadwal_ujian';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'jenis' => JenisUjianKelas::class,
            'status' => StatusJadwalUjian::class,
            'tanggal' => 'date',
            'diterbitkan_at' => 'datetime',
        ];
    }

    public function kelasKuliah(): BelongsTo
    {
        return $this->belongsTo(KelasKuliah::class);
    }

    public function tahunAkademik(): BelongsTo
    {
        return $this->belongsTo(TahunAkademik::class);
    }

    public function ruang(): HasMany
    {
        return $this->hasMany(JadwalUjianRuang::class)->orderBy('urutan');
    }

    public function pengawas(): HasMany
    {
        return $this->hasMany(JadwalUjianPengawas::class);
    }

    public function scopeTerbit(Builder $query): Builder
    {
        return $query->where('status', StatusJadwalUjian::Terbit->value);
    }

    public function scopeTerm(Builder $query, int $tahunAkademikId): Builder
    {
        return $query->where('tahun_akademik_id', $tahunAkademikId);
    }

    public function scopeJenis(Builder $query, JenisUjianKelas $jenis): Builder
    {
        return $query->where('jenis', $jenis->value);
    }

    /** Sessions held on any day between two dates, inclusive. */
    public function scopeDalamRentang(Builder $query, Carbon|string $dari, Carbon|string $sampai): Builder
    {
        return $query
            ->whereDate('tanggal', '>=', Carbon::parse($dari)->toDateString())
            ->whereDate('tanggal', '<=', Carbon::parse($sampai)->toDateString());
    }

    public function sudahTerbit(): bool
    {
        return $this->status === StatusJadwalUjian::Terbit;
    }

    public function mulai(): Carbon
    {
        return $this->tanggal->copy()->setTimeFromTimeString(self::jam($this->jam_mulai));
    }

    public function selesai(): Carbon
    {
        return $this->tanggal->copy()->setTimeFromTimeString(self::jam($this->jam_selesai));
    }

    /** "Senin, 12 Oktober 2026 · 08.00 – 10.00 WIB" */
    public function rentangWaktu(): string
    {
        return Format::tanggalHari($this->tanggal).' · '.Format::rentangJam($this->jam_mulai, $this->jam_selesai);
    }

    /** Seats across every room the session is split over. */
    public function totalKursi(): int
    {
        return (int) $this->ruang->sum('kursi');
    }

    /** Whether the two sessions are on the same date and overlap in time. */
    public function bentrokDengan(self $lain): bool
    {
        return $this->tanggal->isSameDay($lain->tanggal)
            && self::jam($this->jam_mulai) < self::jam($lain->jam_selesai)
            && self::jam($lain->jam_mulai) < self::jam($this->jam_selesai);
    }

    /**
     * "08:00", whatever the column handed back.
     *
     * MySQL returns a TIME as "08:00:00", SQLite returns the string it was
     * given. Compared as strings, "08:00" sorts before "08:00:00", so a check
     * that mixes the two finds overlaps that are not there — or misses ones
     * that are.
     */
    public static function jam(?string $waktu): string
    {
        return substr((string) $waktu, 0, 5);
    }
}
