<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_risks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_version_id')->nullable()->constrained('product_versions')->nullOnDelete();
            $table->string('title');
            $table->text('asset')->nullable();
            $table->text('threat')->nullable();
            $table->text('weakness')->nullable();
            $table->text('attack_scenario')->nullable();
            $table->string('category');
            $table->unsignedTinyInteger('likelihood');
            $table->unsignedTinyInteger('impact');
            $table->unsignedTinyInteger('residual_likelihood')->nullable();
            $table->unsignedTinyInteger('residual_impact')->nullable();
            $table->string('treatment');
            $table->text('treatment_plan')->nullable();
            $table->string('status');
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('deadline')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'status']);
            $table->index(['product_id', 'category']);
        });

        Schema::create('product_risk_control', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_risk_id')->constrained()->cascadeOnDelete();
            $table->foreignId('control_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['product_risk_id', 'control_id']);
        });

        Schema::create('product_risk_requirement', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_risk_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requirement_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['product_risk_id', 'requirement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_risk_requirement');
        Schema::dropIfExists('product_risk_control');
        Schema::dropIfExists('product_risks');
    }
};
