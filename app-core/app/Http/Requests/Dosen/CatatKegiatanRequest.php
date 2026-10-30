<?php

declare(strict_types=1);

namespace App\Http\Requests\Dosen;

use App\Enums\BentukKeterlibatanKebijakan;
use App\Enums\IndeksPublikasi;
use App\Enums\JenisBuktiKebijakan;
use App\Enums\JenisLuaran;
use App\Enums\KuartilPublikasi;
use App\Enums\LecturerAssignmentType;
use App\Enums\PeranKegiatan;
use App\Enums\TingkatKebijakan;
use App\Enums\TingkatKegiatan;
use App\Enums\UnsurBkd;
use App\Models\Sdm\PenugasanDosen;
use App\Services\Berkas\BerkasService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An activity a lecturer records on their own portfolio.
 *
 * Policy drafting (IKU 8) asks for more than the other types: the form of
 * involvement, whose policy it was, the body that issued the document, and
 * the document itself. Without them the row cannot be checked against the
 * decree, so it is refused here rather than left for the verifier to chase.
 */
class CatatKegiatanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', PenugasanDosen::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $kebijakan = 'required_if:jenis,'.LecturerAssignmentType::PenyusunanKebijakan->value;

        return [
            'jenis' => ['required', Rule::enum(LecturerAssignmentType::class)],
            'unsur' => ['required', Rule::enum(UnsurBkd::class), 'not_in:'.UnsurBkd::Pendidikan->value],
            'judul' => ['required', 'string', 'max:255'],
            'peran' => ['nullable', Rule::enum(PeranKegiatan::class)],
            'tingkat' => ['nullable', Rule::enum(TingkatKegiatan::class)],
            'mitra_nama' => [$kebijakan, 'nullable', 'string', 'max:255'],
            'mitra_jenis' => ['nullable', 'string', 'max:48'],
            'lokasi' => ['nullable', 'string', 'max:255'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'sks_ekuivalen' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'luaran_jenis' => ['nullable', Rule::enum(JenisLuaran::class)],
            'luaran_identitas' => ['nullable', 'string', 'max:255'],
            'luaran_tahun' => ['nullable', 'integer', 'min:1950', 'max:'.(date('Y') + 1)],
            'keterangan' => ['nullable', 'string', 'max:1000'],

            // IKU 6 facts (ADR-041 §2). A quartile belongs to a journal in
            // Scopus or WoS only; anything else claiming one is a typo.
            'luaran_indeks' => ['nullable', Rule::enum(IndeksPublikasi::class)],
            'luaran_kuartil' => [
                'nullable', Rule::enum(KuartilPublikasi::class),
                'prohibited_unless:luaran_jenis,'.JenisLuaran::Jurnal->value,
                'prohibited_unless:luaran_indeks,'.implode(',', IndeksPublikasi::berkuartil()),
            ],
            'luaran_doi' => ['nullable', 'string', 'max:255', 'regex:~^(https?://(dx\.)?doi\.org/|doi:\s*)?10\.\d{4,9}/\S+$~i'],
            'luaran_tautan' => ['nullable', 'url:http,https', 'max:512'],
            'luaran_penerbit' => ['nullable', 'string', 'max:255'],
            'luaran_kolaborasi_internasional' => ['nullable', 'boolean'],

            'kebijakan_bentuk' => [$kebijakan, 'nullable', Rule::enum(BentukKeterlibatanKebijakan::class)],
            'kebijakan_tingkat' => [$kebijakan, 'nullable', Rule::enum(TingkatKebijakan::class)],
            'kebijakan_bukti_jenis' => [$kebijakan, 'nullable', Rule::enum(JenisBuktiKebijakan::class)],
            'kebijakan_nomor_bukti' => ['nullable', 'string', 'max:128'],

            // The decree recognises involvement only on an official document
            // naming the lecturer, so for this type the file is not optional.
            'dokumen' => [$kebijakan, ...app(BerkasService::class)->aturan('dokumen', wajib: false)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'mitra_nama.required_if' => __('portofolio.kebijakan.wajib.lembaga'),
            'kebijakan_bentuk.required_if' => __('portofolio.kebijakan.wajib.bentuk'),
            'kebijakan_tingkat.required_if' => __('portofolio.kebijakan.wajib.tingkat'),
            'kebijakan_bukti_jenis.required_if' => __('portofolio.kebijakan.wajib.bukti_jenis'),
            'dokumen.required_if' => __('portofolio.kebijakan.wajib.dokumen'),
            'luaran_kuartil.prohibited_unless' => __('portofolio.publikasi.validasi.kuartil'),
            'luaran_doi.regex' => __('portofolio.publikasi.validasi.doi'),
        ];
    }
}
