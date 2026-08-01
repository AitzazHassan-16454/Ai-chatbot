<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SerpApiService
{
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.serpapi.api_key', '');
    }

    public function searchLinkedinJobs(string $keywords, string $location, array $filters = []): array
    {
        if (empty($this->apiKey)) {
            return ['error' => 'SerpAPI key not configured.'];
        }

        $query = $keywords;

        if (!empty($filters['remote']) && $filters['remote'] !== 'any') {
            $remoteMap = [
                'remote' => 'remote',
                'onsite' => 'on-site',
                'hybrid' => 'hybrid',
            ];
            $query .= ' ' . ($remoteMap[$filters['remote']] ?? '');
        }

        if (!empty($location)) {
            $query .= ' in ' . $location;
        }

        $params = [
            'engine' => 'google_jobs',
            'q' => trim($query),
            'api_key' => $this->apiKey,
            'hl' => 'en',
        ];

        if (!empty($filters['job_type'])) {
            $typeMap = [
                'full_time'  => 'FULLTIME',
                'part_time'  => 'PARTTIME',
                'contract'   => 'CONTRACTOR',
                'internship' => 'INTERN',
            ];
            $employmentType = $typeMap[$filters['job_type']] ?? null;
            if ($employmentType) {
                $params['employment_type'] = $employmentType;
            }
        }

        try {
            $response = Http::timeout(15)->get('https://serpapi.com/search.json', $params);

            if (!$response->successful()) {
                Log::error('SerpAPI error: ' . $response->body());
                return ['error' => 'Job search failed.'];
            }

            $data = $response->json();
            $jobs = $data['jobs_results'] ?? [];

            return array_map(function ($job) {
                $url = $job['share_link'] ?? ($job['related_links'][0]['link'] ?? '');
                if (strlen($url) > 2000) $url = substr($url, 0, 2000);

                return [
                    'title' => $job['title'] ?? '',
                    'company' => $job['company_name'] ?? '',
                    'location' => $job['location'] ?? '',
                    'url' => $url,
                    'salary_text' => $job['detected_extensions']['salary'] ?? null,
                    'posted_at' => $job['detected_extensions']['posted_at'] ?? null,
                    'source' => $job['via'] ?? 'Google Jobs',
                    'description' => substr($job['description'] ?? '', 0, 300),
                ];
            }, $jobs);
        } catch (\Throwable $e) {
            Log::error('SerpAPI search failed: ' . $e->getMessage());
            return ['error' => 'Job search failed.'];
        }
    }
}
