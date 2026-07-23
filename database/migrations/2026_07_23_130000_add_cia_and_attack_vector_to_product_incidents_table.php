<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('product_incidents', function (Blueprint $table) {
            $table->string('confidentiality_impact')->nullable()->after('severity');
            $table->string('integrity_impact')->nullable()->after('confidentiality_impact');
            $table->string('availability_impact')->nullable()->after('integrity_impact');
            $table->string('attack_vector')->nullable()->after('availability_impact');
        });
    }

    public function down(): void
    {
        Schema::table('product_incidents', function (Blueprint $table) {
            $table->dropColumn([
                'confidentiality_impact',
                'integrity_impact',
                'availability_impact',
                'attack_vector',
            ]);
        });
    }
};
