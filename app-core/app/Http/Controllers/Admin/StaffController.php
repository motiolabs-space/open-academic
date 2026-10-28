<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sdm\Staff;
use App\Services\Sdm\KepegawaianService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/**
 * Administrative accounts and what each of them may do.
 *
 * Role is the whole point of this screen. Every other field is contact detail;
 * the role decides whether somebody can enter grades, push to PDDIKTI, or issue
 * Campus Bridge tokens — so it is chosen explicitly at creation and changed
 * with an audit trail behind it.
 */
class StaffController extends Controller
{
    public function __construct(private readonly KepegawaianService $kepegawaian) {}

    public function index(Request $request): View
    {
        $this->izin('pengaturan.view');

        $daftar = Staff::query()
            ->with('roles')
            ->cari($request->string('cari'), ['nama', 'nip', 'email'])
            ->orderBy('nama')
            ->paginate(25)
            ->withQueryString();

        return view('admin.staff', [
            'judul' => 'Akun Staf',
            'konteks' => $daftar->total().' akun',
            'breadcrumb' => ['Dasbor' => route('admin.dashboard'), 'Akun Staf'],
            'daftar' => $daftar,
            'daftarPeran' => Role::where('guard_name', 'staff')->orderBy('name')->pluck('name', 'name'),
            'filter' => $request->only(['cari']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->izin('pengaturan.manage');

        $data = $this->validasi($request);
        $peran = $data['peran'];
        unset($data['peran']);

        $this->authorize('assignRole', [Staff::class, null, $peran]);

        $hasil = $this->kepegawaian->buatStaff($data, $peran);

        return back()->with('kata_sandi_baru', [
            'nama' => $hasil['staff']->nama,
            'identitas' => $hasil['staff']->email,
            'kata_sandi' => $hasil['kata_sandi'],
        ]);
    }

    public function update(Request $request, Staff $staff): RedirectResponse
    {
        $this->izin('pengaturan.manage');
        $this->authorize('manage', $staff);

        $data = $this->validasi($request, $staff);
        $peran = $data['peran'];
        unset($data['peran']);

        $this->authorize('assignRole', [Staff::class, $staff, $peran]);

        // Role first: it is the step that can be refused, and a refusal must
        // not leave the contact details half-saved.
        $this->kepegawaian->ubahPeranStaff($staff, $peran);
        $staff->update($data);

        return back()->with('sukses', "Akun {$staff->nama} diperbarui.");
    }

    public function nonaktifkan(Staff $staff): RedirectResponse
    {
        $this->izin('pengaturan.manage');
        $this->authorize('manage', $staff);

        $this->kepegawaian->nonaktifkanStaff($staff);

        return back()->with('sukses', "Akun {$staff->nama} dinonaktifkan.");
    }

    public function aktifkan(Staff $staff): RedirectResponse
    {
        $this->izin('pengaturan.manage');
        $this->authorize('manage', $staff);

        $this->kepegawaian->aktifkanStaff($staff);

        return back()->with('sukses', "Akun {$staff->nama} diaktifkan kembali.");
    }

    public function resetKataSandi(Staff $staff): RedirectResponse
    {
        $this->izin('pengaturan.manage');
        $this->authorize('manage', $staff);

        $kataSandi = $this->kepegawaian->resetKataSandi($staff);

        return back()->with('kata_sandi_baru', [
            'nama' => $staff->nama,
            'identitas' => $staff->nip ?: $staff->email,
            'kata_sandi' => $kataSandi,
        ]);
    }

    /** @return array<string, mixed> */
    private function validasi(Request $request, ?Staff $staff = null): array
    {
        return $request->validate([
            'nip' => [
                'nullable', 'string', 'max:32',
                Rule::unique('staff', 'nip')->ignore($staff?->id)->whereNull('deleted_at'),
            ],
            'nama' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('staff', 'email')->ignore($staff?->id)->whereNull('deleted_at'),
            ],
            'telepon' => ['nullable', 'string', 'max:32'],
            'jabatan' => ['nullable', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:255'],
            'peran' => ['required', 'string', Rule::exists('roles', 'name')->where('guard_name', 'staff')],
        ]);
    }

    private function izin(string $permission): void
    {
        abort_unless(Portal::user()?->hasPermissionTo($permission, 'staff') ?? false, 403);
    }
}
