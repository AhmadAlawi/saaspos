<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReceiptTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name'       => ['required', 'string', 'max:150'],
            'paper_size' => ['required', Rule::in(['58mm', '80mm', 'a4'])],
            'is_active'  => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, mixed> */
    public function persistedAttributes(): array
    {
        return [
            'name'       => trim((string) $this->input('name')),
            'paper_size' => (string) $this->input('paper_size'),
            'is_active'  => $this->boolean('is_active', true),
        ];
    }
}
