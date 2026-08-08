<?php

declare(strict_types=1);

namespace App\Services\Surat;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Throwable;

/**
 * The QR square printed on a letter.
 *
 * It encodes the verification URL and nothing else — no name, no number, no
 * payload of its own. A QR that carried the facts would be a second copy of the
 * document that nobody can revoke, and forging one would be a text-editing
 * exercise. Pointing at the campus means the campus answers.
 *
 * PNG rather than SVG because the destination is a DomPDF page, whose SVG
 * support is partial; a square of black rectangles is not worth finding the
 * edges of.
 */
class PembuatQr
{
    /**
     * A base64 data URI, ready to drop into an <img src>.
     *
     * Returns null rather than throwing when generation fails. A letter without
     * its QR is still a valid letter with a printed verification URL on it; a
     * letter that will not render at all is a person sent back to the counter.
     */
    public function dataUri(string $isi, int $ukuran = 150): ?string
    {
        try {
            return (new Builder(
                writer: new PngWriter,
                data: $isi,
                size: $ukuran,
                margin: 6,
            ))->build()->getDataUri();
        } catch (Throwable) {
            return null;
        }
    }
}
