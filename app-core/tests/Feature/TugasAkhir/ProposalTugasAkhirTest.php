<?php

declare(strict_types=1);

use App\DTOs\TugasAkhir\ProposalData;
use App\Enums\JenisUjian;
use App\Enums\KeputusanTelaah;
use App\Enums\PeranPenguji;
use App\Enums\StatusProposal;
use App\Enums\StudentStatus;
use App\Enums\TugasAkhirStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\StatusMahasiswa;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Models\TugasAkhir\Proposal;
use App\Models\TugasAkhir\Telaah;
use App\Models\TugasAkhir\TugasAkhir;
use App\Notifications\TugasAkhir\ProposalDiajukan;
use App\Notifications\TugasAkhir\ProposalDitelaah;
use App\Services\TugasAkhir\ProposalService;
use App\Services\TugasAkhir\TugasAkhirService;
use App\Services\TugasAkhir\UjianService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The proposal document as a stage before the title is approved (#38).
 *
 * Two things matter most and are pinned first: a programme that never turns
 * the stage on sees nothing change, and a project proposed before the switch
 * keeps the rule it was proposed under. After that, the trail: every version
 * and every decision survives, whatever happens next.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('local');

    $this->term = TahunAkademik::factory()->berjalan()->create(['is_active' => true]);
    $this->prodi = Prodi::factory()->create(['wajib_proposal_ta' => false]);

    $this->staf = Staff::factory()->create();
    $this->staf->assignRole('baak');

    $this->calon = Dosen::factory()->create(['is_active' => true]);
    $this->calon->assignRole('dosen');

    $this->ta = app(TugasAkhirService::class);
    $this->proposal = app(ProposalService::class);
});

function propMahasiswa(?Prodi $prodi = null): Mahasiswa
{
    $m = Mahasiswa::factory()->create(['prodi_id' => ($prodi ?? test()->prodi)->id, 'status' => StudentStatus::Aktif]);
    $m->assignRole('mahasiswa');

    StatusMahasiswa::create([
        'mahasiswa_id' => $m->id,
        'tahun_akademik_id' => test()->term->id,
        'semester_ke' => 7,
        'status' => StudentStatus::Aktif,
        'sks_semester' => 20,
        'sks_kumulatif' => 130,
        'ips' => 3.4,
        'ipk' => 3.4,
    ]);

    return $m;
}

/** A title proposed under the programme's current rule. */
function propAjukan(?Mahasiswa $m = null): TugasAkhir
{
    return app(TugasAkhirService::class)->ajukan($m ?? propMahasiswa(), test()->term, 'Sistem Pakar Diagnosis Hama Padi');
}

function propData(?int $calonId = null, ?string $judul = null, string $berkas = 'proposal.pdf'): ProposalData
{
    return new ProposalData(UploadedFile::fake()->create($berkas, 80, 'application/pdf'), $judul, null, $calonId);
}

function propWajibkan(): void
{
    test()->prodi->update(['wajib_proposal_ta' => true]);
}

/** Signs in as exactly one person; see kknSebagai() for why. */
function propSebagai(object $pengguna, string $guard): TestCase
{
    auth()->forgetGuards();

    return test()->actingAs($pengguna, $guard);
}

