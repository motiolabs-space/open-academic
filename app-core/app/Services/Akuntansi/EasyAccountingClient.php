<?php

declare(strict_types=1);

namespace App\Services\Akuntansi;

use App\Services\Akuntansi\Contracts\AkuntansiClientInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Easy Accounting (easyERP) API v1.
 *
 * The contract is documented and testable, which is why this exists at all
 * while the SISTER client does not: there is nothing here written against a
 * guess.
 *
 * Its own idempotency guarantee does the heavy lifting. easyERP returns the
 * first response for a repeated `Idempotency-Key` per tenant, so the dangerous
 * case — a response lost after the far side committed — resolves itself on the
 * next attempt instead of billing a student twice.
 *
 * @see https://<domain>/api/docs — Swagger UI on the easyERP installation
 */
class EasyAccountingClient implements AkuntansiClientInterface
{
    public function kirim(string $endpoint, array $payload, string $kunciIdempotensi): HasilKirim
    {
        try {
            $respons = $this->permintaan()
                ->withHeaders(['Idempotency-Key' => $kunciIdempotensi])
                ->post($this->url($endpoint), $payload);
        } catch (ConnectionException $e) {
            // No HTTP code: nobody answered. Worth retrying.
            return HasilKirim::gagal('Tidak dapat menghubungi Easy Accounting: '.$e->getMessage());
        } catch (Throwable $e) {
            return HasilKirim::gagal('Kesalahan tak terduga: '.$e->getMessage());
        }

        $isi = $respons->json() ?? [];

        if ($respons->failed() || ($isi['status'] ?? null) === 'error') {
            return HasilKirim::gagal(
                $this->pesanGalat($isi, $respons->status()),
                $respons->status(),
            );
        }

        $data = $isi['data'] ?? [];

        return HasilKirim::sukses(
            id: isset($data['id']) ? (string) $data['id'] : null,
            nomor: $data['transaction_number'] ?? null,
            data: is_array($data) ? $data : [],
        );
    }

    public function tersedia(): bool
    {
        try {
            // A read endpoint, deliberately: an availability probe must not be
            // able to write anything.
            return $this->permintaan()->get($this->url('journals/coa'))->successful();
        } catch (Throwable) {
            return false;
        }
    }

    private function permintaan()
    {
        return Http::asJson()
            ->acceptJson()
            ->timeout((int) config('akuntansi.easyerp.timeout'))
            ->withToken((string) config('akuntansi.easyerp.api_key'));
    }

    private function url(string $endpoint): string
    {
        return rtrim((string) config('akuntansi.easyerp.base_url'), '/').'/'.ltrim($endpoint, '/');
    }

    /**
     * Turns a refusal into a sentence somebody can act on.
     *
     * The field-level `errors` bag is folded in, because "422 Unprocessable"
     * on the monitor screen tells whoever is looking at it nothing, and the
     * actual cause is almost always one named field.
     *
     * @param array<string, mixed> $isi
     */
    private function pesanGalat(array $isi, int $kode): string
    {
        $pesan = $isi['message'] ?? 'Ditolak Easy Accounting (HTTP '.$kode.').';

        $rincian = collect($isi['errors'] ?? [])
            ->map(fn ($teks, $kolom): string => $kolom.': '.(is_array($teks) ? implode(', ', $teks) : $teks))
            ->implode('; ');

        return $rincian === '' ? $pesan : $pesan.' — '.$rincian;
    }
}
