<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePreferencesRequest;
use App\Services\CvParserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ProfileController extends Controller
{
    public function show()
    {
        $user = request()->user();
        return Inertia::render('Profile', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'cv_name' => $user->cv_name,
                'job_keywords' => $user->job_keywords,
                'job_location' => $user->job_location,
                'job_type' => $user->job_type,
                'job_remote' => $user->job_remote,
                'job_experience' => $user->job_experience,
                'job_salary_min' => $user->job_salary_min,
                'job_salary_max' => $user->job_salary_max,
                'job_alerts_enabled' => $user->job_alerts_enabled,
                'job_alert_frequency' => $user->job_alert_frequency,
            ],
        ]);
    }

    public function uploadCv(Request $request)
    {
        $request->validate([
            'cv' => 'required|file|mimes:pdf|max:10240',
        ]);

        $user = $request->user();

        if ($user->cv_path && Storage::disk('local')->exists($user->cv_path)) {
            Storage::disk('local')->delete($user->cv_path);
        }

        $file = $request->file('cv');
        $path = $file->store('cvs/' . $user->id, 'local');

        $user->update([
            'cv_path' => $path,
            'cv_name' => $file->getClientOriginalName(),
        ]);

        $parser = app(CvParserService::class);
        $fullPath = Storage::disk('local')->path($path);
        $parsed = $parser->extractFromPdf($fullPath);

        if (isset($parsed['error'])) {
            return response()->json([
                'success' => true,
                'cv_name' => $file->getClientOriginalName(),
                'parsed' => null,
                'parse_error' => $parsed['error'],
            ]);
        }

        $searchQuery = $parser->buildSearchQuery($parsed);

        $user->update([
            'job_keywords' => $user->job_keywords ?? $searchQuery,
            'job_location' => $user->job_location ?? ($parsed['preferred_location'] ?? null),
            'job_salary_min' => $user->job_salary_min ?? $parsed['salary_min'] ?? null,
            'job_salary_max' => $user->job_salary_max ?? $parsed['salary_max'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'cv_name' => $file->getClientOriginalName(),
            'parsed' => $parsed,
            'search_query' => $searchQuery,
        ]);
    }

    public function removeCv(Request $request)
    {
        $user = $request->user();

        if ($user->cv_path && Storage::disk('local')->exists($user->cv_path)) {
            Storage::disk('local')->delete($user->cv_path);
        }

        $user->update([
            'cv_path' => null,
            'cv_name' => null,
        ]);

        return response()->json(['success' => true]);
    }

    public function updatePreferences(UpdatePreferencesRequest $request)
    {
        $request->user()->update($request->validated());

        return response()->json(['success' => true]);
    }
}
