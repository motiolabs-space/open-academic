<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * A single-sheet .xlsx, written by hand.
 *
 * Enough of SpreadsheetML for one table — inline strings, plain numbers, a
 * bold header — and nothing else. A spreadsheet library would bring formulas,
 * charts, readers for five formats, and a dependency tree larger than this
 * module, to write twelve columns. ext-zip is already required (CI installs it,
 * and so does every PHP distribution that runs Laravel).
 *
 * Two cell kinds matter, and the distinction is the reason this exists rather
 * than a CSV: a NUPTK or a `TTTT-BB-HH` date written as a number is "helped" by
 * Excel into scientific notation or a serial date, and the upload that checks
 * them against PDDIKTI then refuses the row. Strings are therefore written as
 * text cells with the text number format, and only real numbers as numbers.
 */
final class LembarKerjaXlsx
{
    /** @var array<int, array<int, string|int|float|null>> */
    private array $baris = [];

    /** @param array<int, string> $judul */
    public function __construct(
        private readonly string $namaLembar,
        private readonly array $judul,
    ) {}

    /** @param array<int, string|int|float|null> $sel */
    public function tambah(array $sel): self
    {
        $this->baris[] = array_values($sel);

        return $this;
    }

    /** Writes the workbook to a path and returns it. */
    public function simpan(string $path): string
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Lembar kerja tidak dapat ditulis ke {$path}.");
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->relasiAkar());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->relasiWorkbook());
        $zip->addFromString('xl/styles.xml', $this->gaya());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->lembar());
        $zip->close();

        return $path;
    }

    /* ------------------------------------------------------------------ */

    private const GAYA_JUDUL = 1;

    private const GAYA_ANGKA = 2;

    private const GAYA_TEKS = 3;

    private function lembar(): string
    {
        $xml = '';

        foreach ([$this->judul, ...$this->baris] as $i => $baris) {
            $nomor = $i + 1;
            $xml .= '<row r="'.$nomor.'">';

            foreach ($baris as $k => $nilai) {
                $xml .= $this->sel(self::kolom($k).$nomor, $nilai, $i === 0);
            }

            $xml .= '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetData>'.$xml.'</sheetData></worksheet>';
    }

    private function sel(string $ref, string|int|float|null $nilai, bool $judul): string
    {
        if ($nilai === null || $nilai === '') {
            return '<c r="'.$ref.'"/>';
        }

        if (!$judul && (is_int($nilai) || is_float($nilai))) {
            $angka = is_int($nilai) ? (string) $nilai : number_format($nilai, 2, '.', '');

            return '<c r="'.$ref.'" s="'.self::GAYA_ANGKA.'"><v>'.$angka.'</v></c>';
        }

        $gaya = $judul ? self::GAYA_JUDUL : self::GAYA_TEKS;

        return '<c r="'.$ref.'" t="inlineStr" s="'.$gaya.'"><is><t xml:space="preserve">'
            .htmlspecialchars((string) $nilai, ENT_XML1 | ENT_QUOTES, 'UTF-8')
            .'</t></is></c>';
    }

    /** 0 → A, 25 → Z, 26 → AA. */
    private static function kolom(int $indeks): string
    {
        $nama = '';

        for ($n = $indeks + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $nama = chr(65 + ($n - 1) % 26).$nama;
        }

        return $nama;
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    private function relasiAkar(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function workbook(): string
    {
        // Sheet names may not exceed 31 characters or contain []:*?/\ .
        $nama = mb_substr(preg_replace('/[\[\]:*?\/\\\\]/', ' ', $this->namaLembar) ?? 'Lembar1', 0, 31);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.htmlspecialchars($nama, ENT_XML1 | ENT_QUOTES, 'UTF-8').'" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    private function relasiWorkbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    /** Four cell formats: default, bold header, two-decimal number, text. */
    private function gaya(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="2"><fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="4">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="49" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyNumberFormat="1"/>'
            .'<xf numFmtId="2" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'<xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }
}
