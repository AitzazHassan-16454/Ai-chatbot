<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Searches for jobs using the SerpAPI "Google Jobs" engine.
 * Returns a list of jobs, or ['error' => ...] when the search fails.
 */
class SerpApiService
{
    // Turns our friendly filter names into the values Google Jobs expects.
    private const REMOTE_MAP = [
        'remote' => 'remote',
        'onsite' => 'on-site',
        'hybrid' => 'hybrid',
    ];

    private const JOB_TYPE_MAP = [
        'full_time' => 'FULLTIME',
        'part_time' => 'PARTTIME',
        'contract' => 'CONTRACTOR',
        'internship' => 'INTERN',
    ];

    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.serpapi.api_key', '');
    }

    /** Searches Google Jobs for matching job listings. */
    public function searchLinkedinJobs(string $keywords, string $location, array $filters = []): array
    {
        if (empty($this->apiKey)) {
            return ['error' => 'SerpAPI key not configured.'];
        }

        // Build the search query, adding the user's filters as words.
        $query = $keywords;

        if (! empty($filters['remote']) && $filters['remote'] !== 'any') {
            $query .= ' '.(self::REMOTE_MAP[$filters['remote']] ?? '');
        }

        if (! empty($location)) {
            $query .= ' in '.$location;
        }

        $params = [
            'engine' => 'google_jobs',
            'q' => trim($query),
            'api_key' => $this->apiKey,
            'hl' => 'en',
        ];

        if (! empty($filters['job_type'])) {
            $employmentType = self::JOB_TYPE_MAP[$filters['job_type']] ?? null;

            if ($employmentType) {
                $params['employment_type'] = $employmentType;
            }
        }

        try {
            $response = Http::timeout(15)->get('https://serpapi.com/search.json', $params);

            if (! $response->successful()) {
                Log::error('SerpAPI error: '.$response->body());

                return ['error' => 'Job search failed.'];
            }

            // Convert the raw API jobs into the simple format our app uses.
            $jobs = $response->json('jobs_results', []);

            return array_map(fn ($job) => $this->formatJob($job), $jobs);
        } catch (\Throwable $e) {
            Log::error('SerpAPI search failed: '.$e->getMessage());

            return ['error' => 'Job search failed.'];
        }
    }

    /** Picks out just the fields we care about from one job result. */
    private function formatJob(array $job): array
    {
        $url = $job['share_link'] ?? ($job['related_links'][0]['link'] ?? '');

        // Keep the URL short so it fits in the database and emails.
        if (strlen($url) > 2000) {
            $url = substr($url, 0, 2000);
        }

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
    }
}
