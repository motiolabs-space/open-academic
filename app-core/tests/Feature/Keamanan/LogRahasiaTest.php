<?php

declare(strict_types=1);

use App\Models\Bridge\BridgeConsumer;
use App\Models\Sdm\Staff;
use App\Models\System\LogAktivitas;
use App\Services\Auth\DuaFaktorService;
use Illuminate\Support\Facades\Route;

/**
 * The activity log is readable by anyone holding log.view; secrets stay out.
 *
 * Attributes a model hides from serialisation are never written to it. Before,
 * enrolling in 2FA logged the TOTP secret — decrypted, since the old value is
 * read through its cast — and a Bridge consumer's webhook secret went in plain.
 */
function isiLog(): string
{
    return (string) json_encode(LogAktivitas::query()->get()->map->getAttributes());
}

it('tidak mencatat rahasia dua langkah staf', function () {
    $staf = Staff::factory()->create();

    $rahasia = app(DuaFaktorService::class)->mulai($staf)['rahasia'];

    expect(isiLog())->not->toContain($rahasia)
        ->and(isiLog())->not->toContain('two_factor_secret');
});

it('tidak mencatat rahasia webhook konsumen Bridge', function () {
    $konsumen = BridgeConsumer::create([
        'nama' => 'Open Campus',
        'slug' => 'open-campus',
        'scopes' => ['students.read'],
        'is_active' => true,
        'webhook_secret' => 'rahasia-webhook-awal-0123456789',
    ]);

    $konsumen->update(['webhook_secret' => 'rahasia-webhook-baru-9876543210']);

    expect(isiLog())->not->toContain('rahasia-webhook-awal')
        ->and(isiLog())->not->toContain('rahasia-webhook-baru');
});

it('tidak mendaftarkan rute penyaji berkas disk privat', function () {
    // Every download goes through a controller and its policy.
    expect(Route::has('storage.local'))->toBeFalse();
});

it('memaku opsi dompdf yang membuka akses jarak jauh dan PHP', function () {
    expect(config('dompdf.options.enable_remote'))->toBeFalse()
        ->and(config('dompdf.options.enable_php'))->toBeFalse()
        ->and(config('dompdf.options.enable_javascript'))->toBeFalse()
        // Pinned without wiping the package's other options.
        ->and(config('dompdf.options.font_dir'))->not->toBeNull();
});
