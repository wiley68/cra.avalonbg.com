<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('user_security_instructions', function (Blueprint $table) {
            $table->foreignId('paired_instruction_id')
                ->nullable()
                ->after('supersedes_id')
                ->constrained('user_security_instructions')
                ->nullOnDelete();

            $table->index('paired_instruction_id', 'usi_paired_instruction_idx');
        });
    }

    public function down(): void
    {
        Schema::table('user_security_instructions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('paired_instruction_id');
        });
    }
};
