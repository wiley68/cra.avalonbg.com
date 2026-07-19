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

            if (!Schema::hasColumn('controls', 'name_bg')) {
                $table->string('name_bg')->nullable()->after('name');
            }

            if (!Schema::hasColumn('controls', 'description_bg')) {
                $table->text('description_bg')->nullable()->after('description');
            }

            if (!Schema::hasColumn('controls', 'implementation_guidance_bg')) {
                $table->text('implementation_guidance_bg')->nullable()->after('implementation_guidance');
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
            foreach ([
                'source',
                'name_bg',
                'description_bg',
                'implementation_guidance_bg',
            ] as $column) {
                if (Schema::hasColumn('controls', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
