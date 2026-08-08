<?php

declare(strict_types=1);

namespace App\Services\Akuntansi;

use App\Enums\JenisDokumenAkuntansi;
use App\Enums\JenisEntitasAkuntansi;
use App\Enums\StatusDokumenAkuntansi;
use App\Models\Akuntansi\DokumenAkuntansi;
use App\Models\Akuntansi\PemetaanAkuntansi;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Services\Akuntansi\Contracts\AkuntansiClientInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Draining the outbox.
 *
 * Runs on a schedule and from an artisan command, never inside a request that
 * created a document.
 *
 * The interesting part is dependency resolution. An invoice payload leaves
 * `PenjurnalanService` naming a student by uuid, because at the moment it was
 * queued nobody knew — or should have had to wait to find out — what id that
 * student holds on the accounting side. Resolution happens here, at send time:
 * the contact is created if it does not exist, its id is cached, and only then
 * does the invoice go.
 *
 * Doing it the other way round — queueing the contact as its own document and
 * hoping insertion order holds — breaks the first time a document in front of
 * it fails and blocks the queue.
 */
class PengirimAkuntansi
{
    public function __construct(private readonly AkuntansiClientInterface $klien) {}

    /**
     * Sends up to one batch.
     *
     * @return array{terkirim: int, gagal: int, ditunda: int}
     */
    public function jalankan(?int $batas = null): array
    {
        $batas ??= (int) config('akuntansi.pengiriman.ukuran_batch');

        $hasil = ['terkirim' => 0, 'gagal' => 0, 'ditunda' => 0];

        $antrean = DokumenAkuntansi::query()->siapKirim()->limit($batas)->get();

        foreach ($antrean as $dokumen) {
            $status = $this->kirimSatu($dokumen);
            $hasil[$status]++;
        }

        return $hasil;
    }

    /**
     * @return 'terkirim'|'gagal'|'ditunda'
     */
    public function kirimSatu(DokumenAkuntansi $dokumen): string
    {
        try {
            $payload = $this->siapkanPayload($dokumen);
        } catch (DependensiGagal $e) {
            /*
             * The contact push was refused, and why decides what happens next.
             *
             * A dropped connection is transient and the invoice behind it must
             * wait, not die — treating every dependency failure as terminal
             * turns a five-second network blip into a queue of documents
             * somebody has to notice and requeue by hand.
             */
            $pesan = 'Dependensi kontak: '.$e->getMessage();

            return $e->hasil->layakDiulang()
                ? $this->tundaAtauMenyerah($dokumen, $pesan)
                : $this->tandaiGagal($dokumen, $pesan);
        } catch (Throwable $e) {
            // Anything else here is a bug in our own mapping, not the far side.
            return $this->tandaiGagal($dokumen, 'Gagal menyiapkan dependensi: '.$e->getMessage());
        }

        $hasil = $this->klien->kirim(
            $dokumen->jenis->endpoint(),
            $payload,
            $dokumen->kunci_idempotensi,
        );

        if ($hasil->berhasil) {
            $dokumen->update([
                'status' => StatusDokumenAkuntansi::Terkirim,
                'easyerp_id' => $hasil->easyerpId,
                'easyerp_nomor' => $hasil->nomor,
                'terkirim_at' => now(),
                'galat' => null,
            ]);

            return 'terkirim';
        }

        if (!$hasil->layakDiulang()) {
            return $this->tandaiGagal($dokumen, $hasil->galat ?? 'Ditolak tanpa keterangan.');
        }

        return $this->tundaAtauMenyerah($dokumen, $hasil->galat ?? 'Tidak dapat dihubungi.');
    }

    /** Puts a failed document back in the queue after somebody fixed the cause. */
    public function ulangi(DokumenAkuntansi $dokumen): void
    {
        $dokumen->update([
            'status' => StatusDokumenAkuntansi::Menunggu,
            'percobaan' => 0,
            'coba_lagi_setelah' => null,
            'galat' => null,
        ]);
    }

