# AccesoPro · Motor de contenido en vídeo (prueba técnica AI Engineer)

Sistema en Laravel que genera, revisa y publica vídeos educativos cortos de forma automática
a partir de (a) el texto de un tema del temario y (b) una clase grabada en directo.
Es la prueba técnica de AccesoPro (Grupo Mentor). **Entrega: domingo 1 nov 2026, 16:00 hora
peninsular española = 11:00 hora de Bolivia.** Prioridad absoluta: Nivel 1 funcionando de
principio a fin con un solo disparador.

## Documentación de contexto (léela antes de cambiar nada)

- @docs/RETO.md – requisitos completos de la prueba, niveles, entregables y criterios.
- @docs/ARQUITECTURA.md – stack, modelo de datos, pipelines, estados y estructura de carpetas.
- @docs/PLAN.md – fases, tareas y estado actual. **Actualízalo al terminar cada tarea.**
- @docs/DECISIONES.md – decisiones tomadas y por qué. Añade una entrada si tomas otra.
- @docs/REVISOR.md – especificación del agente revisor (pieza más valorada).
- @docs/TEMARIO_OFICIAL.md – temario oficial de Matemáticas GS (Comunidad Valenciana), público.
- docs/SETUP_WINDOWS.md – guía de instalación para humanos (no hace falta cargarla siempre).

## Entorno

- Windows + PowerShell. PHP 8.4, Composer, Laravel (última estable), SQLite en local.
- FFmpeg en el PATH (`ffmpeg -version`). En Laravel Cloud se usarán binarios estáticos.
- Despliegue final: Laravel Cloud plan Starter (5 $ de crédito, 1 cola gestionada,
  scale-to-zero, sistema de archivos efímero).
- Salida actual: Google Drive (`Storage::disk('google')`). YouTube se implementa al final.

## Comandos

```powershell
php artisan migrate
php artisan accesopro:check                 # comprueba FFmpeg, LLMs, ElevenLabs y Drive
php artisan accesopro:voice:clone a.mp3 b.mp3
php artisan accesopro:voice:test
php artisan accesopro:run T1_S01            # (Fase 1+) disparador único del pipeline
php artisan accesopro:export              # CSV: tabla de control, revisiones, rechazos
php artisan queue:work --timeout=3600       # worker (necesario para el botón "Generar" de /fuentes)
php artisan test
```

## Reglas obligatorias

1. **Confidencialidad.** `materiales/` contiene material propiedad de Grupo Mentor (temario
   AccesoPro, guion de podcast, clase grabada, brand book). Está en `.gitignore`: nunca lo
   subas al repositorio ni lo copies en `docs/`. **Nunca lo envíes a un LLM en plan gratuito**
   (p. ej. Gemini free tier: usa los datos para entrenar y puede haber revisión humana). Con el
   plan gratuito solo se prueba con `docs/TEMARIO_OFICIAL.md` (público) o textos inventados.
2. **Todo acceso a LLM pasa por `App\Services\Llm\LlmGateway`** (roles `generator` y
   `reviewer`, configurados en `config/accesopro.php`). Nunca llames a Prism ni a APIs de LLM
   directamente desde jobs o comandos. Generador y revisor deben ser **modelos distintos**.
3. **Toda operación de pago registra su coste** con `App\Services\Costs\CostRecorder` y llama
   antes a `assertWithinBudget()`. El coste por pieza alimenta la tabla de control.
4. **Idempotencia.** Las piezas se identifican con `Piece::makeUid()` (hash del contenido).
   Relanzar el pipeline nunca crea duplicados: usa `firstOrCreate`/`updateOrCreate` y jobs
   `ShouldBeUnique`. Un job que ya hizo su trabajo debe salir sin repetir llamadas de pago.
5. **Estados** de pieza: solo con el enum `App\Enums\PieceStatus`. Todo rechazo del revisor se
   guarda en `reviews` con su motivo.
6. **Corrección por encima del volumen.** Un vídeo con un error de temario es peor que no
   publicarlo. Ante la duda, el revisor rechaza.
7. **Publicación** detrás de una interfaz `Publisher` (`DrivePublisher` ahora,
   `YouTubePublisher` después), elegida con `PUBLISH_TARGET`.
8. **Sin Chromium/Puppeteer/Browsershot** (no disponible en Laravel Cloud Starter). Visuales
   con plantillas PNG/SVG + ImageMagick/GD + FFmpeg.
9. Archivos intermedios en `storage/app/private/runs/{run_id}/...`; en Cloud irán a un bucket.

## Convenciones de código

- PHP 8.4, tipado estricto en firmas, clases `final` cuando no se extienden.
- Código y nombres de clases en inglés; términos de dominio y textos al usuario en español
  (`PieceStatus::Rechazada`, `idea_title`).
- Jobs en `app/Jobs/{Texto,Clase,Common}`, servicios en `app/Services/{Area}`, prompts en
  `resources/prompts/*.md` (no hardcodear prompts largos en PHP).
- Salidas de LLM siempre estructuradas (`LlmGateway::structured` con `ObjectSchema`) y
  validadas antes de guardarse.
- Tests con Pest. Las llamadas externas se simulan con `Http::fake()` y `Prism::fake()`.
- Cambios pequeños y verificables: ejecuta el comando o test relevante tras cada cambio.
