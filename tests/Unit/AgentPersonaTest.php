<?php

namespace Tests\Unit;

use App\Agents\AssistantAgent;
use App\Agents\CodeReviewerAgent;
use App\Agents\LanguageTutorAgent;
use App\Agents\SummarizerAgent;
use App\Services\GeminiService;
use Tests\TestCase;

class AgentPersonaTest extends TestCase
{
    public function test_agent_classes_instantiate_with_instructions(): void
    {
        $assistant = new AssistantAgent;
        $codeReviewer = new CodeReviewerAgent;
        $tutor = new LanguageTutorAgent;
        $summarizer = new SummarizerAgent;

        $this->assertStringContainsString('helpful', (string) $assistant->instructions());
        $this->assertStringContainsString('Code Reviewer', (string) $codeReviewer->instructions());
        $this->assertStringContainsString('Language', (string) $tutor->instructions());
        $this->assertStringContainsString('Summarizer', (string) $summarizer->instructions());
    }

    public function test_agents_have_tools_registered(): void
    {
        $codeReviewer = new CodeReviewerAgent;
        $tools = iterator_to_array($codeReviewer->tools());

        $this->assertNotEmpty($tools);
        $this->assertCount(4, $tools);
    }
}
