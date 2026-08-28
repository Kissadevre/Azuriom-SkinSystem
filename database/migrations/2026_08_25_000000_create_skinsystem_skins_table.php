<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Keep the original migration name registered on existing installations.
     *
     * The schema previously created here is now owned by the six migrations
     * that immediately follow this compatibility bridge.
     */
    public function up(): void
    {
        // Intentionally empty.
    }

    public function down(): void
    {
        // Intentionally empty. The split migrations reverse their own tables.
    }
};
