<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreManualJournalEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'entry_date'          => ['required', 'date'],
            'store_id'            => ['nullable', 'integer', 'exists:stores,id'],
            'description'         => ['nullable', 'string', 'max:255'],
            'lines'               => ['required', 'array', 'min:2'],
            'lines.*.account_id'  => ['required', 'integer', 'exists:accounts,id'],
            'lines.*.debit'       => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit'      => ['nullable', 'numeric', 'min:0'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $lines    = (array) $this->input('lines', []);
            $totalDr  = '0';
            $totalCr  = '0';

            foreach ($lines as $i => $line) {
                $debit  = (string) ($line['debit'] ?? '0') ?: '0';
                $credit = (string) ($line['credit'] ?? '0') ?: '0';

                // Each line must be exactly one of debit-only / credit-only.
                if ((bccomp($debit, '0', 4) > 0) === (bccomp($credit, '0', 4) > 0)) {
                    $v->errors()->add("lines.$i", __('accounting.journal.errors.line_one_side'));
                }

                $totalDr = bcadd($totalDr, $debit, 4);
                $totalCr = bcadd($totalCr, $credit, 4);
            }

            if (bccomp($totalDr, '0', 4) <= 0) {
                $v->errors()->add('lines', __('accounting.journal.errors.empty'));
            } elseif (bccomp($totalDr, $totalCr, 4) !== 0) {
                $v->errors()->add('lines', __('accounting.journal.errors.unbalanced', [
                    'debit'  => $totalDr,
                    'credit' => $totalCr,
                ]));
            }
        });
    }
}
