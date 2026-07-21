<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('patch_campaign_target_notification_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patch_campaign_target_id')
                ->constrained('patch_campaign_targets')
                ->cascadeOnDelete();
            $table->string('event_type');
            $table->string('channel');
            $table->string('status_before')->nullable();
            $table->string('status_after')->nullable();
            $table->text('body')->nullable();
            $table->string('recipient')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['patch_campaign_target_id', 'created_at'], 'pct_notification_events_target_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patch_campaign_target_notification_events');
    }
};
