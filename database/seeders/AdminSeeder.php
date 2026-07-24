<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'fouedjeudurel02@gmail.com'],
            [
                'name'     => 'Fouedjeu Durel',
                'password' => Hash::make(env('ADMIN_PASSWORD', 'changeme')),
                'locale'   => 'fr',
                'is_admin' => true,
                'onboarding_completed_at' => now(),
            ]
        );
    }
}