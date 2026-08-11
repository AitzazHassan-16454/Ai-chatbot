<?php

namespace App\Http\Controllers;

use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    /** Lists all conversations belonging to the logged-in user. */
    public function index(Request $request)
    {
        $conversations = Conversation::where('user_id', $request->user()->id)
            ->withCount('messages')
            ->orderByDesc('updated_at')
            ->paginate(50);

        return ConversationResource::collection($conversations);
    }

    /** Loads one conversation (with its messages) for the logged-in user. */
    public function show(Request $request, Conversation $conversation)
    {
        // A user may only open their own conversations.
        if ($this->notOwnedBy($request, $conversation)) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $conversation->load('messages');

        return response()->json([
            'data' => new ConversationResource($conversation->loadCount('messages')),
            'messages' => MessageResource::collection($conversation->messages),
        ]);
    }

    /** Deletes one of the logged-in user's conversations. */
    public function destroy(Request $request, Conversation $conversation)
    {
        // A user may only delete their own conversations.
        if ($this->notOwnedBy($request, $conversation)) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $conversation->delete();

        return response()->json(['ok' => true]);
    }

    /** True when the conversation belongs to a different user. */
    private function notOwnedBy(Request $request, Conversation $conversation): bool
    {
        return $conversation->user_id !== $request->user()->id;
    }
}