    /**
     * Fills in whatever the payload could not know when it was queued.
     *
     * @return array<string, mixed>
     */
    private function siapkanPayload(DokumenAkuntansi $dokumen): array
    {
        $payload = $dokumen->payload;

        if ($dokumen->jenis !== JenisDokumenAkuntansi::Invoice) {
            return $payload;
        }

        $uuid = $payload['kontak_mahasiswa'] ?? null;
        unset($payload['kontak_mahasiswa']);

        if ($uuid === null) {
            return $payload;
        }

        return ['contact_id' => (int) $this->kontakUntuk((string) $uuid)] + $payload;
    }

    /**
     * The student's contact id on the accounting side, creating it if needed.
     *
     * Cached in `akuntansi_pemetaan`, so a student who enrols for eight
     * semesters becomes one customer rather than eight. Without the cache the
     * only way to find them again would be to search by name — and two students
     * called Muhammad Rizki would eventually be billed as one person.
     */
    private function kontakUntuk(string $uuid): string
    {
        $tersimpan = PemetaanAkuntansi::cari(JenisEntitasAkuntansi::Mahasiswa, $uuid);

        if ($tersimpan !== null) {
            return $tersimpan;
        }

        $mahasiswa = Mahasiswa::where('uuid', $uuid)->firstOrFail();

        $hasil = $this->klien->kirim('contacts', [
            'name' => $mahasiswa->nama,
            'type' => 'customer',
            'email' => $mahasiswa->email,
            'phone' => $mahasiswa->telepon,

            /*
             * The NIM, not the national ID.
             *
             * The accounting side needs something to recognise a student by on
             * a statement; it does not need their NIK, home address, or
             * parents. Same stance as the Bridge payloads — the moment personal
             * data is in a shape that gets emailed as a spreadsheet, it leaks
             * through the careless channel.
             */
            'address' => 'NIM '.$mahasiswa->nim,
        ], 'oa-kontak-'.$uuid);

        if (!$hasil->berhasil || $hasil->easyerpId === null) {
            throw new DependensiGagal($hasil);
        }

        $this->ingatPemetaan(JenisEntitasAkuntansi::Mahasiswa, $uuid, $hasil->easyerpId, $mahasiswa->nama);

        return $hasil->easyerpId;
    }

    private function ingatPemetaan(JenisEntitasAkuntansi $jenis, string $kunci, string $id, ?string $label): void
    {
        try {
            PemetaanAkuntansi::create([
                'jenis' => $jenis,
                'lokal_kunci' => $kunci,
                'easyerp_id' => $id,
                'label' => $label,
                'dipetakan_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Two workers resolved the same student at once. Both got the same
            // remote id back — easyERP's idempotency key saw to that — so
            // whichever lost the insert has nothing to correct.
        }
    }

    private function tandaiGagal(DokumenAkuntansi $dokumen, string $galat): string
    {
        $dokumen->update([
            'status' => StatusDokumenAkuntansi::Gagal,
            'percobaan' => $dokumen->percobaan + 1,
            'galat' => $galat,
        ]);

        Log::warning('Dokumen akuntansi ditolak.', [
            'dokumen' => $dokumen->uuid,
            'jenis' => $dokumen->jenis->value,
            'galat' => $galat,
        ]);

        return 'gagal';
    }

    /**
     * Backs off, or gives up after the configured number of tries.
     *
     * Giving up is deliberate. A document retried forever hides its cause — an
     * account code that does not exist on the other side, most often — behind a
     * rising counter nobody reads. Failing puts it on the monitor screen where
     * somebody can fix the mapping and requeue it.
     */
    private function tundaAtauMenyerah(DokumenAkuntansi $dokumen, string $galat): string
    {
        $percobaan = $dokumen->percobaan + 1;
        $maks = (int) config('akuntansi.pengiriman.maks_percobaan');

        if ($percobaan >= $maks) {
            return $this->tandaiGagal(
                $dokumen,
                sprintf('Menyerah setelah %d percobaan. Terakhir: %s', $percobaan, $galat),
            );
        }

        $dokumen->update([
            'percobaan' => $percobaan,
            'galat' => $galat,

            // 1, 2, 4, 8 minutes — long enough for a restart on the far side,
            // short enough that a day's billing still lands the same day.
            'coba_lagi_setelah' => now()->addMinutes(
                (int) config('akuntansi.pengiriman.backoff_menit') * (2 ** ($percobaan - 1)),
            ),
        ]);

        return 'ditunda';
    }
}
