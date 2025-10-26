<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => '田中太郎',
                'email' => 'tanaka@example.com',
                'password' => Hash::make('password123'),
            ],
            [
                'name' => '佐藤花子',
                'email' => 'sato@example.com',
                'password' => Hash::make('password123'),
            ],
            [
                'name' => '鈴木一郎',
                'email' => 'suzuki@example.com',
                'password' => Hash::make('password123'),
            ],
            [
                'name' => '高橋美咲',
                'email' => 'takahashi@example.com',
                'password' => Hash::make('password123'),
            ],
            [
                'name' => '山田健太',
                'email' => 'yamada@example.com',
                'password' => Hash::make('password123'),
            ],
            [
                'name' => '伊藤由美',
                'email' => 'ito@example.com',
                'password' => Hash::make('password123'),
            ],
            [
                'name' => '渡辺大輔',
                'email' => 'watanabe@example.com',
                'password' => Hash::make('password123'),
            ],
            [
                'name' => '中村智子',
                'email' => 'nakamura@example.com',
                'password' => Hash::make('password123'),
            ],
            [
                'name' => '小林正義',
                'email' => 'kobayashi@example.com',
                'password' => Hash::make('password123'),
            ],
            [
                'name' => '加藤恵',
                'email' => 'kato@example.com',
                'password' => Hash::make('password123'),
            ],
        ];

        foreach ($users as $userData) {
            User::create($userData);
        }
    }
}