<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles and permissions, one set per guard.
 *
 * Permissions are named "<module>.<action>" so a policy can check a whole
 * module at a glance. Each guard gets its own copies because Spatie scopes
 * both roles and permissions by guard — a "krs.approve" permission on the
 * dosen guard is a different row from the staff one, deliberately.
 */
class RolePermissionSeeder extends Seeder
{
    /** @var array<string, array<int, string>> */
    private const PERMISSIONS = [
        UserRole::Staff->value => [
            'master.view', 'master.manage',
            'mahasiswa.view', 'mahasiswa.manage',
            'dosen.view', 'dosen.manage',
            'kelas.view', 'kelas.manage',

            // Writing holidays, activities and deadlines on the academic
            // calendar. Reading it needs no permission of its own.
            'kalender.manage',

            // Reading the room-utilisation report (#42). Maintaining rooms is
            // master.manage; this only reads how the timetables use them.
            'ruang.view',

            'krs.view', 'krs.manage',
            'nilai.view', 'nilai.manage',
            'presensi.view',
            'keuangan.view', 'keuangan.manage',
            'pmb.view', 'pmb.manage',
            'tugas_akhir.view', 'tugas_akhir.manage',
            'surat.view', 'surat.manage',
            'edom.view', 'edom.manage',

            // Kuesioner umum: writing and publishing, and reading results.
            // Answering one is not a permission — the questionnaire's
            // audience decides (KuesionerPolicy::respond).
            'kuesioner.view', 'kuesioner.manage',
            'bkd.view', 'bkd.manage',
            'wisuda.view', 'wisuda.manage',

            /*
             * Graduates and the tracer study (ADR-037 §2), in three widening
             * exposures: view sees who is reachable without the contacts;
             * manage sees and corrects them and imports employment status;
             * ekspor puts them in a file that leaves the system, and every
             * use is logged.
             */
            'alumni.view', 'alumni.manage', 'alumni.ekspor',

            'feeder.view', 'feeder.sync',
            'lkps.view',
            'bridge.view', 'bridge.manage',

            /*
             * Creating Campus Bridge consumers and SSO clients, and issuing,
             * revoking and rotating their tokens and secrets. Apart from
             * bridge.manage (replaying a webhook) because a token is a
             * standing grant to read student records in bulk. Super-admin and
             * the IT office only.
             */
            'integrasi.kredensial',
            'pengaturan.view', 'pengaturan.manage',
            'log.view',

            /*
             * Helpdesk, in three widening circles.
             *
             * view and tangani are scoped by the policy to the holder's own
             * unit plus tickets assigned to them by name; manage lifts that
             * scope to the whole campus and unlocks categories and FAQ. Every
             * office handles its own queue, so view + tangani go to every
             * staff role; manage goes to BAAK and the super-admin only.
             */
            'helpdesk.view', 'helpdesk.tangani', 'helpdesk.manage',

            // Filing one's own ticket to another unit — a broken laptop to IT,
            // a payroll question to finance. Every staff role holds it.
            'helpdesk.ajukan',
        ],
        UserRole::Dosen->value => [
            'kelas.view',
            'nilai.view', 'nilai.manage',
            'presensi.view', 'presensi.manage',
            'krs.approve',

            // Academic advising for study plans — a lecturer is advisor to a
            // whole cohort. Deliberately NOT the same thing as supervising a
            // final project, which is the tugas_akhir.* set below: conflating
            // them would hand every advisor supervision rights over students
            // whose work they have never seen.
            'bimbingan.view',

            'tugas_akhir.bimbing',
            'tugas_akhir.uji',

            // Reading one's own evaluation results. Scoped to self by the
            // controller, not by this permission — there is no version of this
            // grant that opens a colleague's scores.
            'edom.hasil',

            // Filing one's own workload report and maintaining one's own record.
            'bkd.lapor',
            'portofolio.kelola',

            /*
             * Assessing somebody else's report.
             *
             * Held by every lecturer, because which reports a person may assess
             * is decided per report by the asesor_1/asesor_2 columns — not by
             * the role. A role-level grant would let any lecturer sign off any
             * colleague's workload, which is the same mistake avoided in
             * tugas_akhir.bimbing.
             */
            'bkd.nilai',

            // Filing a ticket about one's own affairs. Scoped to self by the
            // policy, like edom.hasil above.
            'helpdesk.ajukan',
        ],
        UserRole::Mahasiswa->value => [
            'krs.submit',
            'khs.view',
            'jadwal.view',
            'tagihan.view',
            'profil.manage',
            'tugas_akhir.ajukan',
            'surat.ajukan',
            'edom.isi',
            'helpdesk.ajukan',
        ],
    ];