describe('config prodi', function () {
    it('membiarkan prodi yang tidak memakainya persis seperti dulu', function () {
        $ta = propAjukan();

        expect($ta->wajib_proposal)->toBeFalse()
            ->and($this->ta->setujuiJudul($ta, $this->staf)->status)->toBe(TugasAkhirStatus::Disetujui)
            ->and(fn () => $this->proposal->unggah(propAjukan(), propData()))->toThrow(AturanAkademikException::class, 'tidak berlaku');
    });

    it('menahan persetujuan judul pada prodi yang mewajibkannya sampai proposal diterima', function () {
        propWajibkan();
        $ta = propAjukan();

        expect($ta->wajib_proposal)->toBeTrue()
            ->and(fn () => $this->ta->setujuiJudul($ta, $this->staf))->toThrow(AturanAkademikException::class, 'mewajibkan dokumen proposal');

        $p = $this->proposal->unggah($ta, propData($this->calon->id));
        $this->proposal->telaah($p, $this->calon, KeputusanTelaah::Diterima);

        expect($this->ta->setujuiJudul($ta->fresh(), $this->staf)->status)->toBe(TugasAkhirStatus::Disetujui);
    });

    it('tidak menjangkau tugas akhir yang diajukan sebelum prodi menyalakannya', function () {
        $lama = propAjukan();
        propWajibkan();

        expect($lama->fresh()->wajib_proposal)->toBeFalse()
            ->and($this->ta->setujuiJudul($lama->fresh(), $this->staf)->status)->toBe(TugasAkhirStatus::Disetujui);
    });

    it('membuka jalur ke pembimbing dan seminar proposal yang sudah ada sesudah diterima', function () {
        propWajibkan();
        $ta = propAjukan();
        $p = $this->proposal->unggah($ta, propData($this->calon->id));
        $this->proposal->telaah($p, $this->staf, KeputusanTelaah::Diterima);
        $this->ta->setujuiJudul($ta->fresh(), $this->staf);
        $this->ta->tetapkanPembimbing($ta->fresh(), $this->calon);

        $hasil = app(UjianService::class)->jadwalkan(
            $ta->fresh(),
            JenisUjian::Proposal,
            now()->addWeek()->toDateString(),
            '09:00',
            '10:00',
            [['dosen_id' => $this->calon->id, 'peran' => PeranPenguji::Ketua]],
        );

        expect($hasil['ujian']->jenis)->toBe(JenisUjian::Proposal);
    });
});

