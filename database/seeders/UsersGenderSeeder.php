<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersGenderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $femaleFirstNames = [
            'Aarohi', 'Anaya', 'Diya', 'Ishita', 'Kavya',
            'Meera', 'Naina', 'Priya', 'Riya', 'Sanya',
        ];

        $maleFirstNames = [
            'Aarav', 'Aditya', 'Arjun', 'Dev', 'Ishaan',
            'Karan', 'Rahul', 'Rohan', 'Varun', 'Yash',
        ];

        $lastNames = [
            'Sharma', 'Verma', 'Patel', 'Singh', 'Gupta',
            'Khan', 'Joshi', 'Mehta', 'Nair', 'Reddy',
        ];

        $passwordHash = Hash::make('Password.0!');
        $created = 0;

        // 10 female users
        for ($i = 0; $i < 10; $i++) {
            $first = $femaleFirstNames[$i];
            $last = $lastNames[$i % count($lastNames)];
            $created += $this->upsertUser($first, $last, 'female', $passwordHash, $i + 1);
        }

        // 10 male users
        for ($i = 0; $i < 10; $i++) {
            $first = $maleFirstNames[$i];
            $last = $lastNames[($i + 3) % count($lastNames)];
            $created += $this->upsertUser($first, $last, 'male', $passwordHash, $i + 1);
        }

        $this->command->info("UsersGenderSeeder completed. Users created/updated: {$created}");
        $this->command->info('Default password for seeded users: Password.0!');
    }

    private function upsertUser(
        string $firstName,
        string $lastName,
        string $gender,
        string $passwordHash,
        int $sequence
    ): int {
        $baseUsername = strtolower($firstName . '_' . $lastName);
        $username = $this->buildUniqueUsername($baseUsername, $sequence);
        $email = "{$username}@ouptel.com";

        $user = User::query()->where('email', $email)->first();

        $attributes = [
            'username' => $username,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'password' => $passwordHash,
            'gender' => $gender,
            'verified' => '1',
            'active' => '1',
            'admin' => '0',
            'avatar' => '',
            'cover' => '',
        ];

        if ($user) {
            $user->fill($attributes)->save();
            return 1;
        }

        // Keep user_id generation consistent with signup strategy.
        $attributes['user_id'] = $this->generateUniqueUserId();
        User::query()->create($attributes);

        return 1;
    }

    private function buildUniqueUsername(string $base, int $sequence): string
    {
        $candidate = preg_replace('/[^a-z0-9_]/', '', strtolower($base)) ?: 'user';
        $candidate = substr($candidate, 0, 24);
        $candidate .= '_' . $sequence;

        $original = $candidate;
        $counter = 1;

        while (
            User::query()
                ->where('username', $candidate)
                ->where('email', '!=', "{$candidate}@ouptel.com")
                ->exists()
        ) {
            $suffix = '_' . $counter;
            $candidate = substr($original, 0, max(1, 32 - strlen($suffix))) . $suffix;
            $counter++;
        }

        return substr($candidate, 0, 32);
    }

    private function generateUniqueUserId(): int
    {
        do {
            $userId = random_int(100000, 999999);
        } while (User::query()->where('user_id', $userId)->exists());

        return $userId;
    }
}

