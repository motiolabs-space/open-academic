<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTOs\Instalasi\SetupInstalasiData;
use App\Enums\EducationLevel;
use App\Enums\SemesterType;
use App\Http\Requests\Instalasi\SetupInstalasiRequest;
use App\Services\Instalasi\SetupInstalasiService;
use App\Services\Instalasi\TelemetriService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The first-run wizard (ADR-043). Reachable only while the installation has
 * no identity and no staff; 404 for everyone, forever, once it has run.
 */
class SetupController extends Controller
{
    public function __construct(private readonly SetupInstalasiService $setup) {}

    public function formulir(TelemetriService $telemetri): View
    {
        abort_unless($this->setup->terbuka(), 404);

        return view('setup.formulir', [
            'jenjangPilihan' => collect(EducationLevel::cases())
                ->mapWithKeys(fn (EducationLevel $j): array => [$j->value => $j->label()]),
            'semesterPilihan' => collect(SemesterType::cases())
                ->mapWithKeys(fn (SemesterType $s): array => [$s->value => $s->label()]),
            'telemetriDiizinkan' => $telemetri->diizinkanEnv(),
            'tujuanTelemetri' => (string) config('instalasi.telemetri.endpoint'),
            'urlAplikasi' => (string) config('app.url'),
        ]);
    }

    public function simpan(SetupInstalasiRequest $request): RedirectResponse
    {
        abort_unless($this->setup->terbuka(), 404);

        $admin = $this->setup->jalankan(SetupInstalasiData::fromRequest($request));

        return redirect()->route('login')
            ->withInput(['identitas' => $admin->email])
            ->with('sukses', __('instalasi.selesai'));
    }
}
