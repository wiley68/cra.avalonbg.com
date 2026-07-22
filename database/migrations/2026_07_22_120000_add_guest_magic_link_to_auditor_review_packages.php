<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('auditor_review_packages', function (Blueprint $table) {
            $table->string('guest_token_hash', 64)->nullable()->unique()->after('notes');
            $table->timestamp('guest_token_expires_at')->nullable()->after('guest_token_hash');
            $table->timestamp('guest_token_created_at')->nullable()->after('guest_token_expires_at');
            $table->foreignId('guest_token_created_by')
                ->nullable()
                ->after('guest_token_created_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('guest_token_last_accessed_at')->nullable()->after('guest_token_created_by');
        });
    }

    public function down(): void
    {
        Schema::table('auditor_review_packages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('guest_token_created_by');
            $table->dropColumn([
                'guest_token_hash',
                'guest_token_expires_at',
                'guest_token_created_at',
                'guest_token_last_accessed_at',
            ]);
        });
    }
};
