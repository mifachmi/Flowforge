<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use OpenAI\Laravel\Facades\OpenAI;

class AiWorkflowController extends Controller
{
    // Sistem prompt yang ketat — mencegah output LLM yang malformed
    private const SYSTEM_PROMPT = <<<'PROMPT'
You are a workflow definition generator for FlowForge.

Your ONLY job is to convert a user's natural language description into a valid JSON workflow definition.

Rules:
1. Always return ONLY valid JSON. No markdown, no explanation, no code blocks.
2. Step IDs must be unique strings like "step1", "step2", etc.
3. Supported step types: "http", "delay", "script", "condition"
4. HTTP config: { "method": "GET|POST|PUT|DELETE", "url": "...", "max_retries": 1-5 }
5. Delay config: { "seconds": number }
6. Script config: { "script": "shell command" }
7. Edges define dependencies — only reference existing step IDs.
8. If the description is unclear, make reasonable assumptions.
9. Never include sensitive data, credentials, or arbitrary code execution.

Output format (strict):
{
  "name": "Workflow name",
  "trigger_type": "manual|cron|webhook",
  "dag_definition": {
    "steps": [...],
    "edges": [...]
  }
}
PROMPT;

    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'description' => 'required|string|min:10|max:1000',
        ]);

        $description = strip_tags(trim($request->input('description')));

        try {
            $response = OpenAI::chat()->create([
                'model'       => 'gpt-4o-mini',  // lebih murah, cukup untuk structured output
                'temperature' => 0.2,              // rendah = output lebih konsisten
                'max_tokens'  => 1000,
                'messages'    => [
                    ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                    ['role' => 'user',   'content' => $description],
                ],
                'response_format' => ['type' => 'json_object'],  // paksa JSON output
            ]);

            $raw = $response->choices[0]->message->content;

            // Guard: pastikan output valid JSON
            $parsed = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('LLM returned invalid JSON: ' . json_last_error_msg());
            }

            // Guard: pastikan struktur minimal ada
            if (empty($parsed['name']) || empty($parsed['dag_definition']['steps'])) {
                throw new \RuntimeException('LLM output missing required fields.');
            }

            // Guard: sanitasi URL di setiap step
            foreach ($parsed['dag_definition']['steps'] as &$step) {
                if (isset($step['config']['url'])) {
                    $step['config']['url'] = trim($step['config']['url'], " \"'");
                }
            }

            return response()->json([
                'success'  => true,
                'workflow' => $parsed,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate workflow: ' . $e->getMessage(),
            ], 422);
        }
    }
}
