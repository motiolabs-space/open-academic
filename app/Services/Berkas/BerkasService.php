<?php

declare(strict_types=1);

namespace App\Services\Berkas;

use App\Exceptions\AturanAkademikException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Storing and retrieving uploaded documents.
 *
 * These files are identity documents and medical certificates belonging to real
 * people — national ID cards, family cards, sick notes. Three rules follow from
 * that, and all three are easy to get wrong in a way nothing complains about:
 *
 *  1. **Never the public disk.** A file under the document root is readable by
 *     anybody who guesses the path, signed in or not. Everything here goes to a
 *     private disk and is served by a controller that checks permission first.
 *
 *  2. **Never the uploader's filename.** It arrives from the client, so it can
 *     contain path traversal (`../../.env`), a null byte, or a second extension
 *     (`ktp.pdf.php`). The stored name is generated; the original is kept only
 *     as a label to show the user.
 *
 *  3. **Type checked against content, not extension.** An extension is part of
 *     a name somebody typed. Laravel's `mimes` rule inspects the file itself.
 */
class BerkasService
{
    /**
     * Stores an upload and returns the path to record on the owning row.
     *
     * The path is deliberately unguessable. Even behind an authorisation check,
     * a sequential path is a standing invitation to try the next number.
     */
    public function simpan(UploadedFile $berkas, string $folder): string
    {
        $this->pastikanDiskPrivat();

        $nama = Str::uuid()->toString().'.'.$this->ekstensiAman($berkas);

        $path = $berkas->storeAs(
            trim($folder, '/'),
            $nama,
            ['disk' => $this->disk()],
        );

        if ($path === false) {
            throw new AturanAkademikException('Berkas gagal disimpan. Periksa izin tulis pada direktori storage.');
        }

        return $path;
    }

    public function hapus(?string $path): void
    {
        if (blank($path)) {
            return;
        }

        Storage::disk($this->disk())->delete($path);
    }

    public function ada(?string $path): bool
    {
        return filled($path) && Storage::disk($this->disk())->exists($path);
    }

    /** Absolute path for streaming a download. */
    public function jalurPenuh(string $path): string
    {
        return Storage::disk($this->disk())->path($path);
    }

    public function disk(): string
    {
        return (string) config('berkas.disk', 'local');
    }

    /**
     * Validation rules for a file field of the given category.
     *
     * Centralised so every upload form inherits the same limits — a form that
     * forgets `mimes` accepts anything the browser will send.
     *
     * @return array<int, string>
     */
    public function aturan(string $kategori = 'dokumen', bool $wajib = true): array
    {
        $jenis = (array) config("berkas.jenis.{$kategori}", config('berkas.jenis.dokumen'));

        return [
            $wajib ? 'required' : 'nullable',
            'file',
            'mimes:'.implode(',', $jenis),
            'max:'.(int) config('berkas.maks_kb', 4096),
        ];
    }

    /**
     * The extension, taken from the guessed type rather than the given name.
     *
     * `getClientOriginalExtension()` returns whatever the uploader typed.
     * `extension()` derives it from the content, so a PHP script renamed to
     * `.pdf` is stored with the extension its bytes actually imply — and the
     * `mimes` rule will have refused it before reaching here anyway.
     */
    private function ekstensiAman(UploadedFile $berkas): string
    {
        $ekstensi = $berkas->extension();

        return preg_match('/^[a-z0-9]{1,8}$/i', $ekstensi) === 1
            ? Str::lower($ekstensi)
            : 'bin';
    }

    /**
     * Refuses to run against a publicly readable disk.
     *
     * A misconfigured `BERKAS_DISK=public` would put every student's ID card
     * under the document root, and nothing else in the stack would notice. This
     * fails loudly at the first upload instead.
     */
    private function pastikanDiskPrivat(): void
    {
        if ($this->disk() === 'public') {
            throw new AturanAkademikException(
                'BERKAS_DISK diatur ke "public". Berkas pendukung berisi dokumen identitas '
                    .'dan tidak boleh berada di bawah document root — siapa pun yang menebak '
                    .'nama berkasnya dapat mengunduhnya tanpa masuk sistem. Gunakan "local" atau '
                    .'bucket S3 privat.',
            );
        }
    }
}
