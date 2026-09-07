<?php

namespace App\Http\Requests\Admin;

use App\Models\Account;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates POST /admin/accounting/chart-of-accounts (create) and
 * PATCH …/{account}. Codes are short numeric-ish identifiers, unique across
 * the chart; the `type` is not accepted from the client — it always follows
 * the chosen group (see {@see \App\Actions\Accounting\UpdateAccount}).
 */
class AccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('code')) {
            $this->merge(['code' => trim((string) $this->input('code'))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $row = $this->route('account');
        $id  = $row instanceof Account ? $row->id : null;

        return [
            'code' => [
                'required', 'string', 'max:32', 'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('accounts', 'code')->ignore($id),
            ],
            'name'             => ['required', 'string', 'max:255'],
            'account_group_id' => ['required', 'integer', Rule::exists('account_groups', 'id')],
            'is_active'        => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'code.regex' => __('accounting.chart.errors.code_format'),
        ];
    }

    /** @return array<string, mixed> */
    public function persistedAttributes(): array
    {
        $data = $this->validated();
        $data['is_active'] = $this->boolean('is_active', true);

        return $data;
    }
}
