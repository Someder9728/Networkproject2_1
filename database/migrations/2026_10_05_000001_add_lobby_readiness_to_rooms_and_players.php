<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->unsignedTinyInteger('player_limit')->default(6);
            $table->timestamp('start_countdown_at')->nullable();
        });
        Schema::table('players', function (Blueprint $table) {
            $table->boolean('is_ready')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('players', fn (Blueprint $table) => $table->dropColumn('is_ready'));
        Schema::table('rooms', fn (Blueprint $table) => $table->dropColumn(['player_limit', 'start_countdown_at']));
    }
};
