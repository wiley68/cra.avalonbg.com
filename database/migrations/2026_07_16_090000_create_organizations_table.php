<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->string('subscription_plan')->default('free');
            $table->string('billing_status')->default('active');
            $table->string('billing_interval')->nullable();
            $table->string('payment_method')->nullable();
            $table->timestamp('billing_activated_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->string('billing_email')->nullable();
            $table->string('stripe_customer_id')->nullable()->index();
            $table->string('stripe_subscription_id')->nullable()->index();
            $table->boolean('sso_enabled')->default(false);
            $table->string('locale', 5)->default('en');
            $table->timestamp('onboarding_checklist_dismissed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};

