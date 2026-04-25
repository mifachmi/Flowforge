<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    protected $fillable = ['name', 'email', 'password', 'tenant_id', 'role'];

    protected $hidden = ['password'];

    protected $casts = ['password' => 'hashed'];

    // Wajib untuk JWT
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'tenant_id' => $this->tenant_id,
            'role'      => $this->role,
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
