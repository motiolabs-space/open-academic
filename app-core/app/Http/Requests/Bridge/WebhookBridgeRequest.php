<?php

declare(strict_types=1);

namespace App\Http\Requests\Bridge;

use App\DTOs\Bridge\WebhookData;
use App\Models\Bridge\BridgeConsumer;
use App\Rules\UriCallbackAman;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WebhookBridgeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('kelolaKredensial', BridgeConsumer::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'webhook_url' => ['nullable', 'string', 'max:255', new UriCallbackAman],
            'webhook_events' => ['nullable', 'array'],
            'webhook_events.*' => ['string', Rule::in((array) config('bridge.events'))],
        ];
    }

    public function toDto(): WebhookData
    {
        return WebhookData::fromRequest($this);
    }
}
