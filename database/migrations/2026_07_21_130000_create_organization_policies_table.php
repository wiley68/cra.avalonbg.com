<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('organization_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('policy_type');
            $table->string('title');
            $table->string('status');
            $table->string('version_label');
            $table->longText('body');
            $table->foreignId('supersedes_id')
                ->nullable()
                ->constrained('organization_policies')
                ->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('evidence_id')->nullable()->constrained('evidence')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'policy_type', 'status'], 'org_policies_type_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_policies');
    }
};
