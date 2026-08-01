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
    private const MAX_TEXT_LENGTH = 5000;

    public function synthesize(Request $request): Response|JsonResponse
    {
        $text = trim((string) $request->input('text'));

        if ($text === '') {
            return response()->json(['error' => 'Text is required.'], 422);
        }

        if (mb_strlen($text) > self::MAX_TEXT_LENGTH) {
            return response()->json(['error' => 'Text is too long. Max '.self::MAX_TEXT_LENGTH.' characters.'], 422);
        }

        $voice = in_array($request->input('voice'), ['male', 'female'], true)
            ? $request->input('voice')
            : 'female';

        $speechText = $this->sanitizeForSpeech($text);

        if ($speechText === '') {
            return response()->json(['error' => 'Nothing to speak.'], 422);
        }

        $cacheKey = 'tts/'.$voice.'/'.hash('sha256', $speechText).'.wav';

        try {
            if (! Storage::disk('local')->exists($cacheKey)) {
                $audio = Audio::of($speechText)
                    ->voice($voice === 'male' ? 'default-male' : 'default-female')
                    ->timeout(60)
                    ->generate('gemini');

                Storage::disk('local')->put($cacheKey, $audio->content(), 'private');
            }

            return response(Storage::disk('local')->get($cacheKey), 200, [
                'Content-Type' => 'audio/wav',
                'Cache-Control' => 'private, max-age=31536000, immutable',
            ]);
        } catch (\Throwable $e) {
            Log::error('TTS failed: '.$e->getMessage());

            return response()->json(['error' => 'Could not generate speech. Try again.'], 500);
        }
    }

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
