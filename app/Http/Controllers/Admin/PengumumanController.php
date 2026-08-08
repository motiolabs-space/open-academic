<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\System\Pengumuman;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Portal announcements.
 *
 * Kept deliberately small, as the schema comment asks: title, body, who sees
 * it, when it goes live. Comments, reactions and feeds belong to Open Campus —
 * growing this into a CMS would duplicate the engagement layer inside the
 * system of record.
 *
 * The one thing worth care is scheduling. An announcement with a future
 * `published_at` is invisible until that moment arrives, which is what lets a
 * registrar write the KRS notice on Friday and have it appear on Monday.
 */
class PengumumanController extends Controller
{
    public function index(Request $request): View
    {
        $this->izin('pengaturan.view');

        return view('admin.pengumuman', [
            'judul' => 'Pengumuman',
            'konteks' => Pengumuman::terbit()->count().' terbit',
            'breadcrumb' => ['Dasbor' => route('admin.dashboard'), 'Pengumuman'],

            'daftar' => Pengumuman::query()
                ->orderByDesc('is_pinned')
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->paginate(20),

            'peranPilihan' => collect(UserRole::cases())
                ->mapWithKeys(fn (UserRole $r): array => [$r->value => $r->label()])
                ->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->izin('pengaturan.manage');

        $data = $this->validasi($request);

        Pengumuman::create($data + ['slug' => $this->slug($data['judul'])]);

        // `nullable` does not put the key in the validated array when the field
        // was never submitted, so the absent case and the explicitly-empty case
        // both have to read as "draft".
        return back()->with('sukses', ($data['published_at'] ?? null) === null
            ? 'Pengumuman disimpan sebagai draf; belum terlihat siapa pun.'
            : 'Pengumuman disimpan.');
    }

    public function perbarui(Request $request, Pengumuman $pengumuman): RedirectResponse
    {
        $this->izin('pengaturan.manage');

        $pengumuman->update($this->validasi($request, $pengumuman));

        return back()->with('sukses', 'Pengumuman diperbarui.');
    }

    /** Publishes now, or unpublishes back to a draft. */
    public function terbitkan(Pengumuman $pengumuman): RedirectResponse
    {
        $this->izin('pengaturan.manage');

        $terbit = $pengumuman->published_at === null;

        $pengumuman->update(['published_at' => $terbit ? now() : null]);

        return back()->with('sukses', $terbit
            ? 'Pengumuman diterbitkan dan langsung terlihat.'
            : 'Pengumuman ditarik kembali menjadi draf.');
    }

    public function sematkan(Pengumuman $pengumuman): RedirectResponse
    {
        $this->izin('pengaturan.manage');

        $pengumuman->update(['is_pinned' => !$pengumuman->is_pinned]);

        return back()->with('sukses', $pengumuman->is_pinned
            ? 'Pengumuman disematkan di puncak daftar.'
            : 'Sematan dilepas.');
    }

    public function hapus(Pengumuman $pengumuman): RedirectResponse
    {
        $this->izin('pengaturan.manage');

        $pengumuman->delete();

        return back()->with('sukses', 'Pengumuman dihapus.');
    }

    /**
     * A slug that stays unique without silently overwriting an older post.
     *
     * Two "Jadwal KRS" announcements in consecutive semesters is entirely
     * normal, and the second must not take over the first one's address.
     */
    private function slug(string $judul): string
    {
        $dasar = Str::slug($judul) ?: 'pengumuman';
        $slug = $dasar;
        $n = 2;

        while (Pengumuman::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $dasar.'-'.$n++;
        }

        return $slug;
    }

    /** @return array<string, mixed> */
    private function validasi(Request $request, ?Pengumuman $pengumuman = null): array
    {
        return $request->validate([
            'judul' => ['required', 'string', 'max:255'],
            'ringkasan' => ['nullable', 'string', 'max:500'],
            'isi' => ['required', 'string', 'max:20000'],

            // At least one portal, or nobody would ever see it.
            'target_roles' => ['required', 'array', 'min:1'],
            'target_roles.*' => [Rule::enum(UserRole::class)],

            'is_pinned' => ['sometimes', 'boolean'],

            // Null keeps it a draft; a future time schedules it.
            'published_at' => ['nullable', 'date'],
        ], [
            'target_roles.required' => 'Pilih setidaknya satu portal — pengumuman tanpa sasaran '
                .'tidak akan pernah terlihat siapa pun.',
        ]);
    }

    private function izin(string $permission): void
    {
        abort_unless(Portal::user()?->hasPermissionTo($permission, 'staff') ?? false, 403);
    }
}
