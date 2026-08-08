<?php

declare(strict_types=1);

use App\Enums\SemesterType;
use App\Enums\StudentStatus;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Staff;
use App\Models\System\LogAktivitas;

/**
 * The audit trail is the mechanism an auditor relies on to answer "who changed
 * this grade, and when". It is worth proving it actually writes rows rather
 * than assuming the trait works.
 */
it('mencatat pembuatan record akademik', function () {
    $mahasiswa = Mahasiswa::factory()->create(['nama' => 'Siti Aminah']);

    $log = LogAktivitas::forSubject($mahasiswa)->firstOrFail();

    expect($log->event)->toBe('created')
        ->and($log->subject_label)->toBe('Siti Aminah')
        ->and($log->uuid)->not->toBeNull();
});

it('mencatat nilai lama dan baru saat perubahan', function () {
    $mahasiswa = Mahasiswa::factory()->create(['status' => StudentStatus::Aktif]);

    $mahasiswa->update(['status' => StudentStatus::Cuti]);

    $log = LogAktivitas::forSubject($mahasiswa)->where('event', 'updated')->firstOrFail();

    expect($log->changes)->toHaveKey('status')
        ->and($log->changes['status']['old'])->toBe(StudentStatus::Aktif->value)
        ->and($log->changes['status']['new'])->toBe(StudentStatus::Cuti->value);
});

it('menyebut aktor yang melakukan perubahan', function () {
    $staff = Staff::factory()->create(['nama' => 'Sri Wahyuni']);
    $mahasiswa = Mahasiswa::factory()->create();

    $this->actingAs($staff, 'staff');

    $mahasiswa->update(['telepon' => '081200000000']);

    $log = LogAktivitas::forSubject($mahasiswa)->where('event', 'updated')->firstOrFail();

    expect($log->causer_id)->toBe($staff->id)
        ->and($log->causer_name)->toBe('Sri Wahyuni')
        ->and($log->causer_type)->toBe($staff->getMorphClass());
});

it('tidak pernah menuliskan kata sandi ke jejak audit', function () {
    $mahasiswa = Mahasiswa::factory()->create();

    $mahasiswa->update(['password' => 'kata-sandi-baru']);

    $logs = LogAktivitas::forSubject($mahasiswa)->get();

    foreach ($logs as $log) {
        expect($log->changes ?? [])->not->toHaveKey('password')
            ->and($log->changes ?? [])->not->toHaveKey('remember_token');
    }
});

it('mengabaikan perubahan yang tidak layak diaudit', function () {
    $mahasiswa = Mahasiswa::factory()->create();

    // last_login_at ada di daftar logExcept — pembaruannya tidak menghasilkan baris.
    $sebelum = LogAktivitas::forSubject($mahasiswa)->count();

    $mahasiswa->update(['last_login_at' => now()]);

    expect(LogAktivitas::forSubject($mahasiswa)->count())->toBe($sebelum);
});

it('mencatat penghapusan lunak sebagai peristiwa tersendiri', function () {
    $term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->create();

    $term->delete();

    expect(LogAktivitas::forSubject($term)->where('event', 'deleted')->exists())->toBeTrue();
});

it('mempertahankan baris audit meski subjeknya sudah dihapus permanen', function () {
    $term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->create();
    $termId = $term->id;

    $term->forceDelete();

    expect(
        LogAktivitas::where('subject_type', $term->getMorphClass())
            ->where('subject_id', $termId)
            ->count()
    )->toBeGreaterThan(0);
});
