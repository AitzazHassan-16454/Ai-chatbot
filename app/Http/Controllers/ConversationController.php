<?php

namespace App\Http\Controllers;

use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function index(Request $request)
    {
        $conversations = Conversation::where('user_id', $request->user()->id)
            ->withCount('messages')
            ->orderByDesc('updated_at')
            ->paginate(50);

        return ConversationResource::collection($conversations);
    }

    public function show(Request $request, Conversation $conversation)
    {
        if ($conversation->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $conversation->load('messages');

        return response()->json([
            'data' => new ConversationResource($conversation->loadCount('messages')),
            'messages' => MessageResource::collection($conversation->messages),
        ]);
    }

    public function destroy(Request $request, Conversation $conversation)
    {
        if ($conversation->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $conversation->delete();

        return response()->json(['ok' => true]);
    }
}
