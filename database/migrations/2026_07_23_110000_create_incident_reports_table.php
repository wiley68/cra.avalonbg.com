<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('incident_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')
                ->constrained('product_incidents')
                ->cascadeOnDelete();
            $table->string('authority');
            $table->timestamp('submitted_at');
            $table->foreignId('submitted_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('submission_channel');
            $table->string('submission_reference')->nullable();
            $table->text('summary')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('evidence_id')
                ->nullable()
                ->constrained('evidence')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['incident_id', 'submitted_at'], 'incident_reports_incident_submitted_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_reports');
    }
};
