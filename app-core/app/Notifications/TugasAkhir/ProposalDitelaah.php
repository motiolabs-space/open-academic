<?php

declare(strict_types=1);

namespace App\Notifications\TugasAkhir;

use App\Enums\KategoriNotifikasi;
use App\Enums\KeputusanTelaah;
use App\Models\TugasAkhir\Proposal;
use App\Models\TugasAkhir\Telaah;
use App\Notifications\Notifikasi;

/**
 * A proposal version was decided. The reviewer's note travels with it —
 * ProposalService refuses a revision request or refusal without one.
 */
class ProposalDitelaah extends Notifikasi
{
    public function __construct(
        public readonly Proposal $proposal,
        public readonly Telaah $telaah,
    ) {}

    public function kategori(): KategoriNotifikasi
    {
        return KategoriNotifikasi::TugasAkhir;
    }

    public function judul(object $penerima): string
    {
        return __('tugas_akhir_proposal.notifikasi.ditelaah_judul.'.$this->telaah->keputusan->value);
    }

    public function ringkasan(object $penerima): string
    {
        return __('tugas_akhir_proposal.notifikasi.ditelaah_ringkasan', [
            'versi' => $this->proposal->versi,
            'keputusan' => $this->telaah->keputusan->label(),
            'catatan' => $this->telaah->catatan ?? '—',
        ]);
    }

    public function tautan(object $penerima): ?string
    {
        return route('mahasiswa.tugas-akhir');
    }

    public function tone(): string
    {
        return match ($this->telaah->keputusan) {
            KeputusanTelaah::Diterima => 'success',
            KeputusanTelaah::Revisi => 'warning',
            KeputusanTelaah::Ditolak => 'danger',
        };
    }

    public function ajakan(): string
    {
        return __('tugas_akhir_proposal.notifikasi.ditelaah_ajakan');
    }
}
