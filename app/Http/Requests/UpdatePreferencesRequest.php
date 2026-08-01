<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'job_keywords' => ['nullable', 'string', 'max:255'],
            'job_location' => ['nullable', 'string', 'max:255'],
            'job_type' => ['nullable', 'string', 'in:full_time,part_time,contract,internship'],
            'job_remote' => ['nullable', 'string', 'in:remote,onsite,hybrid,any'],
            'job_experience' => ['nullable', 'string', 'in:entry,mid,senior,executive'],
            'job_salary_min' => ['nullable', 'integer', 'min:0'],
            'job_salary_max' => ['nullable', 'integer', 'min:0'],
            'job_alerts_enabled' => ['required', 'boolean'],
            'job_alert_frequency' => ['nullable', 'string', 'in:daily,weekly'],
        ];
    }
}
