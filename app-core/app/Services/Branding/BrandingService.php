<?php

declare(strict_types=1);

namespace App\Services\Branding;

use App\Models\System\Setting;

/**
 * Resolves the institution's white-label identity.
 *
 * Settings written from the admin UI win over the .env defaults, so a campus
 * can rebrand its installation without a deploy. Values are cached by the
 * Setting model, so calling this per request is cheap.
 */
class BrandingService
{
    private const GROUP = 'branding';

    public function institutionName(): string
    {
        return $this->value('institution_name', config('branding.institution.name'));
    }

    public function institutionShortName(): string
    {
        return $this->value('institution_short', config('branding.institution.short_name'));
    }

    /** PDDIKTI institution code, carried by every Feeder payload. */
    public function institutionCode(): string
    {
        return $this->value('institution_code', config('branding.institution.code'));
    }

    public function primaryColor(): string
    {
        return $this->value('primary_color', config('branding.colors.primary'));
    }

    public function accentColor(): string
    {
        return $this->value('accent_color', config('branding.colors.accent'));
    }

    public function logoUrl(): ?string
    {
        $path = $this->value('logo_path', config('branding.logo_path'));

        return $path === '' || $path === null ? null : asset('storage/'.$path);
    }

    /** Letter shown in the gold-bordered box when no logo is uploaded. */
    public function logoInitial(): string
    {
        return mb_strtoupper(mb_substr($this->institutionShortName(), 0, 1));
    }

    /**
     * CSS custom properties injected into the page so a tenant colour override
     * reaches the stylesheet without recompiling Tailwind.
     */
    public function cssVariables(): string
    {
        return sprintf(
            '--color-navy:%s;--color-gold:%s;',
            $this->primaryColor(),
            $this->accentColor(),
        );
    }

    private function value(string $key, ?string $fallback): string
    {
        $value = Setting::get(self::GROUP, $key);

        return is_string($value) && $value !== '' ? $value : (string) $fallback;
    }
}
