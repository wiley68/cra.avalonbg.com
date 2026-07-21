<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('organization_vcs_connections', function (Blueprint $table) {
            $table->text('token')->nullable()->change();
            $table->string('github_app_id')->nullable()->after('token');
            $table->string('github_installation_id')->nullable()->after('github_app_id');
            $table->text('github_private_key')->nullable()->after('github_installation_id');
        });
    }

    public function down(): void
    {
        Schema::table('organization_vcs_connections', function (Blueprint $table) {
            $table->dropColumn([
                'github_app_id',
                'github_installation_id',
                'github_private_key',
            ]);
            $table->text('token')->nullable(false)->change();
        });
    }
};
