<?php

namespace App\Http\Requests\Admin;

use App\Models\DrugSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DrugScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $row = $this->route('drugSchedule');
        $id  = $row instanceof DrugSchedule ? $row->id : null;

        return [
            'code' => [
                'required', 'string', 'max:16',
                Rule::unique('drug_schedules', 'code')
                    ->whereNull('deleted_at')
                    ->ignore($id),
            ],
            'name'         => ['required', 'string', 'max:191'],
            'description'  => ['nullable', 'string', 'max:1000'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'is_active'    => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, mixed> */
    public function persistedAttributes(): array
    {
        $data = $this->validated();
        $data['code']         = strtoupper(trim($data['code']));
        $data['country_code'] = isset($data['country_code']) ? strtoupper($data['country_code']) : null;
        $data['is_active']    = $this->boolean('is_active', true);
        return $data;
    }
}