describe('unggah dan telaah', function () {
    beforeEach(fn () => propWajibkan());

    it('menyimpan versi pertama sebagai menunggu telaah, dengan berkas di disk privat', function () {
        Notification::fake();
        $ta = propAjukan();

        $p = $this->proposal->unggah($ta, propData($this->calon->id));

        expect($p->versi)->toBe(1)
            ->and($p->status)->toBe(StatusProposal::Menunggu)
            ->and($p->judul)->toBe($ta->judul)
            ->and($p->berkas_nama)->toBe('proposal.pdf')
            ->and(Storage::disk('local')->exists($p->berkas_path))->toBeTrue();

        Notification::assertSentTo($this->calon, ProposalDiajukan::class);
    });

    it('menolak versi baru selama versi terakhir masih ditelaah', function () {
        $ta = propAjukan();
        $this->proposal->unggah($ta, propData());

        expect(fn () => $this->proposal->unggah($ta->fresh(), propData()))
            ->toThrow(AturanAkademikException::class, 'masih ditelaah');
    });

    it('menyimpan revisi sebagai versi baru tanpa menimpa versi dan catatan lama', function () {
        Notification::fake();
        $ta = propAjukan();
        $v1 = $this->proposal->unggah($ta, propData($this->calon->id));

        expect(fn () => $this->proposal->telaah($v1, $this->calon, KeputusanTelaah::Revisi))
            ->toThrow(AturanAkademikException::class, 'wajib disertai catatan');

        $this->proposal->telaah($v1, $this->calon, KeputusanTelaah::Revisi, 'Perjelas rumusan masalah.');
        $v2 = $this->proposal->unggah($ta->fresh(), propData(null, 'Sistem Pakar Hama Padi Berbasis Forward Chaining', 'revisi.pdf'));

        $v1->refresh();

        expect($v2->versi)->toBe(2)
            ->and($v2->calon_pembimbing_dosen_id)->toBe($this->calon->id)
            ->and($v2->judul)->toBe('Sistem Pakar Hama Padi Berbasis Forward Chaining')
            ->and($ta->fresh()->judul)->toBe('Sistem Pakar Hama Padi Berbasis Forward Chaining')
            ->and($v1->status)->toBe(StatusProposal::Revisi)
            ->and($v1->judul)->toBe('Sistem Pakar Diagnosis Hama Padi')
            ->and(Storage::disk('local')->exists($v1->berkas_path))->toBeTrue()
            ->and($v1->telaah()->first()->catatan)->toBe('Perjelas rumusan masalah.')
            ->and(Proposal::where('tugas_akhir_id', $ta->id)->count())->toBe(2);

        Notification::assertSentTo($ta->mahasiswa, ProposalDitelaah::class);
    });

    it('tidak mengubah keputusan yang sudah diambil', function () {
        $p = $this->proposal->unggah(propAjukan(), propData($this->calon->id));
        $this->proposal->telaah($p, $this->calon, KeputusanTelaah::Diterima);

        expect(fn () => $this->proposal->telaah($p->fresh(), $this->staf, KeputusanTelaah::Ditolak, 'Berubah pikiran'))
            ->toThrow(AturanAkademikException::class, 'sudah diputus')
            ->and(fn () => $this->proposal->unggah($p->tugasAkhir->fresh(), propData()))
            ->toThrow(AturanAkademikException::class, 'sudah diterima')
            ->and(Telaah::count())->toBe(1);
    });

    it('hanya calon pembimbing yang disebut yang dapat menelaah sebagai dosen', function () {
        $p = $this->proposal->unggah(propAjukan(), propData($this->calon->id));

        expect(fn () => $this->proposal->telaah($p, Dosen::factory()->create(), KeputusanTelaah::Diterima))
            ->toThrow(AturanAkademikException::class, 'calon pembimbing');

        $telaah = $this->proposal->telaah($p, $this->staf, KeputusanTelaah::Diterima);

        expect($telaah->staff_id)->toBe($this->staf->id)
            ->and($telaah->dosen_id)->toBeNull();
    });

    it('menutup tugas akhir dan membebaskan slot bila proposal ditolak', function () {
        $m = propMahasiswa();
        $ta = propAjukan($m);
        $p = $this->proposal->unggah($ta, propData($this->calon->id));

        $this->proposal->telaah($p, $this->calon, KeputusanTelaah::Ditolak, 'Topik di luar bidang prodi.');

        $ta->refresh();

        expect($ta->status)->toBe(TugasAkhirStatus::Ditolak)
            ->and($ta->mahasiswa_aktif_id)->toBeNull()
            ->and($ta->catatan)->toBe('Topik di luar bidang prodi.')
            ->and($p->fresh()->status)->toBe(StatusProposal::Ditolak)
            ->and(propAjukan($m)->status)->toBe(TugasAkhirStatus::Diajukan);
    });

    it('menolak calon pembimbing nonaktif', function () {
        $nonaktif = Dosen::factory()->create(['is_active' => false]);

        expect(fn () => $this->proposal->unggah(propAjukan(), propData($nonaktif->id)))
            ->toThrow(AturanAkademikException::class, 'dosen aktif');
    });
});

