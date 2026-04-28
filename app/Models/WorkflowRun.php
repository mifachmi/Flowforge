<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WorkflowRun extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'workflow_id',
        'tenant_id',
        'version',
        'status',
        'started_at',
        'finished_at',
        'trigger_context',
    ];

    protected $casts = [
        'started_at'      => 'datetime',
        'finished_at'     => 'datetime',
        'trigger_context' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(fn($model) => $model->id ??= Str::uuid());
    }

    public function workflow()
    {
        return $this->belongsTo(WorkflowDefinition::class, 'workflow_id');
    }

    public function stepLogs()
    {
        return $this->hasMany(StepLog::class, 'run_id')->orderBy('started_at');
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    // Helper scope
    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeRunning($query)
    {
        return $query->where('status', 'running');
    }

    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    // Helper computed
    public function getDurationMsAttribute(): ?int
    {
        if (!$this->started_at || !$this->finished_at) return null;
        return (int) $this->started_at->diffInMilliseconds($this->finished_at);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['success', 'failed', 'timeout']);
    }
}
