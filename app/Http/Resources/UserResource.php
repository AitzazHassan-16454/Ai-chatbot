<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'tokens_used' => $this->tokens_used,
            'tokens_limit' => $this->tokens_limit,
            'created_at' => $this->created_at,
            'cv_name' => $this->cv_name,
            'job_keywords' => $this->job_keywords,
            'job_location' => $this->job_location,
            'job_type' => $this->job_type,
            'job_remote' => $this->job_remote,
            'job_experience' => $this->job_experience,
            'job_salary_min' => $this->job_salary_min,
            'job_salary_max' => $this->job_salary_max,
            'job_alerts_enabled' => $this->job_alerts_enabled,
            'job_alert_frequency' => $this->job_alert_frequency,
        ];
    }
}
