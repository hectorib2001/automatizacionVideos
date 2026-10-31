<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Origen del contenido: un tema del temario o una clase grabada.
        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->string('type');                 // temario | clase
            $table->string('code');                 // T1_S01
            $table->string('title')->nullable();
            $table->string('ref')->nullable();      // ruta o URL
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->unique(['type', 'code']);
        });

        // Cada vídeo (desde texto) o clip (desde la clase).
        Schema::create('pieces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained()->cascadeOnDelete();
            $table->string('kind');                 // texto | clip
            $table->string('uid')->unique();        // hash del contenido: evita duplicados al relanzar
            $table->string('idea_title');
            $table->string('status')->index();
            $table->json('script')->nullable();     // guion o datos del clip
            $table->decimal('clip_start', 10, 3)->nullable();
            $table->decimal('clip_end', 10, 3)->nullable();
            $table->string('hook_variant')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('discard_reason')->nullable();
            $table->decimal('cost_eur', 10, 5)->default(0);
            $table->timestamps();
        });

        // Cada pasada del agente revisor, con su motivo.
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('piece_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('attempt');
            $table->string('model');
            $table->string('verdict');              // aprobado | rechazado
            $table->json('reasons')->nullable();
            $table->json('checks')->nullable();     // resultado de cada comprobación
            $table->timestamps();
        });

        Schema::create('renders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('piece_id')->constrained()->cascadeOnDelete();
            $table->string('format');               // 9x16 | 16x9 | thumb | audio
            $table->string('path')->nullable();
            $table->string('drive_file_id')->nullable();
            $table->string('drive_url')->nullable();
            $table->decimal('duration', 8, 2)->nullable();
            $table->timestamps();
            $table->unique(['piece_id', 'format']);
        });

        // Cada llamada que cuesta dinero, para la tabla de control.
        Schema::create('cost_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('piece_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider');             // gemini | anthropic | elevenlabs | ...
            $table->string('operation');            // generator:script, reviewer:check, tts...
            $table->decimal('units', 14, 2);
            $table->string('unit_type');            // tokens | characters | minutes
            $table->decimal('cost_eur', 10, 6);
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('piece_id')->constrained()->cascadeOnDelete();
            $table->string('target');               // drive | youtube
            $table->string('format');               // 9x16 | 16x9
            $table->string('external_id')->nullable();
            $table->string('url')->nullable();
            $table->string('status');               // pendiente | publicada | error
            $table->text('error')->nullable();
            $table->timestamps();
            $table->unique(['piece_id', 'target', 'format']);
        });
    }

    public function down(): void
    {
        foreach (['publications', 'cost_events', 'renders', 'reviews', 'pieces', 'sources'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
