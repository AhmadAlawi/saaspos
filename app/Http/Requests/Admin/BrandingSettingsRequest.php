<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates PATCH /admin/settings/branding.
 */
class BrandingSettingsRequest extends FormRequest
{
    /** Suggested accent colors offered as one-click swatches. */
    public const ACCENT_CHOICES = [
        '#F97316', '#E11D48', '#B70137', '#7C3AED',
        '#2563EB', '#0891B2', '#059669', '#CA8A04', '#0F172A',
    ];

    /** Suggested on-accent text colors (the color sitting on filled buttons). */
    public const TEXT_CHOICES = ['#FFFFFF', '#0D0E10'];

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'app_name'              => ['nullable', 'string', 'max:100'],
            'footer_text'           => ['nullable', 'string', 'max:300'],
            'brand_color'           => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'brand_text_color'      => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'theme_default'         => ['required', Rule::in(['light', 'dark', 'auto'])],
            // App logo — light + dark variants
            'app_logo'              => ['nullable', 'file', 'mimes:png,svg,jpg,jpeg,webp', 'max:1024'],
            'app_logo_remove'       => ['sometimes', 'boolean'],
            'app_logo_dark'         => ['nullable', 'file', 'mimes:png,svg,jpg,jpeg,webp', 'max:1024'],
            'app_logo_dark_remove'  => ['sometimes', 'boolean'],
            // Collapsed / half logo — light + dark variants
            'app_logo_half'              => ['nullable', 'file', 'mimes:png,svg,jpg,jpeg,webp', 'max:512'],
            'app_logo_half_remove'       => ['sometimes', 'boolean'],
            'app_logo_half_dark'         => ['nullable', 'file', 'mimes:png,svg,jpg,jpeg,webp', 'max:512'],
            'app_logo_half_dark_remove'  => ['sometimes', 'boolean'],
            // Favicon
            'favicon'               => ['nullable', 'file', 'mimes:png,svg,ico,jpg,jpeg', 'max:512'],
            'favicon_remove'        => ['sometimes', 'boolean'],
        ];
    }
}
