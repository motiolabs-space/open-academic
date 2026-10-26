<?php

declare(strict_types=1);

namespace App\Notifications\TugasAkhir;

use App\Enums\KategoriNotifikasi;
use App\Models\TugasAkhir\Proposal;
use App\Notifications\Notifikasi;

/** A student named this lecturer as prospective supervisor on a proposal version. */
class ProposalDiajukan extends Notifikasi
{
    public function __construct(public readonly Proposal $proposal) {}

    public function kategori(): KategoriNotifikasi
    {
        return KategoriNotifikasi::TugasAkhir;
    }

    public function judul(object $penerima): string
    {
        return __('tugas_akhir_proposal.notifikasi.diajukan_judul');
    }

    public function ringkasan(object $penerima): string
    {
        return __('tugas_akhir_proposal.notifikasi.diajukan_ringkasan', [
            'versi' => $this->proposal->versi,
            'judul' => $this->proposal->judul,
        ]);
    }

    public function tautan(object $penerima): ?string
    {
        return route('dosen.tugas-akhir.proposal', $this->proposal);
    }

    public function tone(): string
    {
        return 'info';
    }

    public function ajakan(): string
    {
        return __('tugas_akhir_proposal.notifikasi.diajukan_ajakan');
    }
}
