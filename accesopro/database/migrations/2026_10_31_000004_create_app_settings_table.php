<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajustes que se cambian desde el panel y deben sobrevivir a un redeploy (en Laravel Cloud
 * el disco es efímero y el .env no se edita desde la app). Hoy solo guarda el refresh token
 * de YouTube que se obtiene con el botón "Conectar YouTube" (ver D35).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable(); // cifrado con la APP_KEY
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
