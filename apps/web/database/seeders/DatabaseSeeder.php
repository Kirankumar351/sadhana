<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // The glossary comes first, always. Ten AI features inject it into their
            // prompts and the translation reviewer checks against it, so anything seeded
            // before it exists would be produced without the vocabulary people search for.
            GlossarySeeder::class,

            DemoContentSeeder::class,

            // Local and staging only — the seeder refuses to run in production.
            StaffSeeder::class,
        ]);
    }
}
