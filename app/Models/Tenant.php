<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Tenant extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['id', 'name', 'slug'];

    protected static function boot()
    {
        parent::boot();
        static::creating(fn($model) => $model->id ??= Str::uuid());
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
    public function workflows()
    {
        return $this->hasMany(WorkflowDefinition::class);
    }
}
