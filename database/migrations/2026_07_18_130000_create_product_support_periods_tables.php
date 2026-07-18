<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_support_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('start_basis', 32);
            $table->unsignedInteger('duration_months');
            $table->text('basis')->nullable();
            $table->boolean('is_extended')->default(false);
            $table->text('exceptions_notes')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'type']);
        });

        Schema::create('product_support_period_version', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_support_period_id')
                ->constrained('product_support_periods')
                ->cascadeOnDelete();
            $table->foreignId('product_version_id')
                ->constrained('product_versions')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['product_support_period_id', 'product_version_id'],
                'support_period_version_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_support_period_version');
        Schema::dropIfExists('product_support_periods');
    }
};
