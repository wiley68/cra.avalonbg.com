<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('controls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('implementation_guidance')->nullable();
            $table->string('automation_level');
            $table->string('frequency');
            $table->boolean('is_active')->default(true);
            $table->string('source')->default('custom');
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'is_active']);
        });

        Schema::create('control_requirement', function (Blueprint $table) {
            $table->id();
            $table->foreignId('control_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requirement_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['control_id', 'requirement_id']);
        });

        Schema::create('product_controls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('control_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->text('notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'control_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_controls');
        Schema::dropIfExists('control_requirement');
        Schema::dropIfExists('controls');
    }
};
