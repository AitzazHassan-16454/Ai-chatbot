<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Transcription;

class SttController extends Controller
{
    // The largest audio file we accept (20 MB), and the formats we accept.
    private const MAX_AUDIO_SIZE = 20 * 1024 * 1024;

    private const ALLOWED_MIME_TYPES = [
        'audio/wav', 'audio/x-wav', 'audio/wave', 'audio/mp3', 'audio/mpeg',
        'audio/aac', 'audio/aiff', 'audio/x-aiff', 'audio/ogg', 'audio/flac',
        'audio/mp4', 'audio/m4a', 'audio/webm',
    ];

    /** Turns an uploaded audio file into text (speech-to-text). */
    public function transcribe(Request $request): JsonResponse
    {
        $request->validate([
            'audio' => ['required', 'file', 'mimes:wav,mp3,mpeg,aac,aiff,ogg,oga,flac,mp4,m4a,webm', 'max:20480'],
        ]);

        $file = $request->file('audio');

        // Reject files that are too big or in an unsupported format.
        if ($file->getSize() > self::MAX_AUDIO_SIZE) {
            return response()->json(['error' => 'Audio is too large. Max 20 MB.'], 422);
        }

        if (! in_array($file->getClientMimeType(), self::ALLOWED_MIME_TYPES, true)) {
            return response()->json(['error' => 'Unsupported audio format.'], 422);
        }

        try {
            // Ask the AI to transcribe the audio.
            $transcription = Transcription::fromUpload($file)
                ->timeout(60)
                ->generate('gemini');

            $text = trim((string) $transcription);

            if ($text === '') {
                return response()->json(['error' => 'No speech detected in the audio. Please try again.'], 422);
            }

            return response()->json(['text' => $text]);
        } catch (\Throwable $e) {
            Log::error('STT failed: '.$e->getMessage());

            return response()->json(['error' => 'Could not transcribe audio. Try again.'], 500);
        }
    }
}
