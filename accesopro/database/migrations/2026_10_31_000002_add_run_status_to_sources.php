<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estado de la última ejecución del pipeline lanzada desde el panel (página "Fuentes"):
 * en_cola | procesando | completado | error. Null = nunca se lanzó desde el panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sources', function (Blueprint $table) {
            $table->string('run_status')->nullable()->after('meta');
            $table->text('run_error')->nullable()->after('run_status');
            $table->timestamp('run_started_at')->nullable()->after('run_error');
            $table->timestamp('run_finished_at')->nullable()->after('run_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('sources', function (Blueprint $table) {
            $table->dropColumn(['run_status', 'run_error', 'run_started_at', 'run_finished_at']);
        });
    }
};
