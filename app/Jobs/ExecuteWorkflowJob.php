<?php

namespace App\Jobs;

use App\Events\StepStatusUpdated;
use App\Exceptions\UnknownStepTypeException;
use App\Exceptions\WorkflowStepException;
use App\Models\StepLog;
use App\Models\WorkflowRun;
use App\Services\DagParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;  // ✅ Fix P1009
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class ExecuteWorkflowJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries   = 1;

    public function __construct(public WorkflowRun $run) {}

    public function handle(): void
    {
        Log::info('[Job] Started', ['run_id' => $this->run->id]);

        $this->run->update(['status' => 'running', 'started_at' => now()]);

        try {
            $this->run->load('workflow.activeVersion');

            // Guard 1: workflow tidak ditemukan
            if (!$this->run->workflow) {
                throw new \RuntimeException("Workflow not found for run {$this->run->id}");
            }

            // Guard 2: active version tidak ditemukan
            if (!$this->run->workflow->activeVersion) {
                throw new \RuntimeException("No active version for workflow {$this->run->workflow_id}");
            }

            $dagDefinition = $this->run->workflow->activeVersion->dag_definition;

            Log::info('[Job] DAG type: ' . gettype($dagDefinition));

            // Guard 3: decode jika masih string
            if (is_string($dagDefinition)) {
                $dagDefinition = json_decode($dagDefinition, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \RuntimeException("DAG definition is not valid JSON: " . json_last_error_msg());
                }
            }

            // Guard 4: struktur DAG tidak valid
            if (empty($dagDefinition['steps'])) {
                throw new \RuntimeException("DAG definition has no steps. Value: " . json_encode($dagDefinition));
            }

            Log::info('[Job] DAG loaded', ['steps' => count($dagDefinition['steps'])]);

            $parser         = (new DagParser)->parse($dagDefinition)->validate();
            $parallelGroups = $parser->getParallelGroups();
            $nodes          = $parser->getNodes();

            foreach ($parallelGroups as $group) {
                foreach ($group as $stepId) {
                    $this->executeStep($stepId, $nodes[$stepId]);
                }
            }

            $this->run->update(['status' => 'success', 'finished_at' => now()]);
            Log::info('[Job] Completed successfully', ['run_id' => $this->run->id]);
        } catch (\Throwable $e) {
            Log::error('[Job] FAILED', [
                'run_id'  => $this->run->id,
                'error'   => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => collect(explode("\n", $e->getTraceAsString()))->take(10)->implode("\n"),
            ]);

            $this->run->update(['status' => 'failed', 'finished_at' => now()]);
        }
    }


    private function executeStep(string $stepId, array $step): void
    {
        $startedAt = now();
        $log = StepLog::create([
            'id'         => Str::uuid(),
            'run_id'     => $this->run->id,
            'step_id'    => $stepId,
            'step_name'  => $step['name'],
            'status'     => 'running',
            'attempt'    => 1,
            'started_at' => now(),
        ]);

        broadcast(new StepStatusUpdated($this->run->id, $stepId, 'running'));

        $maxRetries = $step['config']['max_retries'] ?? 3;
        $attempt    = 1;

        while ($attempt <= $maxRetries) {
            try {
                $output = $this->runStep($stepId, $step);
                $finishedAt  = now();
                $durationMs  = (int) max(0, $startedAt->diffInMilliseconds($finishedAt));

                $log->update([
                    'status'      => 'success',
                    'output'      => json_encode($output),
                    'attempt'     => $attempt,
                    'finished_at' => $finishedAt,
                    'duration_ms' => $durationMs,   // ← selalu positif integer
                ]);

                broadcast(new StepStatusUpdated($this->run->id, $stepId, 'success'));
                return;
            } catch (\Throwable $e) {
                if ($attempt === $maxRetries) {
                    $finishedAt = now();
                    $durationMs = (int) max(0, $startedAt->diffInMilliseconds($finishedAt));

                    $log->update([
                        'status'      => 'failed',
                        'error'       => $e->getMessage(),
                        'attempt'     => $attempt,
                        'finished_at' => $finishedAt,
                        'duration_ms' => $durationMs,
                    ]);
                    broadcast(new StepStatusUpdated($this->run->id, $stepId, 'failed'));
                    throw $e;
                }

                // Exponential backoff: 2^attempt detik
                sleep(2 ** $attempt);
                $attempt++;
                $log->update(['status' => 'retrying', 'attempt' => $attempt]);
                broadcast(new StepStatusUpdated($this->run->id, $stepId, 'retrying'));
            }
        }
    }

    private function runStep(string $stepId, array $step): string
    {
        return match ($step['type']) {
            'http'      => $this->runHttpStep($stepId, $step),
            'delay'     => $this->runDelayStep($step),
            'condition' => $this->runConditionStep($stepId, $step),
            default     => throw new UnknownStepTypeException($step['type']), // ✅ Fix S112
        };
    }

    private function runHttpStep(string $stepId, array $step): string
    {
        $config = $step['config'];
        $method = strtolower($config['method'] ?? 'get');

        // Bersihkan URL dari tanda kutip yang tidak sengaja masuk
        $url = trim($config['url'] ?? '', " \"'");

        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \RuntimeException("Invalid URL: {$config['url']}");
        }

        $response = Http::timeout(30)                    // ✅ Fix P1009
            ->withHeaders($config['headers'] ?? [])
            ->{$method}($url, $config['body'] ?? []);

        if (!$response->successful()) {
            throw new WorkflowStepException(            // ✅ Fix S112
                $stepId,
                "HTTP {$response->status()}: {$response->body()}"
            );
        }

        return $response->body();
    }

    private function runDelayStep(array $step): string
    {
        $seconds = $step['config']['seconds'] ?? 1;
        sleep($seconds);
        return "Delayed {$seconds}s";
    }

    private function runConditionStep(string $stepId, array $step): string // ✅ Fix S1172
    {
        $condition = $step['config']['expression'] ?? null;

        if (empty($condition)) {
            throw new WorkflowStepException($stepId, 'Condition expression is empty.');
        }

        // Evaluasi kondisi sederhana: "eq", "gt", "lt"
        $left     = $step['config']['left'] ?? null;
        $operator = $step['config']['operator'] ?? 'eq';
        $right    = $step['config']['right'] ?? null;

        $result = match ($operator) {
            'eq'  => $left == $right,
            'neq' => $left != $right,
            'gt'  => $left > $right,
            'lt'  => $left < $right,
            default => throw new WorkflowStepException($stepId, "Unknown operator: {$operator}"),
        };

        return $result ? 'condition:true' : 'condition:false';
    }
}
