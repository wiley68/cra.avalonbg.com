<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('requirement_versions')) {
            return;
        }

        Schema::table('requirement_versions', function (Blueprint $table) {
            if (!Schema::hasColumn('requirement_versions', 'requirement_text_bg')) {
                $table->text('requirement_text_bg')->nullable()->after('requirement_text');
            }
            if (!Schema::hasColumn('requirement_versions', 'plain_language_bg')) {
                $table->text('plain_language_bg')->nullable()->after('plain_language');
            }
            if (!Schema::hasColumn('requirement_versions', 'applicability_notes_bg')) {
                $table->text('applicability_notes_bg')->nullable()->after('applicability_notes');
            }
            if (!Schema::hasColumn('requirement_versions', 'suggested_controls_text_bg')) {
                $table->text('suggested_controls_text_bg')->nullable()->after('suggested_controls_text');
            }
            if (!Schema::hasColumn('requirement_versions', 'required_evidence_text_bg')) {
                $table->text('required_evidence_text_bg')->nullable()->after('required_evidence_text');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('requirement_versions')) {
            return;
        }

        Schema::table('requirement_versions', function (Blueprint $table) {
            $columns = [
                'requirement_text_bg',
                'plain_language_bg',
                'applicability_notes_bg',
                'suggested_controls_text_bg',
                'required_evidence_text_bg',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('requirement_versions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
