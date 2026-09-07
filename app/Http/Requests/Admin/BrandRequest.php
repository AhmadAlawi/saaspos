<?php

namespace App\Http\Requests\Admin;

use App\Models\Brand;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Validates POST /admin/brands (create) and PATCH /admin/brands/{id}.
 */
class BrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $brand = $this->route('brand');
        $id    = $brand instanceof Brand ? $brand->id : null;

        return [
            'name' => [
                'required',
                'string',
                'max:191',
                Rule::unique('brands', 'name')
                    ->whereNull('deleted_at')
                    ->ignore($id),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active'   => ['sometimes', 'boolean'],
            // Optional logo upload. Kept lax so callers that don't send
            // the field (e.g. the AJAX status toggle) still pass.
            'logo'        => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp,svg', 'max:1024'],
            'logo_remove' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Map the validated payload to the fields we persist. Slug is derived
     * from the name; callers don't think about it.
     *
     * @return array<string, mixed>
     */
    public function persistedAttributes(): array
    {
        $data = $this->validated();

        $base  = Str::slug($data['name']);
        $brand = $this->route('brand');
        $data['slug'] = $brand instanceof Brand
            ? $base.'-'.$brand->id
            : $base.'-'.Str::lower(Str::random(5));

        $data['is_active'] = $this->boolean('is_active', true);

        return $data;
    }
}
