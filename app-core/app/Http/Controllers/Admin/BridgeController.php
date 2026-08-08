<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\WebhookDeliveryStatus;
use App\Http\Controllers\Controller;
use App\Jobs\PublishBridgeEventJob;
use App\Models\Bridge\BridgeApiRequest;
use App\Models\Bridge\BridgeConsumer;
use App\Models\Bridge\BridgeWebhookDelivery;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The Campus Bridge console.
 *
 * Answers what an administrator actually needs to know about an integration:
 * which applications are connected, what each may read, and whether the events
 * they depend on are arriving.
 */
class BridgeController extends Controller
{
    public function index(): View
    {
        $this->authorizeBridge('bridge.view');

        return view('admin.bridge', [
            'judul' => 'Campus Bridge',
            'konteks' => 'Kontrak integrasi dengan sistem lain',
            'breadcrumb' => ['Dasbor' => route('admin.dashboard'), 'Campus Bridge'],
            'konsumen' => $this->konsumen(),
            'pengiriman' => BridgeWebhookDelivery::with('consumer')->latest('id')->limit(30)->get(),
            'penggunaan' => $this->penggunaanHarian(),
            'scopeTersedia' => config('bridge.scopes'),
            'eventTersedia' => config('bridge.events'),
        ]);
    }

    /** Replays a delivery an operator judged worth another attempt. */
    public function kirimUlang(BridgeWebhookDelivery $pengiriman): RedirectResponse
    {
        $this->authorizeBridge('bridge.manage');

        $pengiriman->update([
            'status' => WebhookDeliveryStatus::Pending,
            'attempts' => 0,
            'next_attempt_at' => null,
        ]);

        PublishBridgeEventJob::dispatch($pengiriman->id);

        return back()->with('sukses', 'Pengiriman dijadwalkan ulang.');
    }

    /** @return Collection<int, array<string, mixed>> */
    private function konsumen(): Collection
    {
        return BridgeConsumer::withCount('apiRequests')->get()->map(fn (BridgeConsumer $c): array => [
            'consumer' => $c,
            'token_aktif' => $c->tokens()->count(),
            'panggilan_7_hari' => BridgeApiRequest::where('bridge_consumer_id', $c->id)
                ->where('created_at', '>=', now()->subWeek())
                ->count(),
            'gagal_kirim' => BridgeWebhookDelivery::where('bridge_consumer_id', $c->id)
                ->whereIn('status', [WebhookDeliveryStatus::Failed->value, WebhookDeliveryStatus::Exhausted->value])
                ->count(),
        ]);
    }

    /**
     * API calls per day for the last two weeks.
     *
     * Days with no traffic are filled with zero so the chart shows a gap where
     * a gap happened, rather than silently compressing the timeline.
     *
     * @return Collection<int, array{tanggal: string, jumlah: int}>
     */
    private function penggunaanHarian(): Collection
    {
        $mentah = BridgeApiRequest::query()
            ->where('created_at', '>=', now()->subDays(13)->startOfDay())
            ->get()
            ->groupBy(fn (BridgeApiRequest $r): string => $r->created_at->toDateString())
            ->map(fn (Collection $baris): int => $baris->count());

        return collect(range(13, 0))->map(function (int $mundur) use ($mentah): array {
            $tanggal = Carbon::today()->subDays($mundur)->toDateString();

            return ['tanggal' => $tanggal, 'jumlah' => (int) ($mentah[$tanggal] ?? 0)];
        });
    }

    private function authorizeBridge(string $permission): void
    {
        abort_unless(Portal::user()?->hasPermissionTo($permission, 'staff') ?? false, 403);
    }
}
