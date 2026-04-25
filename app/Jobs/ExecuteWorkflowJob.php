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

class ExecuteWorkflowJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries   = 1;

    public function __construct(public WorkflowRun $run) {}

    public function handle(): void
    {
        $this->run->update(['status' => 'running', 'started_at' => now()]);

        $dagDefinition  = $this->run->workflow->activeVersion->dag_definition;
        $parser         = (new DagParser)->parse($dagDefinition)->validate();
        $parallelGroups = $parser->getParallelGroups();
        $nodes          = $parser->getNodes();

        try {
            foreach ($parallelGroups as $group) {
                foreach ($group as $stepId) {
                    $this->executeStep($stepId, $nodes[$stepId]);
                }
            }

            $this->run->update(['status' => 'success', 'finished_at' => now()]);
        } catch (\Throwable $e) {
            $this->run->update(['status' => 'failed', 'finished_at' => now()]);
        }
    }

    private function executeStep(string $stepId, array $step): void
    {
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

                $log->update([
                    'status'      => 'success',
                    'output'      => $output,
                    'attempt'     => $attempt,
                    'finished_at' => now(),
                    'duration_ms' => now()->diffInMilliseconds($log->started_at),
                ]);

                broadcast(new StepStatusUpdated($this->run->id, $stepId, 'success'));
                return;
            } catch (\Throwable $e) {
                if ($attempt === $maxRetries) {
                    $log->update([
                        'status'      => 'failed',
                        'error'       => $e->getMessage(),
                        'attempt'     => $attempt,
                        'finished_at' => now(),
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

        $response = Http::timeout(30)                    // ✅ Fix P1009
            ->withHeaders($config['headers'] ?? [])
            ->{$method}($config['url'], $config['body'] ?? []);

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
