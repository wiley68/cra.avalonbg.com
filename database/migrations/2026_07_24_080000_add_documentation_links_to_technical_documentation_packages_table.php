<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('technical_documentation_packages', function (Blueprint $table): void {
            $table->foreignId('user_security_instruction_id')
                ->nullable()
                ->after('notes');
            $table->foreignId('sdl_run_id')
                ->nullable()
                ->after('user_security_instruction_id');

            $table->foreign('user_security_instruction_id', 'td_packages_usi_fk')
                ->references('id')
                ->on('user_security_instructions')
                ->nullOnDelete();
            $table->foreign('sdl_run_id', 'td_packages_sdl_fk')
                ->references('id')
                ->on('sdl_runs')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('technical_documentation_packages', function (Blueprint $table): void {
            $table->dropForeign('td_packages_usi_fk');
            $table->dropForeign('td_packages_sdl_fk');
            $table->dropColumn(['user_security_instruction_id', 'sdl_run_id']);
        });
    }
};
