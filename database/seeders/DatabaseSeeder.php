<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Applies to everything in the `call()` list below, not only to user
     * seeding: `Seeder::__invoke()` wraps the whole block in
     * `Model::withoutEvents()`. A seeder that depends on an observer, a
     * `booted()` hook or a `creating` callback will have it silently skipped
     * here.
     */
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Orchestrator only - the seeding logic itself lives in the individual
     * seeders (R24).
     *
     * Seeders for `currencies`, `countries`, `provider_accounts`, `settings`
     * and `payment_methods` are deliberately left out of this iteration: that
     * is the customer's decision, recorded as a Non-goal in
     * `docs/domenProblema/spec.md` and as risk RK8 in
     * `docs/domenProblema/design.md`. Those tables therefore stay empty after
     * a seed - that is expected, not a bug. Add each seeder to the `call()`
     * list below once it is actually asked for.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
        ]);
    }
}
