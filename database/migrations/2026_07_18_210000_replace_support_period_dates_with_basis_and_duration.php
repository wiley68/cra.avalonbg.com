<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('product_support_periods', 'starts_at')) {
            return;
        }

        if (!Schema::hasColumn('product_support_periods', 'start_basis')) {
            Schema::table('product_support_periods', function (Blueprint $table) {
                $table->string('start_basis', 32)->default('custom')->after('type');
                $table->unsignedInteger('duration_months')->default(12)->after('start_basis');
            });
        }

        $periods = DB::table('product_support_periods')->get(['id', 'starts_at', 'ends_at']);

        foreach ($periods as $period) {
            $startsAt = $period->starts_at ? \Illuminate\Support\Carbon::parse($period->starts_at) : null;
            $endsAt = $period->ends_at ? \Illuminate\Support\Carbon::parse($period->ends_at) : null;

            $months = 12;
            if ($startsAt !== null && $endsAt !== null) {
                $months = max(1, (int) round($startsAt->floatDiffInMonths($endsAt)));
            }

            DB::table('product_support_periods')
                ->where('id', $period->id)
                ->update([
                    'start_basis' => 'custom',
                    'duration_months' => $months,
                ]);
        }

        Schema::table('product_support_periods', function (Blueprint $table) {
            $table->dropColumn(['starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('product_support_periods', 'starts_at')) {
            return;
        }

        Schema::table('product_support_periods', function (Blueprint $table) {
            $table->date('starts_at')->nullable()->after('type');
            $table->date('ends_at')->nullable()->after('starts_at');
        });

        $periods = DB::table('product_support_periods')->get(['id', 'duration_months']);

        foreach ($periods as $period) {
            DB::table('product_support_periods')
                ->where('id', $period->id)
                ->update([
                    'starts_at' => now()->toDateString(),
                    'ends_at' => now()->addMonths(max(1, (int) $period->duration_months))->toDateString(),
                ]);
        }

        if (Schema::hasColumn('product_support_periods', 'start_basis')) {
            Schema::table('product_support_periods', function (Blueprint $table) {
                $table->dropColumn(['start_basis', 'duration_months']);
            });
        }
    }
};
