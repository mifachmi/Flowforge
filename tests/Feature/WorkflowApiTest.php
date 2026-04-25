<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::create([
        'id'   => Str::uuid(),
        'name' => 'Test Corp',
        'slug' => 'test-corp',
    ]);

    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role'      => 'admin',
        'password'  => bcrypt('password'),
    ]);

    $this->token = \Tymon\JWTAuth\Facades\JWTAuth::fromUser($this->user);
});

it('dapat login dan mendapatkan token', function () {
    $response = $this->postJson('/api/auth/login', [
        'email'    => $this->user->email,
        'password' => 'password',
    ]);

    $response->assertStatus(200)->assertJsonStructure(['access_token', 'token_type']);
});

it('dapat membuat workflow baru', function () {
    $response = $this->withToken($this->token)->postJson('/api/workflows', [
        'name'           => 'Test Workflow',
        'trigger_type'   => 'manual',
        'dag_definition' => [
            'steps' => [
                [
                    'id'     => 'step1',
                    'name'   => 'Fetch Data',
                    'type'   => 'http',
                    'config' => ['method' => 'GET', 'url' => 'https://api.example.com/data'],
                ],
            ],
            'edges' => [],
        ],
    ]);

    $response->assertStatus(201)->assertJsonPath('name', 'Test Workflow');
});

it('menolak DAG dengan cycle', function () {
    $response = $this->withToken($this->token)->postJson('/api/workflows', [
        'name'           => 'Bad Workflow',
        'trigger_type'   => 'manual',
        'dag_definition' => [
            'steps' => [
                ['id' => 'A', 'name' => 'A', 'type' => 'http', 'config' => ['method' => 'GET', 'url' => 'http://x.com']],
                ['id' => 'B', 'name' => 'B', 'type' => 'http', 'config' => ['method' => 'GET', 'url' => 'http://x.com']],
            ],
            'edges' => [
                ['from' => 'A', 'to' => 'B'],
                ['from' => 'B', 'to' => 'A'],
            ],
        ],
    ]);

    $response->assertStatus(422);
});

it('viewer tidak bisa membuat workflow', function () {
    $viewer = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role'      => 'viewer',
    ]);
    $token = \Tymon\JWTAuth\Facades\JWTAuth::fromUser($viewer);

    $this->withToken($token)->postJson('/api/workflows', [
        'name'           => 'Test',
        'trigger_type'   => 'manual',
        'dag_definition' => [
            'steps' => [['id' => 'A', 'name' => 'A', 'type' => 'http', 'config' => ['method' => 'GET', 'url' => 'http://x.com']]],
            'edges' => [],
        ],
    ])->assertStatus(403);
});
