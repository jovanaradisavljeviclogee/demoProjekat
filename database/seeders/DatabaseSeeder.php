<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Fixed e-mail of the anchor user (FR-35).
     *
     * AC-07 and AC-08 prove persistence by running one and the same query
     * twice: after `docker compose down` + `up -d` the row must still be
     * there, after `docker compose down -v` + `up -d` it must be gone.
     * Factory rows are random, so they cannot be that query target - this
     * constant is the only stable value the check has. Do not remove it and
     * do not make it random.
     *
     * The `.test` TLD is reserved and never produced by Faker's safeEmail(),
     * so the anchor can never collide with a factory row on the unique index.
     */
    public const ANCHOR_EMAIL = 'seed-anchor@example.test';

    private const ANCHOR_NAME = 'Seed Anchor';

    /**
     * Additional factory users (FR-35). Kept small on purpose: this is a
     * development fixture, not a load-test data set.
     */
    private const FACTORY_USER_COUNT = 10;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $password = env('SEED_USER_PASSWORD');

        // Fail loudly instead of falling back to a literal (C-02, FR-36) or
        // writing users with an unusable password.
        if (! is_string($password) || $password === '') {
            throw new RuntimeException(
                'SEED_USER_PASSWORD is not set. Copy .env.example to .env, set the value there '
                .'and run the seed again.'
            );
        }

        // Hashed once and reused: the User model's `hashed` cast leaves an
        // already hashed value alone, which keeps the seed at a single bcrypt
        // run instead of one per user.
        $hashedPassword = Hash::make($password);

        User::updateOrCreate(
            ['email' => self::ANCHOR_EMAIL],
            ['name' => self::ANCHOR_NAME, 'password' => $hashedPassword],
        );

        User::factory()
            ->count(self::FACTORY_USER_COUNT)
            ->create(['password' => $hashedPassword]);
    }
}
