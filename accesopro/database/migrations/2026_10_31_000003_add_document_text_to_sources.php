<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Texto extraído del PDF de un tema subido desde el panel. Va en la base de datos y no en
 * disco porque en Laravel Cloud el disco es efímero y la web (que recibe el PDF) y el
 * worker (que genera el contenido) son máquinas distintas (ver D29).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sources', function (Blueprint $table) {
            $table->longText('document_text')->nullable()->after('meta');
        });
    }

    public function down(): void
    {
        Schema::table('sources', function (Blueprint $table) {
            $table->dropColumn('document_text');
        });
    }
};
