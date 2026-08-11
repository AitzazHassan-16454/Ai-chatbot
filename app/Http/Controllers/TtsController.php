<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Audio;

class TtsController extends Controller
{
    // The longest text we will turn into speech.
    private const MAX_TEXT_LENGTH = 5000;

    /** Turns text into an audio file (text-to-speech). */
    public function synthesize(Request $request): Response|JsonResponse
    {
        $text = trim((string) $request->input('text'));

        // Basic checks on the text.
        if ($text === '') {
            return response()->json(['error' => 'Text is required.'], 422);
        }

        if (mb_strlen($text) > self::MAX_TEXT_LENGTH) {
            return response()->json(['error' => 'Text is too long. Max '.self::MAX_TEXT_LENGTH.' characters.'], 422);
        }

        // Choose the voice: only 'male' or 'female' are allowed, default is female.
        $voice = in_array($request->input('voice'), ['male', 'female'], true)
            ? $request->input('voice')
            : 'female';

        // Remove markdown symbols so the AI doesn't read them out loud.
        $speechText = $this->sanitizeForSpeech($text);

        if ($speechText === '') {
            return response()->json(['error' => 'Nothing to speak.'], 422);
        }

        // Reuse the same audio for the same text (cache it on disk).
        $cacheKey = 'tts/'.$voice.'/'.hash('sha256', $speechText).'.wav';

        try {
            if (! Storage::disk('local')->exists($cacheKey)) {
                $audio = Audio::of($speechText)
                    ->voice($voice === 'male' ? 'default-male' : 'default-female')
                    ->timeout(60)
                    ->generate('gemini');

                Storage::disk('local')->put($cacheKey, $audio->content(), 'private');
            }

            // Return the saved audio file to the browser.
            return response(Storage::disk('local')->get($cacheKey), 200, [
                'Content-Type' => 'audio/wav',
                'Cache-Control' => 'private, max-age=31536000, immutable',
            ]);
        } catch (\Throwable $e) {
            Log::error('TTS failed: '.$e->getMessage());

            return response()->json(['error' => 'Could not generate speech. Try again.'], 500);
        }
    }

    /** Removes markdown formatting so the speech only contains words. */
    private function sanitizeForSpeech(string $text): string
    {
        return trim(preg_replace('/\s+/', ' ', preg_replace([
            '/```[\s\S]*?```/',           // code blocks
            '/`[^`]*`/',                  // inline code
            '/\[([^\]]+)\]\([^)]+\)/',    // markdown links
            '/#{1,6}\s*/',                // headings
            '/^>+\s*/m',                  // blockquote markers
            '/^[-+*]\s*/m',               // list bullets
            '/^\d+\.\s*/m',               // ordered list numbers
            '/[*_~]/',                    // emphasis markers
        ], ' ', $text)) ?? '');
    }
}
