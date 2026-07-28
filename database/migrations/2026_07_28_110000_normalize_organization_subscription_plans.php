<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $aliases = [
            'solo' => 'small',
            'small_team' => 'standard',
            'small-team' => 'standard',
            'company' => 'enterprise',
        ];

        foreach ($aliases as $from => $to) {
            DB::table('organizations')
                ->whereRaw('LOWER(TRIM(subscription_plan)) = ?', [$from])
                ->update(['subscription_plan' => $to]);
        }

        // Case-normalize known canonical keys.
        foreach (['free', 'small', 'standard', 'enterprise'] as $plan) {
            DB::table('organizations')
                ->whereRaw('LOWER(TRIM(subscription_plan)) = ?', [$plan])
                ->where('subscription_plan', '!=', $plan)
                ->update(['subscription_plan' => $plan]);
        }

        // Empty string → null (resolved at runtime as enterprise fallback).
        DB::table('organizations')
            ->where('subscription_plan', '')
            ->update(['subscription_plan' => null]);
    }

    public function down(): void
    {
        // Irreversible normalization.
    }
};
