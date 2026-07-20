<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('organization_vcs_connections', function (Blueprint $table) {
            $table->text('webhook_secret')->nullable()->after('sync_schedule');
        });

        Schema::create('vcs_webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('organization_vcs_connections')->cascadeOnDelete();
            $table->string('delivery_id')->unique();
            $table->string('event');
            $table->unsignedBigInteger('repository_id')->nullable();
            $table->string('status');
            $table->timestamps();

            $table->index(['connection_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vcs_webhook_deliveries');

        Schema::table('organization_vcs_connections', function (Blueprint $table) {
            $table->dropColumn('webhook_secret');
        });
    }
};
