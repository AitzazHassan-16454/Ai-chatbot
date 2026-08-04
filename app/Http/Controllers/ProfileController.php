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
                'username' => $user->username,
                'email' => $user->email,
                'has_avatar' => (bool) $user->avatar_path,
                'avatar_url' => $user->avatar_path ? '/profile/avatar?v=' . $user->updated_at->timestamp : null,
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

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'nullable|string|max:50|regex:/^[a-zA-Z0-9_.]+$/|unique:users,username,' . $user->id,
        ]);

        $user->update([
            'name' => $validated['name'],
            'username' => $validated['username'] ?: null,
        ]);

        return response()->json([
            'success' => true,
            'user' => [
                'name' => $user->name,
                'username' => $user->username,
            ],
        ]);
    }

    public function updateAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,webp,gif|max:2048',
        ]);

        $user = $request->user();

        if ($user->avatar_path && Storage::disk('local')->exists($user->avatar_path)) {
            Storage::disk('local')->delete($user->avatar_path);
        }

        $file = $request->file('avatar');
        $path = $file->storeAs('avatars/' . $user->id, 'avatar.' . $file->getClientOriginalExtension(), 'local');

        $user->update(['avatar_path' => $path]);

        return response()->json([
            'success' => true,
            'has_avatar' => true,
            'avatar_url' => '/profile/avatar?v=' . now()->timestamp,
        ]);
    }

    public function removeAvatar(Request $request)
    {
        $user = $request->user();

        if ($user->avatar_path && Storage::disk('local')->exists($user->avatar_path)) {
            Storage::disk('local')->delete($user->avatar_path);
        }

        $user->update(['avatar_path' => null]);

        return response()->json(['success' => true]);
    }

    public function avatar(Request $request)
    {
        $user = $request->user();

        if (!$user->avatar_path || !Storage::disk('local')->exists($user->avatar_path)) {
            abort(404);
        }

        return response()->file(Storage::disk('local')->path($user->avatar_path), [
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
