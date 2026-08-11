<?php

namespace App\Http\Controllers;

use App\Models\JobListing;
use App\Models\User;
use App\Services\SerpApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;

class JobAlertController extends Controller
{
    /** Lists the jobs that were found for the logged-in user. */
    public function index(Request $request)
    {
        $jobs = $request->user()
            ->jobListings()
            ->orderByDesc('found_at')
            ->limit(50)
            ->get();

        return response()->json(['jobs' => $jobs]);
    }

    /** Searches for jobs using the user's keywords (from their CV or settings). */
    public function search(Request $request)
    {
        $user = $request->user();

        // The user can search with any keywords, but their saved settings are the default.
        $keywords = $request->input('keywords', $user->job_keywords);
        $location = $request->input('location', $user->job_location);

        if (empty($keywords)) {
            return response()->json(['error' => 'Keywords required. Upload CV or set preferences first.'], 422);
        }

        $results = $this->performJobSearch($user, $keywords, $location, [
            'job_type' => $request->input('job_type', $user->job_type),
            'remote' => $request->input('remote', $user->job_remote),
            'experience' => $request->input('experience', $user->job_experience),
        ]);

        if (isset($results['error'])) {
            return response()->json(['error' => $results['error']], 500);
        }

        return response()->json([
            'found' => count($results),
            'new' => count($results['new_jobs'] ?? []),
            'jobs' => $results['new_jobs'] ?? [],
        ]);
    }

    /** Called by the n8n workflow to find new jobs for every user with alerts on. */
    public function searchUsers(Request $request)
    {
        if (! $this->isAuthorized($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $users = User::where('job_alerts_enabled', true)
            ->whereNotNull('job_keywords')
            ->get();

        $results = [];

        foreach ($users as $user) {
            try {
                $resultsData = $this->performJobSearch($user, $user->job_keywords, $user->job_location);

                if (! empty($resultsData['new_jobs'])) {
                    $results[] = [
                        'email' => $user->email,
                        'name' => $user->name,
                        'keywords' => $user->job_keywords,
                        'location' => $user->job_location,
                        'jobs' => $resultsData['new_jobs'],
                    ];
                }
            } catch (\Throwable $e) {
                Log::error("Job search failed for user {$user->id}: ".$e->getMessage());
            }
        }

        Log::info('n8n search-users: '.count($results).' users with new jobs');

        return response()->json(['users' => $results]);
    }

    /** Called by the n8n workflow to email a list of jobs to one user. */
    public function sendEmail(Request $request)
    {
        if (! $this->isAuthorized($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'email' => 'required|email',
            'name' => 'required|string',
            'keywords' => 'required|string',
            'location' => 'nullable|string',
            'jobs' => 'required|array|min:1',
            'jobs.*.title' => 'required|string',
            'jobs.*.company' => 'required|string',
            'jobs.*.url' => 'required|string',
            'jobs.*.location' => 'nullable|string',
            'jobs.*.salary_text' => 'nullable|string',
            'jobs.*.posted_at' => 'nullable|string',
            'jobs.*.source' => 'nullable|string',
        ]);

        // Build a simple user-like object from the webhook data.
        $user = (object) [
            'email' => $validated['email'],
            'name' => $validated['name'],
            'job_keywords' => $validated['keywords'],
            'job_location' => $validated['location'],
        ];

        $jobs = collect($validated['jobs'])->map(fn ($job) => (object) $job);
        $subject = $jobs->count().' New Opportunities — '.$validated['keywords'];
        $html = View::make('emails.job-alert', ['user' => $user, 'jobs' => $jobs])->render();

        try {
            $this->sendMail($user->email, $subject, $html);

            Log::info("n8n send-email: sent to {$user->email}");

            return response()->json(['success' => true, 'sent_to' => $user->email]);
        } catch (\Throwable $e) {
            Log::error("n8n send-email failed for {$user->email}: ".$e->getMessage());

            return response()->json(['error' => 'Failed to send email'], 500);
        }
    }

    /** Called daily by n8n: searches for every user and emails them new jobs. */
    public function dailyRun(Request $request)
    {
        if (! $this->isAuthorized($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $users = User::where('job_alerts_enabled', true)
            ->whereNotNull('job_keywords')
            ->get();

        $totalJobs = 0;
        $totalEmails = 0;

        foreach ($users as $user) {
            try {
                $results = $this->performJobSearch($user, $user->job_keywords, $user->job_location);

                $newJobs = $results['new_jobs'] ?? [];
                $totalJobs += count($newJobs);

                if (! empty($newJobs)) {
                    $this->sendJobAlertEmail($user, $newJobs);
                    $totalEmails++;

                    JobListing::whereIn('id', array_column($newJobs, 'id'))
                        ->update(['emailed' => true]);
                }
            } catch (\Throwable $e) {
                Log::error("Job alert failed for user {$user->id}: ".$e->getMessage());
            }
        }

        Log::info("Daily job alerts completed: {$totalJobs} jobs found, {$totalEmails} emails sent");

        return response()->json([
            'success' => true,
            'users_processed' => $users->count(),
            'jobs_found' => $totalJobs,
            'emails_sent' => $totalEmails,
        ]);
    }

    /** True when the request carries the correct secret API key (used by n8n). */
    private function isAuthorized(Request $request): bool
    {
        return $request->header('X-API-Key') === config('services.job_alerts.api_key');
    }

    /**
     * Searches for jobs and saves any that are new (not seen before) for the user.
     * Returns ['new_jobs' => [...]] or ['error' => ...].
     */
    private function performJobSearch(User $user, string $keywords, ?string $location, array $filters = []): array
    {
        $serpApi = app(SerpApiService::class);
        $results = $serpApi->searchLinkedinJobs($keywords, $location, $filters);

        if (isset($results['error'])) {
            return $results;
        }

        $newJobs = [];

        foreach ($results as $job) {
            if (empty($job['url'])) {
                continue;
            }

            // Skip jobs we already saved for this user.
            $existing = JobListing::where('user_id', $user->id)
                ->where('url', $job['url'])
                ->first();

            if (! $existing) {
                $listing = JobListing::create([
                    'user_id' => $user->id,
                    'title' => $job['title'],
                    'company' => $job['company'],
                    'location' => $job['location'],
                    'url' => $job['url'],
                    'source' => $job['source'] ?? 'Google Jobs',
                    'salary_text' => $job['salary_text'],
                    'posted_at' => $job['posted_at'] ?? null,
                    'found_at' => now(),
                ]);

                $newJobs[] = $listing;
            }
        }

        return ['all' => $results, 'new_jobs' => $newJobs];
    }

    /** Emails one user their new job alerts. */
    private function sendJobAlertEmail(User $user, array $jobs): void
    {
        $subject = count($jobs).' New Opportunities — '.$user->job_keywords;
        $html = View::make('emails.job-alert', ['user' => $user, 'jobs' => collect($jobs)])->render();

        try {
            $this->sendMail($user->email, $subject, $html);
        } catch (\Throwable $e) {
            Log::error("Job alert email failed for {$user->email}: ".$e->getMessage());
        }
    }

    /** Actually sends an HTML email to an address. */
    private function sendMail(string $to, string $subject, string $html): void
    {
        Mail::raw(null, function ($message) use ($to, $subject, $html) {
            $message->to($to)
                ->subject($subject)
                ->html($html)
                ->from(config('mail.from.address'), config('mail.from.name'));
        });
    }
}
