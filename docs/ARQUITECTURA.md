# Arquitectura

## Stack

| Pieza | Elección | Motivo |
|---|---|---|
| Framework | Laravel (última estable) + PHP 8.4 | Colas, cadenas de jobs, reintentos, `ShouldBeUnique`; despliegue en Laravel Cloud |
| Base de datos | SQLite (local) / la de Laravel Cloud (prod) | Fuente de verdad del estado de cada pieza |
| Cola | `database` en local; la cola gestionada de Cloud en prod (solo 1 → todo en `default`) | El orden lo dan las cadenas `Bus::chain` |
| LLM | Prism PHP vía `LlmGateway` | Cambiar proveedor/modelo por `.env` |
| Generador | Gemini Flash (pruebas con material público) → Claude Sonnet o Gemini de pago | |
| Revisor | Modelo distinto: Gemini Pro (pruebas) → Claude Opus | Requisito "segundo modelo" |
| Verificación numérica | `brick/math` (BigRational) + parser propio | Recalcula de forma exacta cada afirmación |
| Voz | ElevenLabs, voz clonada (IVC), `eleven_multilingual_v2`, endpoint `with-timestamps` | Alineamiento por carácter → subtítulos exactos |
| Transcripción de la clase | Whisper (Groq u OpenAI) con timestamps por palabra | Detección y corte de clips |
| Visuales | Plantillas de marca PNG + SVG generados por el LLM → ImageMagick → PNG | Sin Chromium |
| Montaje | FFmpeg: subtítulos ASS quemados, 1080×1920 y 1920×1080 | Gratuito, estándar |
| Panel / tabla de control | Filament | Tabla con estado, coste y enlace; botones de aprobación (Nivel 2) |
| Publicación | `Publisher`: `DrivePublisher` (ahora) / `YouTubePublisher` (final) | `PUBLISH_TARGET` |

## Modelo de datos (migración `2026_10_30_000001_create_accesopro_tables`)

- `sources`: tema (`temario`, `T1_S01`) o clase (`clase`, id del vídeo).
- `pieces`: una por vídeo (`kind=texto`) o clip (`kind=clip`). `uid` único (hash), `status`,
  `script` (JSON), `clip_start/clip_end`, `hook_variant`, `attempts`, `discard_reason`, `cost_eur`.
- `reviews`: cada pasada del revisor (`attempt`, `model`, `verdict`, `reasons`, `checks`).
- `renders`: archivos producidos por pieza y formato (`9x16`, `16x9`, `thumb`, `audio`).
- `cost_events`: cada operación de pago (proveedor, operación, unidades, coste en €).
- `publications`: destino (`drive`/`youtube`), formato, URL, estado y error.

## Estados de una pieza (`App\Enums\PieceStatus`)

```
generada → revisada ─┬─ aprobada → renderizada → publicada
                     └─ rechazada → corregida → (revisión de nuevo, máx. 2 correcciones)
                                              └─ descartada (motivo en discard_reason)
```
Con aprobación humana activa (`REQUIRE_HUMAN_APPROVAL=true`), `revisada` espera en Filament a
que un profesor pulse aprobar o rechazar antes de pasar a `aprobada`.

## Pipelines (cadenas de jobs)

**Texto (fuente a):**
`ExtractIdeas → GenerateScript → ReviewScript ⇄ ReviseScript → SynthesizeVoice → BuildScenes →
RenderVideo(9x16) + RenderVideo(16x9) → GenerateMetadata + GenerateThumbnail → Publish`

**Clase (fuente b):**
`FetchClassVideo → ExtractAudio → Transcribe → DetectMoments → ReviewClip → CutAndRender(9x16,
16x9) → GenerateMetadata + GenerateThumbnail → Publish`

Disparador único: `php artisan accesopro:run T1_S01 --class=<url|ruta>` (y botón en Filament).
`FetchClassVideo` intenta yt-dlp; si YouTube bloquea la descarga, usa un MP4 ya subido como
entrada (`materiales/clase.mp4` o bucket). Documentarlo en el README.

## Especificación de contenido

**Guion desde texto (JSON):** `idea_title`, `hooks` (2 variantes A/B), `explanation`,
`cta`, `scenes[]` (texto en pantalla, fórmula o SVG opcional, duración), `claims[]`
(afirmaciones numéricas verificables), `syllabus_refs[]` (saberes del temario oficial).
Duración 30–60 s ≈ 75–150 palabras habladas. Un adulto que prepara un examen debe aprender algo
concreto y aplicable. CTA orientada a AccesoPro sin promesas inventadas.

**Clip desde clase (JSON):** `start`, `end`, `type` (explicacion_completa | truco_examen |
error_tipico), `title`, `why_standalone`, `transcript`. Cortes ajustados a límites de frase,
duración típica 30–90 s; se descartan clips que dependan de contexto previo ("como vimos antes").

**Montaje:** 1080×1920 y 1920×1080, 30 fps, H.264 + AAC, subtítulos ASS quemados (2 líneas
máx., palabras resaltadas opcional), logo y colores de marca (provisionales hasta recibir el
brand book). Para clips 9:16: vídeo original centrado con fondo difuminado.

## Estructura de carpetas

```
app/
  Console/Commands/      accesopro:check, voice:clone, voice:test, run
  Enums/PieceStatus.php
  Jobs/{Texto,Clase,Common}/
  Models/                Source, Piece, Review, Render, CostEvent, Publication
  Services/
    Llm/LlmGateway.php
    Costs/CostRecorder.php
    Voice/ElevenLabsClient.php
    Review/              ReviewerAgent, MathVerifier
    Video/               SceneBuilder, SubtitleBuilder, FfmpegRenderer, ThumbnailMaker
    Publishing/          Publisher (interfaz), DrivePublisher, YouTubePublisher
config/accesopro.php
resources/prompts/       prompts en Markdown
resources/brand/         plantillas y logo (provisionales)
materiales/              CONFIDENCIAL, en .gitignore
storage/app/private/runs/{run_id}/
```

## Restricciones conocidas

- YouTube: proyectos de API sin auditar suben los vídeos bloqueados en privado; cuota diaria de
  10.000 unidades y `videos.insert` históricamente a 1.600 (verificar). Ver `DECISIONES.md`.
- Laravel Cloud Starter: sin add-ons de FFmpeg/Puppeteer (son de Private Cloud) → binarios
  estáticos (`mathiasgrimm/laravel-cloud-binaries`); disco efímero; gasto de cómputo limitado.
- Gemini gratis: datos usados para entrenamiento → solo material público.
