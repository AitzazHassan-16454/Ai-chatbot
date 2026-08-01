<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser;

class CvParserService
{
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
    }

    public function extractFromPdf(string $filePath): array
    {
        $parser = new Parser();
        $pdf = $parser->parseFile($filePath);
        $text = $pdf->getText();

        if (empty(trim($text))) {
            return ['error' => 'Could not extract text from PDF.'];
        }

        return $this->analyzeWithGemini($text);
    }

    private function analyzeWithGemini(string $cvText): array
    {
        $prompt = "Analyze this resume/CV text and extract the following information in valid JSON format only, no markdown, no code blocks:

{
    \"job_titles\": [\"list of job titles this person has held or is qualified for\"],
    \"skills\": [\"list of technical and soft skills\"],
    \"experience_years\": \"number of years of experience as integer or null\",
    \"education\": [\"list of degrees/qualifications\"],
    \"preferred_location\": \"city/country if mentioned in CV, or null\",
    \"salary_min\": \"estimated minimum salary in USD per year as integer, or null\",
    \"salary_max\": \"estimated maximum salary in USD per year as integer, or null\",
    \"summary\": \"one-line professional summary\"
}

CV Text:
" . substr($cvText, 0, 8000);

        try {
            $response = Http::withBody(json_encode([
                'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                'generationConfig' => ['temperature' => 0.1],
            ]), 'application/json')->timeout(30)->post(
                "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$this->apiKey}"
            );

            if (!$response->successful()) {
                Log::error('Gemini CV parse error: ' . $response->body());
                return ['error' => 'Failed to analyze CV with AI.'];
            }

            $reply = $response->json('candidates.0.content.parts.0.text', '');
            $reply = trim($reply);

            $reply = preg_replace('/```json\s*/', '', $reply);
            $reply = preg_replace('/```\s*/', '', $reply);

            $data = json_decode($reply, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('CV parse JSON error: ' . $reply);
                return ['error' => 'Failed to parse AI response.'];
            }

            return $data;
        } catch (\Throwable $e) {
            Log::error('CV parse failed: ' . $e->getMessage());
            return ['error' => 'CV analysis failed.'];
        }
    }

    public function buildSearchQuery(array $parsed): string
    {
        $titles = $parsed['job_titles'] ?? [];
        $skills = $parsed['skills'] ?? [];

        $keywords = array_merge(array_slice($titles, 0, 2), array_slice($skills, 0, 3));

        return implode(' ', array_slice($keywords, 0, 4));
    }
}
