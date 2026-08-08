<?php

declare(strict_types=1);

namespace App\Traits;

use App\Jobs\RecordActivityLogJob;
use App\Models\System\LogAktivitas;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Request;

/**
 * Queued audit trail for models that mutate academic or financial records.
 *
 * Grades, KRS approvals and status changes are events, not values: they are
 * never silently overwritten, and every write leaves a trace naming the actor.
 * Writing the trail is queued so an audited save costs the request nothing.
 *
 * Models opt in by using the trait and may narrow what is captured with:
 *
 *   protected array $logExcept = ['remember_token'];
 *   protected array $logOnly = ['status', 'nilai_akhir'];
 *
 * @mixin Model
 */
trait HasLogAktivitas
{
    public static function bootHasLogAktivitas(): void
    {
        static::created(fn (Model $model) => $model->recordActivity('created'));
        static::updated(fn (Model $model) => $model->recordActivity('updated'));
        static::deleted(fn (Model $model) => $model->recordActivity(
            method_exists($model, 'isForceDeleting') && $model->isForceDeleting() ? 'force_deleted' : 'deleted'
        ));

        if (method_exists(static::class, 'restored')) {
            static::restored(fn (Model $model) => $model->recordActivity('restored'));
        }
    }

    /** @return MorphMany<LogAktivitas, static> */
    public function logAktivitas(): MorphMany
    {
        return $this->morphMany(LogAktivitas::class, 'subject')->latest();
    }

    public function recordActivity(string $event, ?string $description = null): void
    {
        $changes = $this->activityChanges($event);

        // An update that touched nothing auditable is not worth a row.
        if ($event === 'updated' && $changes === []) {
            return;
        }

        RecordActivityLogJob::dispatch([
            'subject_type' => $this->getMorphClass(),
            'subject_id' => $this->getKey(),
            'subject_label' => $this->activityLabel(),
            'event' => $event,
            'description' => $description,
            'changes' => $changes,
            'causer_type' => $this->activityCauser()?->getMorphClass(),
            // getKey(), not getAuthIdentifier(): causer_type + causer_id is a
            // polymorphic relation, and morphTo resolves by primary key. The
            // two used to be the same value; since the auth identifier became
            // the UUID (see AuthenticatesWithUuid) they differ, and using the
            // auth identifier here would write a causer that resolves to
            // nobody — an audit trail that names no one.
            'causer_id' => $this->activityCauser()?->getKey(),
            'causer_name' => $this->activityCauserName(),
            'ip_address' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 500),
        ]);
    }

    /**
     * Old/new pairs for the attributes this model wants audited.
     *
     * @return array<string, array{old: mixed, new: mixed}>
     */
    protected function activityChanges(string $event): array
    {
        $attributes = match ($event) {
            'created' => $this->getAttributes(),
            'updated' => $this->getChanges(),
            default => [],
        };

        $only = property_exists($this, 'logOnly') ? $this->logOnly : [];
        $except = array_merge(
            ['id', 'uuid', 'created_at', 'updated_at', 'password', 'remember_token'],
            property_exists($this, 'logExcept') ? $this->logExcept : [],
        );

        $changes = [];

        foreach ($attributes as $key => $value) {
            if (in_array($key, $except, true)) {
                continue;
            }

            if ($only !== [] && !in_array($key, $only, true)) {
                continue;
            }

            $changes[$key] = [
                'old' => $event === 'created' ? null : ($this->getOriginal($key) ?? null),
                'new' => $value,
            ];
        }

        return $changes;
    }

    /** Human-readable identifier of the audited row, resolved at write time. */
    protected function activityLabel(): ?string
    {
        foreach (['nama', 'name', 'judul', 'kode', 'nomor', 'nim', 'nidn'] as $attribute) {
            if (filled($this->getAttribute($attribute))) {
                return (string) $this->getAttribute($attribute);
            }
        }

        return null;
    }

    protected function activityCauser(): ?Authenticatable
    {
        foreach (array_keys(config('auth.guards')) as $guard) {
            if (config("auth.guards.{$guard}.driver") !== 'session') {
                continue;
            }

            if (auth()->guard($guard)->check()) {
                return auth()->guard($guard)->user();
            }
        }

        return null;
    }

    protected function activityCauserName(): ?string
    {
        $causer = $this->activityCauser();

        return $causer?->getAttribute('nama') ?? $causer?->getAttribute('name');
    }
}
