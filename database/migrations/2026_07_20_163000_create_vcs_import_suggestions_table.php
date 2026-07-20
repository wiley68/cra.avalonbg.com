<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vcs_import_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('repository_id')->constrained('product_repositories')->cascadeOnDelete();
            $table->string('kind');
            $table->string('external_id');
            $table->json('payload');
            $table->string('status');
            $table->string('accepted_entity_type')->nullable();
            $table->unsignedBigInteger('accepted_entity_id')->nullable();
            $table->timestamps();

            $table->unique(['repository_id', 'kind', 'external_id'], 'vcs_import_suggestions_unique');
            $table->index(['product_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vcs_import_suggestions');
    }
};
