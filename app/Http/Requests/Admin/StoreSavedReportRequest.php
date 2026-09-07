<?php

namespace App\Http\Requests\Admin;

use App\Support\ReportRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSavedReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'report_key'  => ['required', 'string', Rule::in(ReportRegistry::keys())],
            'name'        => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_shared'   => ['boolean'],
            'parameters'  => ['nullable', 'array'],
        ];
    }
}
