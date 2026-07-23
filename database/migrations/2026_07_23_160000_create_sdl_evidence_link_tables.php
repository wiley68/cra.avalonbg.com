<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sdl_run_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sdl_run_id')
                ->constrained('sdl_runs')
                ->cascadeOnDelete();
            $table->foreignId('evidence_id')
                ->constrained('evidence')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['sdl_run_id', 'evidence_id'], 'sdl_run_evidence_unique');
        });

        Schema::create('sdl_stage_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sdl_stage_entry_id')
                ->constrained('sdl_stage_entries')
                ->cascadeOnDelete();
            $table->foreignId('evidence_id')
                ->constrained('evidence')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['sdl_stage_entry_id', 'evidence_id'], 'sdl_stage_evidence_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sdl_stage_evidence');
        Schema::dropIfExists('sdl_run_evidence');
    }
};
