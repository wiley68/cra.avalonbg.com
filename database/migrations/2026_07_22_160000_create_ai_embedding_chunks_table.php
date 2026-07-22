<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_embedding_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            $table->unsignedInteger('chunk_index')->default(0);
            $table->text('content');
            $table->json('embedding');
            $table->string('embedding_model')->nullable();
            $table->unsignedSmallInteger('dimensions')->nullable();
            $table->string('content_hash', 64);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['source_type', 'source_id', 'chunk_index'],
                'ai_emb_source_chunk_uq',
            );
            $table->index(
                ['organization_id', 'product_id', 'source_type'],
                'ai_emb_scope_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_embedding_chunks');
    }
};
