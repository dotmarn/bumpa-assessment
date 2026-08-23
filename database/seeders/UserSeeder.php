<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'first_name' => 'Test1',
                'last_name' => 'User',
                'email' => 'test@example.com',
                'password' => 'password',
            ],
            [
                'first_name' => 'Test2',
                'last_name' => 'Testing',
                'email' => 'test1@example.com',
                'password' => 'password',
            ],
        ];

        foreach ($users as $user) {
            User::query()->updateOrCreate(
                ['email' => $user['email']],
                [
                    ...$user,
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}
