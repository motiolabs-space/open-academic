<?php

declare(strict_types=1);

namespace App\Http\Requests\Sdm;

use App\DTOs\Sdm\RiwayatKepegawaianData;
use App\Enums\JenisRiwayat;
use App\Models\Sdm\Dosen;
use App\Services\Berkas\BerkasService;
use App\Support\Portal;
use App\Support\Sdm\FormulirRiwayat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * One personnel-history entry, new or edited, from either portal.
 *
 * The rules come from FormulirRiwayat so the form a lecturer sees and the
 * check it passes are one definition. Whose file it is comes from the route —
 * `{dosen}` on the staff side, the signed-in lecturer on theirs — never from
 * the body, so no field names somebody else's record.
 */
class RiwayatKepegawaianRequest extends FormRequest
{
    public function authorize(): bool
    {
        $aktor = Portal::user();

        if ($aktor === null) {
            return false;
        }

        $baris = $this->route('riwayat');

        if ($baris instanceof Model) {
            return Gate::forUser($aktor)->allows('update', $baris);
        }

        $pemilik = $this->pemilik();

        return $pemilik !== null
            && Gate::forUser($aktor)->allows('create', [$this->jenis()->model(), $pemilik, $this->jenis()]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            ...FormulirRiwayat::aturan($this->jenis()),
            'dokumen' => app(BerkasService::class)->aturan('dokumen', wajib: false),
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return collect(array_keys($this->rules()))
            ->mapWithKeys(fn (string $f): array => [$f => mb_strtolower((string) __('kepegawaian.field.'.$f))])
            ->all();
    }

    public function jenis(): JenisRiwayat
    {
        $jenis = $this->route('jenis');

        return $jenis instanceof JenisRiwayat ? $jenis : JenisRiwayat::from((string) $jenis);
    }

    /** The lecturer whose file this is. */
    public function pemilik(): ?Dosen
    {
        $baris = $this->route('riwayat');

        if ($baris instanceof Model) {
            return $baris->dosen;
        }

        $dosen = $this->route('dosen');

        if ($dosen instanceof Dosen) {
            return $dosen;
        }

        $aktor = Portal::user();

        return $aktor instanceof Dosen ? $aktor : null;
    }

    public function toDto(): RiwayatKepegawaianData
    {
        return RiwayatKepegawaianData::fromRequest($this, $this->jenis());
    }
}
