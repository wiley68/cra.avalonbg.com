<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('product_vulnerabilities', function (Blueprint $table) {
            $table->string('external_ticket_url', 2048)->nullable()->after('remediation_pr_url');
        });

        Schema::table('product_incidents', function (Blueprint $table) {
            $table->string('external_ticket_url', 2048)->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('product_vulnerabilities', function (Blueprint $table) {
            $table->dropColumn('external_ticket_url');
        });

        Schema::table('product_incidents', function (Blueprint $table) {
            $table->dropColumn('external_ticket_url');
        });
    }
};
