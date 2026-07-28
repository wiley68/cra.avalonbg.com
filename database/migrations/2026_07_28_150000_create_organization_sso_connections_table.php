<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('organization_sso_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->default('generic');
            $table->string('issuer');
            $table->string('client_id')->nullable();
            $table->text('client_secret')->nullable();
            $table->string('idp_sso_url')->nullable();
            $table->text('idp_x509_cert')->nullable();
            $table->json('allowed_email_domains')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->timestamps();

            $table->unique('organization_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_sso_connections');
    }
};
