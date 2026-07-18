<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('event_type', 64)->change();
            $table->foreignId('organization_id')
                ->nullable()
                ->after('is_success')
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('product_id')
                ->nullable()
                ->after('organization_id')
                ->constrained()
                ->nullOnDelete();
            $table->index(['organization_id', 'occurred_at']);
            $table->index(['product_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'occurred_at']);
            $table->dropIndex(['product_id', 'occurred_at']);
            $table->dropConstrainedForeignId('product_id');
            $table->dropConstrainedForeignId('organization_id');
            $table->string('event_type', 32)->change();
        });
    }
};
