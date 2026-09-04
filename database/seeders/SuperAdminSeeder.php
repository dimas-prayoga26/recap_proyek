<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Super Admin',
                'email' => 'superadmin@gmail.com',
            ],
            [
                'name' => 'Lukman',
                'email' => 'lukman@rnbmanagement.com',
            ],
            [
                'name' => 'Rexy',
                'email' => 'rexy@andalanbersama.com',
            ],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'password' => 'password',
                ],
            );
        }
    }
}
