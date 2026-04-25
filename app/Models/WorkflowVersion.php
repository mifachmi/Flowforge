<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WorkflowVersion extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['id', 'workflow_id', 'version', 'dag_definition', 'is_active'];
    protected $casts = ['dag_definition' => 'array', 'is_active' => 'boolean'];

    protected static function boot()
    {
        parent::boot();
        static::creating(fn($m) => $m->id ??= Str::uuid());
    }
}
