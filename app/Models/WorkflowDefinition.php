<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class WorkflowDefinition extends Model
{
    use SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'id',
        'tenant_id',
        'name',
        'description',
        'current_version',
        'trigger_type',
        'cron_expression'
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(fn($m) => $m->id ??= Str::uuid());
    }

    // Scope multi-tenant — SELALU gunakan ini
    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function versions()
    {
        return $this->hasMany(WorkflowVersion::class, 'workflow_id');
    }
    public function runs()
    {
        return $this->hasMany(WorkflowRun::class, 'workflow_id');
    }

    public function activeVersion()
    {
        return $this->hasOne(WorkflowVersion::class, 'workflow_id')
            ->where('is_active', true);
    }
}
