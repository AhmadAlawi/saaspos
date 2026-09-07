<?php

namespace App\Http\Requests\Admin;

use App\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $expense = $this->route('expense');

        return $expense instanceof Expense
            ? ($this->user()?->can('update', $expense) ?? false)
            : ($this->user()?->can('create', Expense::class) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'expense_date'      => ['required', 'date', 'before_or_equal:today'],
            'category_id'       => ['nullable', 'integer', Rule::exists('expense_categories', 'id')],
            'amount'            => ['required', 'numeric', 'gt:0'],
            'tax_amount'        => ['nullable', 'numeric', 'gte:0'],
            'payment_method_id' => ['nullable', 'integer', Rule::exists('payment_methods', 'id')],
            'supplier_id'       => ['nullable', 'integer', Rule::exists('suppliers', 'id')->whereNull('deleted_at')],
            'reference'         => ['nullable', 'string', 'max:191'],
            'description'       => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'expense_date' => __('expenses.fields.date'),
            'amount'       => __('expenses.fields.amount'),
            'category_id'  => __('expenses.fields.category'),
        ];
    }

    /** Shape the validated payload for the create/update actions. */
    public function expenseData(): array
    {
        return [
            'expense_date'      => $this->input('expense_date'),
            'category_id'       => $this->integer('category_id') ?: null,
            'amount'            => $this->input('amount'),
            'tax_amount'        => $this->input('tax_amount') !== null && $this->input('tax_amount') !== ''
                                    ? $this->input('tax_amount')
                                    : '0',
            'payment_method_id' => $this->integer('payment_method_id') ?: null,
            'supplier_id'       => $this->integer('supplier_id') ?: null,
            'reference'         => $this->input('reference'),
            'description'       => $this->input('description'),
        ];
    }
}
