<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Enums\StudentStatus;
use App\Models\Bridge\BridgeConsumer;
use App\Models\Feeder\FeederMapping;
use App\Models\System\Pengumuman;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Integration baseline: the local enum ⇄ PDDIKTI code map, and Open Campus
 * registered as the first Campus Bridge consumer.
 */
class IntegrasiSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedFeederMappings();
        $this->seedBridgeConsumer();
        $this->seedPengumuman();
    }

    /**
     * Seeded from the PDDIKTI codes documented for AktivitasKuliahMahasiswa.
     * A real installation overwrites these from the reference pull — the map
     * exists precisely so a Feeder build with a different code set does not
     * require a code change.
     */
    private function seedFeederMappings(): void
    {
        $statusMahasiswa = [
            StudentStatus::Aktif->value => 'A',
            StudentStatus::Cuti->value => 'C',
            StudentStatus::NonAktif->value => 'N',
            StudentStatus::Lulus->value => 'L',
            StudentStatus::DropOut->value => 'D',
            StudentStatus::Keluar->value => 'K',
            StudentStatus::GantiProdi->value => 'G',
        ];

        foreach ($statusMahasiswa as $local => $feeder) {
            FeederMapping::create([
                'group' => 'status_mahasiswa',
                'local_value' => $local,
                'feeder_code' => $feeder,
                'feeder_label' => StudentStatus::from($local)->label(),
            ]);
        }

        $agama = [
            '1' => 'Islam', '2' => 'Kristen', '3' => 'Katholik',
            '4' => 'Hindu', '5' => 'Budha', '6' => 'Khonghucu', '99' => 'Lainnya',
        ];

        foreach ($agama as $kode => $label) {
            FeederMapping::create([
                'group' => 'agama',
                'local_value' => $kode,
                'feeder_code' => $kode,
                'feeder_label' => $label,
            ]);
        }

        // Jalur masuk dan jenis pendaftaran dipakai mapper riwayat pendidikan.
        $jalurMasuk = [
            'Reguler' => '1',
            'Prestasi' => '2',
            'Undangan' => '3',
        ];

        foreach ($jalurMasuk as $local => $feeder) {
            FeederMapping::create([
                'group' => 'jalur_masuk',
                'local_value' => $local,
                'feeder_code' => $feeder,
                'feeder_label' => $local,
            ]);

            FeederMapping::create([
                'group' => 'jenis_daftar',
                'local_value' => $local,
                'feeder_code' => '1', // Peserta didik baru
                'feeder_label' => 'Peserta Didik Baru',
            ]);
        }
    }

    private function seedBridgeConsumer(): void
    {
        BridgeConsumer::create([
            'nama' => 'Open Campus',
            'slug' => 'open-campus',
            'deskripsi' => 'Lapisan ekosistem & engagement — jejaring sosial kampus, review evidence, dan dasbor 12 IKU.',
            'base_url' => 'http://localhost:8001',
            'scopes' => array_keys(config('bridge.scopes')),
            'webhook_url' => 'http://localhost:8001/api/webhooks/open-academic',
            'webhook_secret' => Str::random(48),
            'webhook_events' => config('bridge.events'),
            'is_active' => true,
        ]);
    }

    private function seedPengumuman(): void
    {
        $daftar = [
            [
                'judul' => 'Pengisian KRS Semester Ganjil Telah Dibuka',
                'ringkasan' => 'Mahasiswa aktif dapat mengisi rencana studi hingga batas perubahan KRS.',
                'isi' => 'Pengisian Kartu Rencana Studi dibuka sesuai kalender akademik. '
                    .'Jumlah SKS yang dapat diambil ditentukan oleh Indeks Prestasi Semester terakhir. '
                    .'Rencana studi yang telah diajukan menunggu persetujuan Dosen Wali sebelum dinyatakan sah. '
                    .'Mahasiswa dengan tunggakan pembayaran melebihi ketentuan minimum tidak dapat mengajukan KRS.',
                'target' => ['mahasiswa', 'dosen'],
                'pinned' => true,
            ],
            [
                'judul' => 'Batas Akhir Pembayaran Biaya Kuliah',
                'ringkasan' => 'KRS tidak dapat diajukan sebelum pembayaran minimum terpenuhi.',
                'isi' => 'Pembayaran biaya kuliah dapat dilakukan melalui virtual account, QRIS, maupun dompet digital '
                    .'yang tersedia pada menu Tagihan. Mahasiswa yang memerlukan keringanan dapat mengajukan '
                    .'dispensasi ke Bagian Keuangan sebelum batas waktu berakhir.',
                'target' => ['mahasiswa'],
                'pinned' => false,
            ],
            [
                'judul' => 'Sinkronisasi PDDIKTI Periode Pelaporan',
                'ringkasan' => 'Operator diminta melengkapi data mahasiswa sebelum jadwal sinkronisasi.',
                'isi' => 'Validasi pra-sinkron dijalankan sebelum data dikirim ke Neo Feeder. '
                    .'Baris yang tidak lolos aturan PDDIKTI — antara lain NIK kosong dan NIDN tidak valid — '
                    .'ditampilkan pada laporan validasi dan wajib diperbaiki terlebih dahulu.',
                'target' => ['staff'],
                'pinned' => false,
            ],
            [
                'judul' => 'Pengisian Nilai Akhir Semester',
                'ringkasan' => 'Dosen pengampu dapat mengunci nilai setelah seluruh komponen terisi.',
                'isi' => 'Nilai akhir dihitung otomatis dari komponen berbobot yang ditetapkan pada tiap kelas. '
                    .'Setelah dikunci dan difinalisasi, nilai tidak dapat diubah langsung; perbaikan hanya '
                    .'dimungkinkan melalui jalur koreksi yang tercatat pada log aktivitas.',
                'target' => ['dosen'],
                'pinned' => false,
            ],
        ];

        foreach ($daftar as $item) {
            Pengumuman::create([
                'judul' => $item['judul'],
                'slug' => Str::slug($item['judul']),
                'ringkasan' => $item['ringkasan'],
                'isi' => $item['isi'],
                'target_roles' => $item['target'],
                'is_pinned' => $item['pinned'],
                'published_at' => now()->subDays(fake()->numberBetween(1, 20)),
            ]);
        }
    }
}