describe('layar dan otorisasi per portal', function () {
    beforeEach(fn () => propWajibkan());

    it('membiarkan mahasiswa mengunggah proposal untuk tugas akhirnya sendiri', function () {
        $m = propMahasiswa();
        $ta = propAjukan($m);

        propSebagai($m, 'mahasiswa')->get(route('mahasiswa.tugas-akhir'))->assertOk()->assertSee(__('tugas_akhir_proposal.kartu.unggah'));
        propSebagai($m, 'mahasiswa')->post(route('mahasiswa.tugas-akhir.proposal'), [
            'berkas' => UploadedFile::fake()->create('proposal.pdf', 50, 'application/pdf'),
            'calon_pembimbing_dosen_id' => $this->calon->id,
        ])->assertSessionHas('sukses');

        expect($ta->proposal()->count())->toBe(1);
    });

    it('melindungi berkas proposal: pemilik, calon pembimbing, dan staf saja', function () {
        $m = propMahasiswa();
        $p = $this->proposal->unggah(propAjukan($m), propData($this->calon->id));

        propSebagai($m, 'mahasiswa')->get(route('berkas.proposal-ta', $p))->assertOk();
        propSebagai($this->calon, 'dosen')->get(route('berkas.proposal-ta', $p))->assertOk();
        propSebagai($this->staf, 'staff')->get(route('berkas.proposal-ta', $p))->assertOk();
        propSebagai(propMahasiswa(), 'mahasiswa')->get(route('berkas.proposal-ta', $p))->assertForbidden();
        propSebagai(Dosen::factory()->create(), 'dosen')->get(route('berkas.proposal-ta', $p))->assertForbidden();
    });

    it('membiarkan calon pembimbing menelaah lewat portal dosen, dan menolak dosen lain', function () {
        $p = $this->proposal->unggah(propAjukan(), propData($this->calon->id));
        $lain = Dosen::factory()->create();
        $lain->assignRole('dosen');

        propSebagai($this->calon, 'dosen')->get(route('dosen.tugas-akhir'))->assertOk()->assertSee(__('tugas_akhir_proposal.kartu.menunggu_telaah'));
        propSebagai($lain, 'dosen')->get(route('dosen.tugas-akhir.proposal', $p))->assertForbidden();
        propSebagai($lain, 'dosen')->post(route('dosen.tugas-akhir.proposal.telaah', $p), ['keputusan' => 'diterima'])->assertForbidden();
        propSebagai($this->calon, 'dosen')->get(route('dosen.tugas-akhir.proposal', $p))->assertOk()->assertSee($p->judul);
        propSebagai($this->calon, 'dosen')->post(route('dosen.tugas-akhir.proposal.telaah', $p), ['keputusan' => 'revisi'])
            ->assertSessionHasErrors('catatan');
        propSebagai($this->calon, 'dosen')->post(route('dosen.tugas-akhir.proposal.telaah', $p), ['keputusan' => 'revisi', 'catatan' => 'Tambah tinjauan pustaka.'])
            ->assertSessionHas('sukses');

        expect($p->fresh()->status)->toBe(StatusProposal::Revisi);
    });

    it('membiarkan staf bagian tugas akhir menelaah dan melihat riwayatnya, tetapi bukan staf keuangan', function () {
        $p = $this->proposal->unggah(propAjukan(), propData($this->calon->id));
        $keuangan = Staff::factory()->create();
        $keuangan->assignRole('keuangan');

        propSebagai($keuangan, 'staff')->post(route('admin.tugas-akhir.proposal.telaah', $p), ['keputusan' => 'diterima'])->assertForbidden();
        propSebagai($this->staf, 'staff')->post(route('admin.tugas-akhir.proposal.telaah', $p), ['keputusan' => 'diterima'])->assertSessionHas('sukses');
        propSebagai($this->staf, 'staff')->get(route('admin.tugas-akhir.show', $p->tugasAkhir))->assertOk()
            ->assertSee(__('tugas_akhir_proposal.kartu.riwayat'))
            ->assertSee(__('tugas_akhir_proposal.status.diterima'));
    });

    it('menyimpan saklar prodi dari layar master', function () {
        $admin = Staff::factory()->create();
        $admin->assignRole('super-admin');
        $prodi = Prodi::factory()->create(['wajib_proposal_ta' => false]);

        propSebagai($admin, 'staff')->put(route('admin.master.prodi.update', $prodi), [
            'fakultas_id' => $prodi->fakultas_id,
            'kode' => $prodi->kode,
            'nama' => $prodi->nama,
            'jenjang' => $prodi->jenjang->value,
            'sks_lulus' => 144,
            'wajib_proposal_ta' => '1',
        ])->assertSessionHasNoErrors();

        expect($prodi->fresh()->wajib_proposal_ta)->toBeTrue();
    });
});
