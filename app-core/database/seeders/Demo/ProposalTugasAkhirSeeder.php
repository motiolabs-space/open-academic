<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Enums\KeputusanTelaah;
use App\Enums\StatusProposal;
use App\Enums\StudentStatus;
use App\Enums\TugasAkhirStatus;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Models\TugasAkhir\Proposal;
use App\Models\TugasAkhir\TugasAkhir;
use App\Services\TugasAkhir\ProposalService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * One programme that requires the proposal stage (#38), with two students in it.
 *
 * The first was asked to revise and has uploaded a second version, now waiting
 * on dosen1 — so the lecturer demo account has a proposal to review and the
 * student's screen shows the trail. The second was accepted and waits for the
 * programme to approve the title.
 *
 * Projects and versions are written directly, like TugasAkhirSeeder (the
 * credit floor a three-term demo cannot meet); the decisions go through
 * ProposalService so the reviews look exactly like ones made on screen. The
 * documents are a one-page PDF on the private disk, not written under tests.
 */
class ProposalTugasAkhirSeeder extends Seeder
{
    private const PDF_CONTOH = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
        ."2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
        ."3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]>>endobj\n"
        ."trailer<</Root 1 0 R>>\n%%EOF\n";

    public function run(ProposalService $layanan): void
    {
        $term = TahunAkademik::aktif();
        $dosen = Dosen::query()->where('email', 'dosen1@demo.test')->first()
            ?? Dosen::query()->where('is_active', true)->orderBy('id')->first();
        $staf = Staff::query()->where('email', 'baak@demo.test')->first();

        if ($term === null || $dosen === null || $staf === null) {
            return;
        }

        $mahasiswa = Mahasiswa::query()
            ->where('status', StudentStatus::Aktif->value)
            ->whereDoesntHave('tugasAkhir')
            ->orderByDesc('angkatan')
            ->orderBy('id')
            ->get();

        // The programme with the most students still free to propose.
        $prodiId = $mahasiswa->countBy('prodi_id')->sortDesc()->keys()->first();

        if ($prodiId === null) {
            return;
        }

        Prodi::whereKey($prodiId)->update(['wajib_proposal_ta' => true]);

        [$revisi, $diterima] = $mahasiswa->where('prodi_id', $prodiId)->take(2)->values()->pad(2, null)->all();

        if ($revisi !== null) {
            $ta = $this->tugasAkhir($revisi, $term, 'Sistem Pendukung Keputusan Penerima Beasiswa dengan Metode AHP');
            $v1 = $this->versi($ta, 1, $ta->judul, $dosen);
            $layanan->telaah($v1, $dosen, KeputusanTelaah::Revisi, 'Batasan masalah terlalu luas. Pilih satu skema beasiswa dan sebutkan kriterianya.');

            $judul = 'Sistem Pendukung Keputusan Penerima Beasiswa KIP Kuliah dengan Metode AHP';
            $ta->update(['judul' => $judul]);
            $this->versi($ta, 2, $judul, $dosen, 'Batasan masalah dipersempit ke KIP Kuliah; kriteria diambil dari pedoman.');
        }

        if ($diterima !== null) {
            $ta = $this->tugasAkhir($diterima, $term, 'Analisis Kualitas Layanan Portal Akademik dengan Metode WebQual');
            $v1 = $this->versi($ta, 1, $ta->judul, $dosen);
            $layanan->telaah($v1, $staf, KeputusanTelaah::Diterima, 'Diterima pada rapat program studi.');
        }
    }

    private function tugasAkhir(Mahasiswa $mahasiswa, TahunAkademik $term, string $judul): TugasAkhir
    {
        return TugasAkhir::create([
            'mahasiswa_id' => $mahasiswa->id,
            'tahun_akademik_id' => $term->id,
            'mahasiswa_aktif_id' => $mahasiswa->id,
            'judul' => $judul,
            'status' => TugasAkhirStatus::Diajukan,
            'wajib_proposal' => true,
            'tanggal_pengajuan' => now()->subWeeks(3)->toDateString(),
        ]);
    }

    private function versi(TugasAkhir $ta, int $versi, string $judul, Dosen $calon, ?string $ringkasan = null): Proposal
    {
        $path = 'tugas-akhir/proposal/'.$ta->uuid.'/'.Str::uuid().'.pdf';

        if (!app()->runningUnitTests()) {
            Storage::disk((string) config('berkas.disk'))->put($path, self::PDF_CONTOH);
        }

        return Proposal::create([
            'tugas_akhir_id' => $ta->id,
            'versi' => $versi,
            'judul' => $judul,
            'ringkasan' => $ringkasan,
            'berkas_path' => $path,
            'berkas_nama' => 'proposal-v'.$versi.'.pdf',
            'calon_pembimbing_dosen_id' => $calon->id,
            'status' => StatusProposal::Menunggu,
        ]);
    }
}
