<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\Sdm\BahasaDosen;
use App\Models\Sdm\DiklatDosen;
use App\Models\Sdm\InpassingDosen;
use App\Models\Sdm\JabatanStrukturalDosen;
use App\Models\Sdm\KeluargaDosen;
use App\Models\Sdm\KontrakDosen;
use App\Models\Sdm\MutasiDosen;
use App\Models\Sdm\OrganisasiDosen;
use App\Models\Sdm\PangkatDosen;
use App\Models\Sdm\PengalamanKerjaDosen;
use App\Models\Sdm\PenghargaanSanksiDosen;
use App\Models\Sdm\PensiunDosen;
use App\Models\Sdm\TugasBelajarDosen;
use App\Models\Sdm\TugasDinasDosen;
use Illuminate\Database\Eloquent\Model;

/**
 * The fourteen reviewed personnel histories, as one vocabulary.
 *
 * Each is still its own table — see docs/KEPEGAWAIAN.md for why. This enum is
 * what lets one review queue, one pair of screens, and one policy speak about
 * all of them without fourteen copies of the same controller.
 *
 * Case order is screen order: rank and posts first, the things SISTER's
 * "Profil Dosen" asks about next, and the employer's own records last.
 */
enum JenisRiwayat: string
{
    case Pangkat = 'pangkat';
    case Inpassing = 'inpassing';
    case JabatanStruktural = 'jabatan_struktural';
    case TugasBelajar = 'tugas_belajar';
    case Diklat = 'diklat';
    case PengalamanKerja = 'pengalaman_kerja';
    case TugasDinas = 'tugas_dinas';
    case PenghargaanSanksi = 'penghargaan_sanksi';
    case Organisasi = 'organisasi';
    case Bahasa = 'bahasa';
    case Keluarga = 'keluarga';
    case Mutasi = 'mutasi';
    case Kontrak = 'kontrak';
    case Pensiun = 'pensiun';

    public function label(): string
    {
        return __('kepegawaian.jenis.'.$this->value);
    }

    /** @return class-string<Model> */
    public function model(): string
    {
        return match ($this) {
            self::Pangkat => PangkatDosen::class,
            self::Inpassing => InpassingDosen::class,
            self::JabatanStruktural => JabatanStrukturalDosen::class,
            self::TugasBelajar => TugasBelajarDosen::class,
            self::Diklat => DiklatDosen::class,
            self::PengalamanKerja => PengalamanKerjaDosen::class,
            self::TugasDinas => TugasDinasDosen::class,
            self::PenghargaanSanksi => PenghargaanSanksiDosen::class,
            self::Organisasi => OrganisasiDosen::class,
            self::Bahasa => BahasaDosen::class,
            self::Keluarga => KeluargaDosen::class,
            self::Mutasi => MutasiDosen::class,
            self::Kontrak => KontrakDosen::class,
            self::Pensiun => PensiunDosen::class,
        };
    }

    /** The relation on Dosen that lists these rows. */
    public function relasi(): string
    {
        return match ($this) {
            self::JabatanStruktural => 'jabatanStruktural',
            self::TugasBelajar => 'tugasBelajar',
            self::PengalamanKerja => 'pengalamanKerja',
            self::TugasDinas => 'tugasDinas',
            self::PenghargaanSanksi => 'penghargaanSanksi',
            default => $this->value,
        };
    }

    /**
     * Whether the lecturer files this one, or only the employer does.
     *
     * Three are the employer's acts rather than the lecturer's history. A
     * posting moves the `unit_kerja_id` pointer, so a back-dated entry
     * submitted from the portal would relocate somebody on approval; a
     * contract is the employer's own document; and a retirement closes the
     * account. Lecturers read all three on their screen and file none.
     */
    public function diisiDosen(): bool
    {
        return !in_array($this, [self::Mutasi, self::Kontrak, self::Pensiun], true);
    }

    /** The column a list of these is ordered by, newest first. */
    public function kolomUrut(): string
    {
        return match ($this) {
            self::Pangkat, self::Inpassing, self::JabatanStruktural, self::Mutasi, self::Pensiun => 'tmt',
            self::PenghargaanSanksi => 'tanggal',
            self::Organisasi => 'tahun_mulai',
            self::Bahasa => 'bahasa',
            self::Keluarga => 'nama',
            default => 'tanggal_mulai',
        };
    }

    public static function dariModel(Model $baris): self
    {
        foreach (self::cases() as $jenis) {
            if ($baris instanceof ($jenis->model())) {
                return $jenis;
            }
        }

        throw new \InvalidArgumentException($baris::class.' bukan riwayat kepegawaian.');
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $carry, self $case): array => $carry + [$case->value => $case->label()],
            [],
        );
    }
}
