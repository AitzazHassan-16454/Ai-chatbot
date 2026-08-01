<?php

namespace App\Console\Commands;

use App\Models\JobListing;
use App\Models\User;
use App\Services\SerpApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;

class RunJobAlertsCommand extends Command
{
    protected $signature = 'job-alerts:run';
    protected $description = 'Search jobs and send alert emails for all opted-in users';

    public function handle(): int
    {
        $users = User::where('job_alerts_enabled', true)
            ->whereNotNull('job_keywords')
            ->get();

        if ($users->isEmpty()) {
            $this->info('No users with job alerts enabled.');
            return self::SUCCESS;
        }

        $serpApi = app(SerpApiService::class);
        $totalJobs = 0;
        $totalEmails = 0;

        foreach ($users as $user) {
            try {
                $results = $serpApi->searchLinkedinJobs(
                    $user->job_keywords,
                    $user->job_location,
                    [
                        'job_type'    => $user->job_type,
                        'remote'      => $user->job_remote,
                        'experience'  => $user->job_experience,
                    ]
                );

                if (isset($results['error']) || empty($results)) continue;

                $newJobs = [];
                foreach ($results as $job) {
                    if (empty($job['url'])) continue;

                    $existing = JobListing::where('user_id', $user->id)
                        ->where('url', $job['url'])
                        ->first();

                    if (!$existing) {
                        $listing = JobListing::create([
                            'user_id'     => $user->id,
                            'title'       => $job['title'],
                            'company'     => $job['company'],
                            'location'    => $job['location'],
                            'url'         => $job['url'],
                            'source'      => $job['source'] ?? 'Google Jobs',
                            'salary_text' => $job['salary_text'],
                            'posted_at'   => $job['posted_at'] ?? null,
                            'found_at'    => now(),
                        ]);
                        $newJobs[] = $listing;
                    }
                }

                $totalJobs += count($newJobs);

                if (!empty($newJobs)) {
                    $subject = count($newJobs) . ' New Opportunities — ' . $user->job_keywords;
                    $html = View::make('emails.job-alert', ['user' => $user, 'jobs' => collect($newJobs)])->render();

                    Mail::raw(null, function ($message) use ($user, $subject, $html) {
                        $message->to($user->email)
                            ->subject($subject)
                            ->html($html)
                            ->from(config('mail.from.address'), config('mail.from.name'));
                    });

                    JobListing::whereIn('id', array_column($newJobs, 'id'))
                        ->update(['emailed' => true]);

                    $totalEmails++;
                    $this->info("Sent {$subject} to {$user->email}");
                }
            } catch (\Throwable $e) {
                $this->error("Failed for user {$user->id}: " . $e->getMessage());
                Log::error("Job alert failed for user {$user->id}: " . $e->getMessage());
            }
        }

        $this->info("Done: {$totalJobs} jobs found, {$totalEmails} emails sent");
        Log::info("job-alerts:run completed: {$totalJobs} jobs, {$totalEmails} emails");

        return self::SUCCESS;
    }
}
