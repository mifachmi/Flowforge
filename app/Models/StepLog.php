<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class StepLog extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'run_id',
        'step_id',
        'step_name',
        'status',
        'attempt',
        'output',
        'error',
        'duration_ms',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
        'attempt'     => 'integer',
        'duration_ms' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(fn($model) => $model->id ??= Str::uuid());
    }

    public function run()
    {
        return $this->belongsTo(WorkflowRun::class, 'run_id');
    }
}
