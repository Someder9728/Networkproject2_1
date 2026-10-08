<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', fn (Blueprint $table) => $table->string('game_mode', 10)->default('normal'));
        Schema::table('players', fn (Blueprint $table) => $table->string('role', 20)->nullable()->change());
    }

    public function down(): void
    {
        if (DB::table('players')->where('role', 'guardian')->exists()) {
            throw new LogicException('Cannot roll back while guardian roles exist.');
        }
        Schema::table('players', fn (Blueprint $table) => $table->enum('role', ['villager', 'werewolf', 'seer'])->nullable()->change());
        Schema::table('rooms', fn (Blueprint $table) => $table->dropColumn('game_mode'));
    }
};
