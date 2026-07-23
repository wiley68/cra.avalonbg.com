<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sdl_runs', function (Blueprint $table): void {
            $table->foreignId('user_security_instruction_id')
                ->nullable()
                ->after('notes')
                ->constrained('user_security_instructions')
                ->nullOnDelete();
            $table->boolean('tech_doc_delta_reviewed')
                ->default(false)
                ->after('user_security_instruction_id');
        });
    }

    public function down(): void
    {
        Schema::table('sdl_runs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('user_security_instruction_id');
            $table->dropColumn('tech_doc_delta_reviewed');
        });
    }
};
