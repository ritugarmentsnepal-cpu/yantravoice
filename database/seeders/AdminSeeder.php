<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@yantravoice.com'],
            [
                'name'     => 'Admin',
                'password' => Hash::make('Learn@110055'),
                'role'     => 'admin',
                'credits'  => 99999,
                'is_active' => true,
            ]
        );
    }
}
