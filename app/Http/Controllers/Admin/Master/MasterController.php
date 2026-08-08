<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Master;

use App\Http\Controllers\Controller;
use App\Support\Portal;

/**
 * Shared plumbing for the master-data screens.
 *
 * Master data is one area with several tabs rather than six separate menu
 * entries: a registrar setting up a semester moves between terms, programmes
 * and courses in a single sitting, and splitting them across the sidebar turns
 * one task into six navigations.
 */
abstract class MasterController extends Controller
{
    /** @var array<string, array{label: string, route: string}> */
    protected const TABS = [
        'tahun-akademik' => ['label' => 'Tahun Akademik', 'route' => 'admin.master.term'],
        'fakultas' => ['label' => 'Fakultas', 'route' => 'admin.master.fakultas'],
        'prodi' => ['label' => 'Program Studi', 'route' => 'admin.master.prodi'],
        'kurikulum' => ['label' => 'Kurikulum', 'route' => 'admin.master.kurikulum'],
        'mata-kuliah' => ['label' => 'Mata Kuliah', 'route' => 'admin.master.mata-kuliah'],
        'ruang' => ['label' => 'Gedung & Ruang', 'route' => 'admin.master.ruang'],
        'cpl' => ['label' => 'Capaian Pembelajaran', 'route' => 'admin.master.cpl'],
    ];

    protected function izin(string $permission): void
    {
        abort_unless(Portal::user()?->hasPermissionTo($permission, 'staff') ?? false, 403);
    }

    /**
     * The view payload every master screen shares.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function halaman(string $tab, string $konteks, array $data): array
    {
        return [
            'judul' => 'Master Akademik',
            'konteks' => $konteks,
            'breadcrumb' => ['Dasbor' => route('admin.dashboard'), 'Master Akademik'],
            'tabs' => self::TABS,
            'tabAktif' => $tab,
            ...$data,
        ];
    }
}
