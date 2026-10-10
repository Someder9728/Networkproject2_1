<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->json('avatar')->nullable();
        });
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('players_recipient_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', fn (Blueprint $table) => $table->dropColumn('players_recipient_id'));
        Schema::table('players', fn (Blueprint $table) => $table->dropColumn('avatar'));
    }
};
