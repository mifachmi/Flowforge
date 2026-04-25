<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class StepStatusUpdated implements ShouldBroadcast
{
    use SerializesModels;

    public function __construct(
        public string $runId,
        public string $stepId,
        public string $status,
    ) {}

    public function broadcastOn(): array
    {
        // Channel per run — frontend subscribe ke channel ini
        return [new Channel("workflow-run.{$this->runId}")];
    }

    public function broadcastAs(): string
    {
        return 'step.updated';
    }
}
