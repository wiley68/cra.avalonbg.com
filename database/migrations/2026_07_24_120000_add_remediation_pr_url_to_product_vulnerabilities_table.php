<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('product_vulnerabilities', function (Blueprint $table) {
            $table->string('remediation_pr_url', 2048)->nullable()->after('advisory_url');
        });
    }

    public function down(): void
    {
        Schema::table('product_vulnerabilities', function (Blueprint $table) {
            $table->dropColumn('remediation_pr_url');
        });
    }
};
