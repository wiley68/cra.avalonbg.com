<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sdl_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sdl_stage_entry_id')
                ->unique()
                ->constrained('sdl_stage_entries')
                ->cascadeOnDelete();
            $table->foreignId('owner_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->date('expires_at');
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sdl_exceptions');
    }
};
