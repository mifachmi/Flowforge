<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Buat tenant
        $tenant = Tenant::create([
            'id'   => Str::uuid(),
            'name' => 'Demo Corp',
            'slug' => 'demo-corp',
        ]);

        // Buat user Admin
        User::create([
            'name'      => 'Admin User',
            'email'     => 'admin@demo.com',
            'password'  => bcrypt('password123'),
            'tenant_id' => $tenant->id,
            'role'      => 'admin',
        ]);

        // Buat user Editor
        User::create([
            'name'      => 'Editor User',
            'email'     => 'editor@demo.com',
            'password'  => bcrypt('password123'),
            'tenant_id' => $tenant->id,
            'role'      => 'editor',
        ]);

        // Buat user Viewer
        User::create([
            'name'      => 'Viewer User',
            'email'     => 'viewer@demo.com',
            'password'  => bcrypt('password123'),
            'tenant_id' => $tenant->id,
            'role'      => 'viewer',
        ]);
    }
}