    /** @var array<string, array<string, array<int, string>|string>> */
    private const ROLES = [
        UserRole::Staff->value => [
            'super-admin' => '*',
            'baak' => [
                'master.view', 'master.manage', 'mahasiswa.view', 'mahasiswa.manage',
                'dosen.view', 'kelas.view', 'kelas.manage', 'kalender.manage', 'ruang.view', 'krs.view', 'krs.manage',
                'nilai.view', 'presensi.view', 'pmb.view', 'pmb.manage',
                'tugas_akhir.view', 'tugas_akhir.manage',
                'surat.view', 'surat.manage',
                'edom.view', 'edom.manage', 'kuesioner.view', 'kuesioner.manage',
                'wisuda.view', 'wisuda.manage', 'feeder.view', 'feeder.sync',
                'alumni.view', 'alumni.manage', 'alumni.ekspor',
                'helpdesk.view', 'helpdesk.tangani', 'helpdesk.manage', 'helpdesk.ajukan',
            ],
            'keuangan' => [
                'keuangan.view', 'keuangan.manage', 'mahasiswa.view',
                'helpdesk.view', 'helpdesk.tangani', 'helpdesk.ajukan',
            ],
            'operator-pddikti' => [
                'feeder.view', 'feeder.sync', 'mahasiswa.view', 'dosen.view', 'kelas.view', 'nilai.view',
                'helpdesk.view', 'helpdesk.tangani', 'helpdesk.ajukan',
            ],

            // Facilities: which rooms overflow and which sit empty, without
            // the timetable's editing screens.
            'sarpras' => [
                'master.view', 'ruang.view', 'helpdesk.view', 'helpdesk.tangani', 'helpdesk.ajukan',
            ],

            // The IT office: integrations and their credentials, and its own
            // helpdesk queue. No academic or financial records.
            'tik' => [
                'bridge.view', 'bridge.manage', 'integrasi.kredensial', 'log.view',
                'helpdesk.view', 'helpdesk.tangani', 'helpdesk.ajukan',
            ],

            // Leadership reads results but does not open, close, or reword a
            // period — an instrument whose questions can be edited by the person
            // being judged by them measures nothing.
            'pimpinan' => [
                'master.view', 'mahasiswa.view', 'dosen.view', 'kelas.view', 'ruang.view',
                'keuangan.view', 'feeder.view', 'edom.view', 'kuesioner.view', 'helpdesk.view', 'helpdesk.ajukan', 'alumni.view',
            ],
        ],
        UserRole::Dosen->value => [
            'dosen' => [
                'kelas.view', 'nilai.view', 'nilai.manage', 'presensi.view', 'presensi.manage',

                // Every lecturer may supervise and examine. Which projects they
                // may touch is decided per project by the pembimbing and penguji
                // rows, not by the role — a role-level grant would let any
                // lecturer sign off any student's consultation log.
                'tugas_akhir.bimbing', 'tugas_akhir.uji', 'edom.hasil',
                'bkd.lapor', 'bkd.nilai', 'portofolio.kelola', 'helpdesk.ajukan',
            ],
            'dosen-wali' => [
                'kelas.view', 'nilai.view', 'nilai.manage', 'presensi.view', 'presensi.manage',
                'krs.approve', 'bimbingan.view',
                'tugas_akhir.bimbing', 'tugas_akhir.uji', 'edom.hasil',
                'bkd.lapor', 'bkd.nilai', 'portofolio.kelola', 'helpdesk.ajukan',
            ],
        ],
        UserRole::Mahasiswa->value => [
            'mahasiswa' => '*',
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $guard => $permissions) {
            foreach ($permissions as $permission) {
                Permission::findOrCreate($permission, $guard);
            }
        }

        foreach (self::ROLES as $guard => $roles) {
            foreach ($roles as $name => $permissions) {
                $role = Role::findOrCreate($name, $guard);

                $role->syncPermissions(
                    $permissions === '*' ? self::PERMISSIONS[$guard] : $permissions,
                );
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
