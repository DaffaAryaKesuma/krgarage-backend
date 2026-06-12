<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('vespa')) {
            return;
        }

        Schema::table('vespa', function (Blueprint $table) {
            if (!Schema::hasColumn('vespa', 'reminder_h_minus_3_sent_at')) {
                $table->timestamp('reminder_h_minus_3_sent_at')->nullable()->after('tanggal_servis_selanjutnya');
            }

            if (!Schema::hasColumn('vespa', 'reminder_due_date_sent_at')) {
                $table->timestamp('reminder_due_date_sent_at')->nullable()->after('reminder_h_minus_3_sent_at');
            }

            if (!Schema::hasColumn('vespa', 'reminder_h_plus_7_sent_at')) {
                $table->timestamp('reminder_h_plus_7_sent_at')->nullable()->after('reminder_due_date_sent_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('vespa')) {
            return;
        }

        Schema::table('vespa', function (Blueprint $table) {
            foreach ([
                'reminder_h_minus_3_sent_at',
                'reminder_due_date_sent_at',
                'reminder_h_plus_7_sent_at',
            ] as $column) {
                if (Schema::hasColumn('vespa', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
