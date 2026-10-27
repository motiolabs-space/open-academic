<?php

declare(strict_types=1);

namespace App\Services\TugasAkhir;

use App\DTOs\TugasAkhir\ProposalData;
use App\Enums\KeputusanTelaah;
use App\Enums\StatusProposal;
use App\Enums\TugasAkhirStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Models\TugasAkhir\Proposal;
use App\Models\TugasAkhir\Telaah;
use App\Models\TugasAkhir\TugasAkhir;
use App\Notifications\TugasAkhir\ProposalDiajukan;
use App\Notifications\TugasAkhir\ProposalDitelaah;
use App\Services\Berkas\BerkasService;
use App\Services\Notifikasi\Notifier;
use Illuminate\Support\Facades\DB;

/**
 * The proposal document as a stage before the title is approved (#38).
 *
 * The stage exists only where a programme switched it on, and only for
 * projects proposed after it did: `tugas_akhir.wajib_proposal` is fixed when
 * the title is proposed, so a running project never acquires a hurdle it was
 * not proposed under.
 *
 * Nothing here is overwritten. A revision is the next version, a decision is
 * a new review row, and the version keeps the decision it was given — the
 * student's history of what they submitted and what they were told is the
 * point of the stage, not a by-product.
 *
 * An accepted proposal opens the way the project already had: the title can
 * then be approved (TugasAkhirService::setujuiJudul), supervisors assigned,
 * and the proposal seminar scheduled as before. A refused one closes the
 * project like a refused title.
 */
class ProposalService
{
    public function __construct(
        private readonly BerkasService $berkas,
        private readonly Notifier $notifier,
    ) {}

    public function wajib(TugasAkhir $ta): bool
    {
        return (bool) $ta->wajib_proposal;
    }

    public function sudahDiterima(TugasAkhir $ta): bool
    {
        return $ta->proposal()->where('status', StatusProposal::Diterima->value)->exists();
    }

    public function terakhir(TugasAkhir $ta): ?Proposal
    {
        return $ta->proposal()->first();
    }

    /** Uploads the next version: the first, or the one a request for revision asked for. */
    public function unggah(TugasAkhir $ta, ProposalData $data): Proposal
    {
        if (!$this->wajib($ta)) {
            throw new AturanAkademikException(__('tugas_akhir_proposal.galat.tahap_tidak_aktif'));
        }

        if ($ta->status !== TugasAkhirStatus::Diajukan) {
            throw new AturanAkademikException(__('tugas_akhir_proposal.galat.status_ta', ['status' => $ta->status->label()]));
        }

        $terakhir = $this->terakhir($ta);

        if ($terakhir !== null && $terakhir->status !== StatusProposal::Revisi) {
            throw new AturanAkademikException(__('tugas_akhir_proposal.galat.tidak_dapat_unggah.'.$terakhir->status->value));
        }

        $calonId = $data->calonPembimbingId ?? $terakhir?->calon_pembimbing_dosen_id;

        if ($calonId !== null) {
            $calon = Dosen::find($calonId);

            if ($calon === null || !$calon->is_active) {
                throw new AturanAkademikException(__('tugas_akhir_proposal.galat.calon_nonaktif'));
            }
        }

        $path = $this->berkas->simpan($data->berkas, 'tugas-akhir/proposal/'.$ta->uuid);

        try {
            $proposal = DB::transaction(function () use ($ta, $data, $terakhir, $calonId, $path): Proposal {
                $judul = $data->judul ?? $ta->judul;

                if ($judul !== $ta->judul) {
                    $ta->update(['judul' => $judul]);
                }

                $proposal = Proposal::create([
                    'tugas_akhir_id' => $ta->id,
                    'versi' => ($terakhir?->versi ?? 0) + 1,
                    'judul' => $judul,
                    'ringkasan' => $data->ringkasan,
                    'berkas_path' => $path,
                    'berkas_nama' => $data->berkas->getClientOriginalName(),
                    'calon_pembimbing_dosen_id' => $calonId,
                    'status' => StatusProposal::Menunggu,
                ]);

                $ta->recordActivity('proposal_uploaded', __('tugas_akhir_proposal.log.unggah', ['versi' => $proposal->versi]));

                return $proposal;
            });
        } catch (\Throwable $e) {
            $this->berkas->hapus($path);

            throw $e;
        }

        if ($proposal->calonPembimbing !== null) {
            $this->notifier->kirim($proposal->calonPembimbing, new ProposalDiajukan($proposal));
        }

        return $proposal;
    }

    /**
     * Records a reviewer's decision on the latest version.
     *
     * A lecturer reviews only proposals naming them as prospective
     * supervisor; staff with the final-project grant review any. A request for
     * revision or a refusal carries a note the student can act on.
     */
    public function telaah(Proposal $proposal, Dosen|Staff $penelaah, KeputusanTelaah $keputusan, ?string $catatan = null): Telaah
    {
        $catatan = filled($catatan) ? trim((string) $catatan) : null;

        if ($proposal->status !== StatusProposal::Menunggu) {
            throw new AturanAkademikException(__('tugas_akhir_proposal.galat.sudah_diputus', [
                'status' => $proposal->status->label(),
            ]));
        }

        if ($penelaah instanceof Dosen && (int) $proposal->calon_pembimbing_dosen_id !== (int) $penelaah->id) {
            throw new AturanAkademikException(__('tugas_akhir_proposal.galat.bukan_calon'));
        }

        if ($keputusan->wajibCatatan() && $catatan === null) {
            throw new AturanAkademikException(__('tugas_akhir_proposal.galat.catatan_wajib'));
        }

        $ta = $proposal->tugasAkhir;

        if ($ta->status !== TugasAkhirStatus::Diajukan) {
            throw new AturanAkademikException(__('tugas_akhir_proposal.galat.status_ta', ['status' => $ta->status->label()]));
        }

        $telaah = DB::transaction(function () use ($proposal, $ta, $penelaah, $keputusan, $catatan): Telaah {
            $telaah = Telaah::create([
                'tugas_akhir_proposal_id' => $proposal->id,
                'dosen_id' => $penelaah instanceof Dosen ? $penelaah->id : null,
                'staff_id' => $penelaah instanceof Staff ? $penelaah->id : null,
                'keputusan' => $keputusan,
                'catatan' => $catatan,
            ]);

            $proposal->update([
                'status' => $keputusan->status(),
                'diputus_at' => now(),
            ]);

            // A refused proposal is a refused title: the project closes and
            // the student's slot is released for a new one, exactly as
            // TugasAkhirService::tolakJudul() does.
            if ($keputusan === KeputusanTelaah::Ditolak) {
                $ta->update([
                    'status' => TugasAkhirStatus::Ditolak,
                    'mahasiswa_aktif_id' => null,
                    'disetujui_by_staff_id' => $penelaah instanceof Staff ? $penelaah->id : null,
                    'catatan' => $catatan,
                ]);
            }

            $ta->recordActivity('proposal_reviewed', __('tugas_akhir_proposal.log.telaah', [
                'versi' => $proposal->versi,
                'keputusan' => $keputusan->label(),
                'penelaah' => $penelaah instanceof Dosen ? $penelaah->namaLengkap() : $penelaah->nama,
            ]));

            return $telaah;
        });

        $this->notifier->kirim($ta->mahasiswa, new ProposalDitelaah($proposal->refresh(), $telaah));

        return $telaah;
    }
}
