<?php

namespace App\Http\Requests\Admin;

use App\Models\ScheduledReport;
use App\Support\ReportRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreScheduledReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'report_key'         => ['required', 'string', Rule::in(ReportRegistry::keys())],
            'name'               => ['required', 'string', 'max:120'],
            'parameters'         => ['nullable', 'array'],
            'frequency'          => ['required', Rule::in(ScheduledReport::FREQUENCIES)],
            'day_of_week'        => ['nullable', 'integer', 'between:1,7', 'required_if:frequency,weekly'],
            'day_of_month'       => ['nullable', 'integer', 'between:1,31', 'required_if:frequency,monthly'],
            'time_of_day'        => ['required', 'date_format:H:i'],
            'format'             => ['required', Rule::in(['csv', 'xlsx', 'pdf'])],
            'recipients_email'   => ['required', 'array', 'min:1'],
            'recipients_email.*' => ['email', 'max:190'],
            'subject_template'   => ['nullable', 'string', 'max:200'],
            'message_template'   => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'recipients_email.required' => __('reports.schedule.errors.recipients_required'),
            'recipients_email.min'      => __('reports.schedule.errors.recipients_required'),
            'recipients_email.*.email'  => __('reports.schedule.errors.recipient_invalid'),
        ];
    }
}
