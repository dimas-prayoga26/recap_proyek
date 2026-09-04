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
                'email' => 'superadmin@rumahgue.id',
            ],
            [
                'name' => 'Lukman',
                'email' => 'lukman@rumahgue.id',
            ],
            [
                'name' => 'Rexy',
                'email' => 'rexy@rumahgue.id',
            ],
            [
                'name' => 'admin',
                'email' => 'admin@rumahgue.id',
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
