<?php

namespace App\Http\Requests\Admin;

use App\Models\Shift;
use App\Models\Terminal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OpenShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Shift::class) ?? false;
    }

    /**
     * The admin open form submits `terminal_id` directly; the cashier gate binds
     * the terminal via the `pos_terminal_id` cookie and opens without the field.
     * Fall back to that cookie so both paths validate + bind the same way.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->filled('terminal_id')) {
            $cookieTerminal = current_terminal_id();
            if ($cookieTerminal) {
                $this->merge(['terminal_id' => $cookieTerminal]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $storeId = current_store_id() ?: default_store_id();

        // A terminal is REQUIRED to open a shift when the store has any active
        // terminals — that's what makes "one terminal = one open shift" real.
        // Stores with no terminals yet can still open one (terminal_id null).
        $hasTerminals = $storeId
            && Terminal::query()->where('store_id', $storeId)->where('is_active', true)->exists();

        return [
            'opening_cash'            => ['required', 'numeric', 'min:0'],
            'notes'                   => ['nullable', 'string', 'max:1000'],
            // Store-scoped: the chosen terminal must belong to the active store
            // and be active, so a stale/cross-store id can never bind.
            'terminal_id'             => [
                $hasTerminals ? 'required' : 'nullable',
                'integer',
                Rule::exists('terminals', 'id')
                    ->where('store_id', $storeId)
                    ->where('is_active', true),
            ],
            // Denomination helper (Slice C) — counts per face value.
            'opening_denominations'   => ['nullable', 'array'],
            'opening_denominations.*' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'terminal_id.required' => __('shifts.errors.terminal_required'),
            'terminal_id.exists'   => __('shifts.errors.terminal_invalid'),
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'opening_cash' => __('shifts.fields.opening_cash'),
            'notes'        => __('shifts.fields.notes'),
            'terminal_id'  => __('shifts.fields.terminal'),
        ];
    }
}
