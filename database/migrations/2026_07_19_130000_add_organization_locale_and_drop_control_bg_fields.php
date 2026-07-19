<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('organizations', 'locale')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->string('locale', 5)->default('en')->after('billing_email');
            });
        }

        if (
            Schema::hasTable('controls')
            && Schema::hasColumn('controls', 'name_bg')
        ) {
            $controls = DB::table('controls')
                ->join('organizations', 'organizations.id', '=', 'controls.organization_id')
                ->select(
                    'controls.id',
                    'organizations.locale',
                    'controls.name',
                    'controls.name_bg',
                    'controls.description',
                    'controls.description_bg',
                    'controls.implementation_guidance',
                    'controls.implementation_guidance_bg',
                )
                ->get();

            foreach ($controls as $control) {
                if ($control->locale !== 'bg') {
                    continue;
                }

                DB::table('controls')->where('id', $control->id)->update([
                    'name' => filled($control->name_bg) ? $control->name_bg : $control->name,
                    'description' => filled($control->description_bg)
                        ? $control->description_bg
                        : $control->description,
                    'implementation_guidance' => filled($control->implementation_guidance_bg)
                        ? $control->implementation_guidance_bg
                        : $control->implementation_guidance,
                ]);
            }

            Schema::table('controls', function (Blueprint $table) {
                $table->dropColumn([
                    'name_bg',
                    'description_bg',
                    'implementation_guidance_bg',
                ]);
            });
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('controls')
            && !Schema::hasColumn('controls', 'name_bg')
        ) {
            Schema::table('controls', function (Blueprint $table) {
                $table->string('name_bg')->nullable()->after('name');
                $table->text('description_bg')->nullable()->after('description');
                $table->text('implementation_guidance_bg')->nullable()->after('implementation_guidance');
            });
        }

        if (Schema::hasColumn('organizations', 'locale')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->dropColumn('locale');
            });
        }
    }
};
