<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('patch_campaigns', function (Blueprint $table) {
            $table->dropForeign(['target_version_id']);
        });

        Schema::table('patch_campaigns', function (Blueprint $table) {
            $table->foreign('target_version_id')
                ->references('id')
                ->on('product_versions')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('patch_campaigns', function (Blueprint $table) {
            $table->dropForeign(['target_version_id']);
        });

        Schema::table('patch_campaigns', function (Blueprint $table) {
            $table->foreign('target_version_id')
                ->references('id')
                ->on('product_versions')
                ->restrictOnDelete();
        });
    }
};
