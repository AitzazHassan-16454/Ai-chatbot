<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use App\Services\N8nService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FeedbackController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'email' => 'nullable|email|max:255',
            'category' => 'required|string|in:general,bug,feature,other',
            'message' => 'required|string|min:10|max:2000',
            'rating' => 'nullable|integer|between:1,5',
            'page_context' => 'nullable|string|max:100',
        ]);

        $user = $request->user();

        $feedback = Feedback::create([
            'user_id' => $user?->id,
            'email' => $validated['email'] ?? $user?->email,
            'category' => $validated['category'],
            'message' => $validated['message'],
            'rating' => $validated['rating'] ?? null,
            'page_context' => $validated['page_context'] ?? url()->current(),
        ]);

        $n8n = app(N8nService::class);
        
        $n8n->notifyFeedback([
            'feedback_id' => $feedback->id,
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'Guest',
            'user_email' => $feedback->email,
            'category' => $feedback->category,
            'message' => Str::limit($feedback->message, 500),
            'rating' => $feedback->rating,
            'page_context' => $feedback->page_context,
        ]);

        return response()->json(['success' => true, 'message' => 'Thank you for your feedback!']);
    }
}
