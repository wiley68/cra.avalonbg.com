<?php

use App\Support\StarterControlCatalogue;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('controls', function (Blueprint $table) {
            if (!Schema::hasColumn('controls', 'source')) {
                $table->string('source')->default('custom')->after('is_active');
            }
        });

        $codes = collect(StarterControlCatalogue::items())->pluck('code')->all();

        if ($codes !== [] && Schema::hasTable('controls')) {
            DB::table('controls')
                ->whereIn('code', $codes)
                ->update(['source' => 'starter_template']);
        }
    }

    public function down(): void
    {
        Schema::table('controls', function (Blueprint $table) {
            if (Schema::hasColumn('controls', 'source')) {
                $table->dropColumn('source');
            }
        });
    }
};
