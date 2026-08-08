<?php

declare(strict_types=1);

namespace App\Services\Dokumen;

use App\Models\System\Setting;
use App\Services\Branding\BrandingService;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Letterhead, signatory and footer for the documents a campus prints routinely.
 *
 * **Not a template engine, and that is the design.** The roadmap item said
 * "kustomisasi templat dokumen", and user-editable Blade templates mean
 * executing code stored in the database — an RCE path bought with flexibility
 * nobody asked for. What a campus actually changes between installations is
 * the address line, who signs, and the note at the foot of the page. Those are
 * settings. The layout stays in code, under review, in version control.
 *
 * Institution name, short name and logo are **not** repeated here: they belong
 * to BrandingService, and a second copy would eventually disagree with it.
 */
class PengaturanDokumen
{
    public const GRUP = 'dokumen';

    public function __construct(private readonly BrandingService $brand) {}

    /** @return array<string, array<string, mixed>> */
    public function jenis(): array
    {
        return (array) config('dokumen.jenis', []);
    }

    public function label(string $jenis): string
    {
        return (string) ($this->definisi($jenis)['label'] ?? $jenis);
    }

    /**
     * Everything a printed document needs about its own presentation.
     *
     * Returned as one array rather than six getters because every template
     * needs the whole set, and six calls per render is six chances for one of
     * them to be forgotten in a new document.
     *
     * @return array<string, mixed>
     */
    public function untuk(string $jenis): array
    {
        $definisi = $this->definisi($jenis);

        return [
            'institusi' => $this->brand->institutionName(),
            'institusi_singkat' => $this->brand->institutionShortName(),
            'logo' => $this->brand->logoUrl(),

            'alamat' => $this->nilai('kop_alamat', (string) config('dokumen.kop.alamat')),
            'kontak' => $this->nilai('kop_kontak', (string) config('dokumen.kop.kontak')),

            'judul' => $this->nilai($jenis.'_judul', (string) $definisi['label']),
            'catatan_kaki' => $this->nilai($jenis.'_catatan_kaki', (string) $definisi['catatan_kaki']),

            /*
             * Whether a signature block is printed at all.
             *
             * Registers and journals are signed on paper by whoever was in the
             * room, so a printed name there is actively wrong: what is needed is
             * empty space, not an official who was not present.
             */
            'bertanda_tangan' => (bool) $definisi['penandatangan'],

            'penandatangan' => (bool) $definisi['penandatangan'] ? [
                'nama' => $this->nilai($jenis.'_ttd_nama', ''),
                'jabatan' => $this->nilai($jenis.'_ttd_jabatan', (string) $definisi['jabatan_bawaan']),
                'nip' => $this->nilai($jenis.'_ttd_nip', ''),
            ] : null,
        ];
    }

    /**
     * The settings this screen may write, flattened for the form.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function medan(): Collection
    {
        return collect($this->jenis())->map(fn (array $d, string $jenis): array => [
            'jenis' => $jenis,
            'label' => $d['label'],
            'bertanda_tangan' => (bool) $d['penandatangan'],
            'nilai' => $this->untuk($jenis),
        ])->values();
    }

    /** @return array<string, mixed> */
    private function definisi(string $jenis): array
    {
        $definisi = $this->jenis()[$jenis] ?? null;

        if ($definisi === null) {
            /*
             * Loud rather than a blank page.
             *
             * A typo in a document type would otherwise render a header with no
             * title and no footer, which reads as a styling bug and gets chased
             * in the template for an hour.
             */
            throw new InvalidArgumentException(sprintf(
                'Jenis dokumen "%s" tidak dikenal. Yang terdaftar: %s.',
                $jenis,
                implode(', ', array_keys($this->jenis())),
            ));
        }

        return $definisi;
    }

    private function nilai(string $kunci, string $bawaan): string
    {
        $nilai = Setting::get(self::GRUP, $kunci);

        return is_string($nilai) && $nilai !== '' ? $nilai : $bawaan;
    }
}
