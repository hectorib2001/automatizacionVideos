# Plan y estado

Entrega: **domingo 1 nov 2026, 11:00 hora de Bolivia.** Marca `[x]` al terminar cada tarea y
anota bloqueos en "Notas".

## Fase 0 – Base del proyecto y voz clonada  ← CASI CERRADA (falta solo la muestra de voz)
- [x] PHP 8.4 + proyecto Laravel nuevo (ver `SETUP_WINDOWS.md`) — instalado vía php.new,
      proyecto creado en `accesopro/`. Nota: hubo que añadir `curl.cainfo`/`openssl.cafile`
      (descargar `cacert.pem` de curl.se) al `php.ini` de php.new, si no fallan todas las
      llamadas HTTPS con "SSL peer certificate ... not OK".
- [x] Paquetes: `prism-php/prism`, `google/apiclient`, `masbug/flysystem-google-drive-ext`,
      `brick/math` (con `-W`: bajó `guzzlehttp/guzzle` de 8.3.1 a 7.16.1 por requisito de
      `masbug/flysystem-google-drive-ext`). `pestphp/pest` + `pest-plugin-laravel` añadidos
      para cumplir la convención de tests del `CLAUDE.md` (el esqueleto de Laravel 12 trae
      PHPUnit por defecto).
- [x] Integrar archivos de `accesopro-fase0.zip` (venía en `~/Downloads/accesopro-fase0`,
      no en el repo): config, migración, modelos, servicios (`LlmGateway`, `CostRecorder`,
      `ElevenLabsClient`), comandos (`check`, `voice:clone`, `voice:test`).
- [x] Registrar `GoogleDriveServiceProvider` y añadir el disco `google`
- [x] `.gitignore`: `/materiales`, `/accesopro/storage/app/private/runs`
- [x] `php artisan migrate` — todas las tablas creadas.
- [x] `accesopro:check` — **todo en verde**: FFmpeg ✔, LLM generador (`claude-sonnet-5-5`) ✔,
      LLM revisor (`claude-opus-5-5`) ✔, ElevenLabs ✔ (plan Starter, clonación disponible),
      Google Drive ✔. Gemini quedó descartado para Fase 1 (cuota gratuita agotada en pruebas
      y D3 prohíbe usarlo con material confidencial); se pasó a Claude para ambos roles
      (ver D15). Nombres de modelo de Gemini también corregidos por si se reactiva como
      alternativa (D14).
- [ ] Grabar muestras de voz (1–2 min, humano) y correr `accesopro:voice:clone` +
      `accesopro:voice:test` — único paso manual que queda en Fase 0.

## Fase 1 – Ideas, guiones y agente revisor  ← EN CURSO (feliz camino probado en real)
- [x] `accesopro:run {code} --limit=N` (crea `Source`, lanza la cadena). `--limit` (nuevo,
      no estaba en la especificación original) controla cuántas ideas se procesan en una
      ejecución para no disparar N llamadas de pago de golpe; por defecto 1. Ver D16.
- [x] `ExtractIdeas`: probado en real contra T1_S01 → **7 ideas** (operaciones, ordenación,
      propiedades, error frecuente), usando el JSON del podcast + ambos temarios. Idempotente
      (no vuelve a llamar al LLM si `source.meta.ideas` ya existe).
- [x] `GenerateScript` con salida estructurada (`app/Services/Llm/Schemas/ScriptSchema.php`) y
      prompt en `resources/prompts/generate_script.md`. Probado en real: guion sobre "sumar
      fracciones con mcm" correcto, con gancho A/B, 6 escenas y 6 `claims`.
- [x] `MathVerifier` (`app/Services/Review/MathVerifier.php` + `Math/ExpressionEvaluator.php`,
      `brick/math`) + 10 tests Pest, incluidos los 7 casos obligatorios de `REVISOR.md`.
- [x] `ReviewerAgent` (`app/Services/Review/ReviewerAgent.php`) según `REVISOR.md`: probado en
      real con Claude Opus → `aprobado`, las 6 comprobaciones correctas.
- [x] Casos de prueba de rechazo (Pest, con Prism fake, cero coste): error inyectado
      `2^-3 = -8` fuerza `fail` aunque el LLM diga que está bien; el caso del "90 %" del
      podcast se simuló vía `datos_inventados` y lleva a `descartada` al 3er intento.
- [x] **Rechazo real conseguido** (no simulado): la idea `multiplicar_dividir_fracciones`
      fue rechazada por el revisor real (Claude Opus) — ver "Ejemplo de rechazo" más abajo.
      Cubre el entregable "≥1 guion rechazado con su motivo".
- [x] Las 7 ideas de T1_S01 procesadas (guion + revisión cada una): 6 aprobadas a la primera,
      1 rechazada → corregida con `ReviseScript` → **las 7 terminaron `revisada` (aprobadas)**.
- [x] `ReviseScript` (`app/Jobs/Texto/ReviseScript.php`): reescribe solo lo que señalan los
      `reasons` de la última revisión (prompt en `resources/prompts/revise_script.md`) y
      vuelve a dejar la pieza en `corregida` para que `ReviewScript` la revise de nuevo.
      Probado en real: corrigió el dato sin fuente y ajustó las escenas a 40 s exactos: el
      revisor (intento 2) la aprobó. Bucle completo en `RunCommand` (máx. 2 correcciones,
      luego `descartada`), probado con Prism fake y en real.

**Fase 1 completa para T1_S01: 7/7 ideas con guion aprobado.** Coste real acumulado
(ideas + 7 guiones + 8 revisiones, incluida la corrección): **≈0,78 €**. Muy por debajo del
límite diario de 5 € y del tope de reembolso de 30 €.

### Ejemplo de rechazo (entregable)

Idea `multiplicar_dividir_fracciones` ("Multiplicar y dividir fracciones sin común
denominador"). Las matemáticas del guion eran correctas (verificadas por `MathVerifier`), pero
el revisor (Claude Opus, modelo distinto del generador) lo rechazó por `datos_inventados`:

> El gancho A afirma «Es el error que más puntos te cuesta». Es una afirmación comparativa
> sobre la puntuación del examen que no tiene fuente: ni está en el temario oficial ni se
> deduce de las matemáticas del guion. Además, el gancho B promete «40 segundos», pero las
> escenas suman 43 s (8+9+9+9+8). Es un dato menor, pero tampoco cuadra exactamente.

Exactamente el tipo de comprobación que pide `docs/REVISOR.md`: no es un cálculo matemático
erróneo, es una afirmación sin respaldo y una promesa de duración que no se cumple — el tipo
de error que una revisión genérica (o un corrector ortográfico) no detectaría.

## Fase 2 – Voz, visuales y render  ← COMPLETA para T1_S01 (fuente texto)
- [x] `SynthesizeVoice` (`app/Jobs/Texto/SynthesizeVoice.php`): narra con la voz clonada
      (gancho A + explicación + CTA) y guarda MP3 + alineamiento por carácter. Probado en
      real: 780 caracteres, 52,1 s de audio, ≈0,12 €.
- [x] `SubtitleBuilder` (`app/Services/Video/SubtitleBuilder.php`): alineamiento por
      carácter → palabras → cues de ≤4 palabras/≤34 caracteres → `.ass`. 4 tests Pest.
- [x] `SceneBuilder` (`app/Services/Video/SceneBuilder.php`): fondo de marca (color
      corporativo + logo) con GD. **Nota importante (ver D18)**: el GD de este entorno no
      tiene FreeType, así que el texto de las escenas NO se dibuja aquí — lo pone FFmpeg.
- [x] `FfmpegRenderer` (`app/Services/Video/FfmpegRenderer.php`): compone fondo + texto de
      cada escena (`drawtext`, con las posiciones calculadas en PHP para que texto y
      fórmula no se solapen) + subtítulos quemados (`ass`) + audio, 1080×1920 y 1920×1080,
      30 fps, H.264+AAC.
- [x] `RenderVideo` (`app/Jobs/Texto/RenderVideo.php`): job que encadena los tres
      servicios anteriores para una pieza, en los dos formatos. Idempotente por formato.
- [x] `GenerateMetadata` (`app/Jobs/Texto/GenerateMetadata.php`): título, descripción y
      etiquetas (guardados en `script.metadata`, no hay columna propia en `pieces`).
- [x] `ThumbnailMaker` (`app/Services/Video/ThumbnailMaker.php`) + `GenerateThumbnail`:
      miniatura 1280×720 con fondo de marca + título, mismo motor de texto que el vídeo.
- [x] `Publisher`/`DrivePublisher` (`app/Services/Publishing/`) + job `Publish`: sube los dos
      formatos a `AccesoPro/{code}/texto/{uid}/...` y guarda la URL (vista estándar de
      Drive, no el enlace de descarga directa) en `publications`. Idempotente por formato.
- [x] **Las 7 piezas de T1_S01 publicadas de punta a punta**: ideas → guion → revisión
      (con 1 corrección real) → voz → vídeo 9:16 y 16:9 → metadata → miniatura → Drive.
      `accesopro:run T1_S01 --limit=7` ejecuta todo el pipeline con un solo disparador.
      **Enlaces de los 7 vídeos (9:16 y 16:9) en la sección "Vídeos publicados" más abajo.**
      Coste total del día: **≈1,57 €** (0,75 € Claude + 0,78 € ElevenLabs; renderizar,
      generar miniaturas y publicar en Drive son gratis, solo cómputo/ancho de banda local).
      Cubre el entregable "≥3 vídeos publicados desde texto" (son 7).

### Vídeos publicados (T1_S01, fuente texto)

| Idea | 9:16 | 16:9 |
|---|---|---|
| Sumar y restar fracciones con el mcm | [ver](https://drive.google.com/file/d/1SO0P9TtKwfmsEw4OVnLk0V6j7DMONvq9/view?usp=sharing) | [ver](https://drive.google.com/file/d/1_NTbIP8WBXpfrQ61iC-RvgHknT54xc9I/view?usp=sharing) |
| Multiplicar y dividir fracciones sin común denominador | [ver](https://drive.google.com/file/d/1Gadmx631fFLfl2_aDu7bpjlx66QXSvFd/view?usp=sharing) | [ver](https://drive.google.com/file/d/19I4LLqjf-1BqSIb7JZhMi4lloDubgB6Q/view?usp=sharing) |
| Potencias de exponente negativo | [ver](https://drive.google.com/file/d/1RZMlxYT6i5Ob97LX-bASpmjO0yekEACo/view?usp=sharing) | [ver](https://drive.google.com/file/d/1jSOgT72FholHiwrjARSBloPAvexhxtA9/view?usp=sharing) |
| Error frecuente: el orden de las operaciones | [ver](https://drive.google.com/file/d/1Hm_70eu4AYkblCWrLwLJoQX982zxaGgK/view?usp=sharing) | [ver](https://drive.google.com/file/d/1cBxU_Bk46IIDYWJGnC5hzCGWF4LwXnH4/view?usp=sharing) |
| Representar y comparar números reales en la recta | [ver](https://drive.google.com/file/d/1HrEX3ZtncAHcqxL3Bk-8trYnQarqAFqC/view?usp=sharing) | [ver](https://drive.google.com/file/d/1-nTQqe7ldVBggfFq2iMqcQkl8TP8xNdI/view?usp=sharing) |
| Propiedades conmutativa y distributiva y factor común | [ver](https://drive.google.com/file/d/1oIHp62IFWKmgEKDjVwcJ_peAQzO569aM/view?usp=sharing) | [ver](https://drive.google.com/file/d/1kvZdU98l2Gi0W2WmU_9fxVbNmkKc9AyV/view?usp=sharing) |
| Comprobar una operación con fracciones pasando a decimales | [ver](https://drive.google.com/file/d/17Zv98mjRHTR4X-CAl36dZ0kpMUcDlTg7/view?usp=sharing) | [ver](https://drive.google.com/file/d/1sAyM4WhU2G-6myOjVuowH4nQXhpAaVN7/view?usp=sharing) |

## Fase 3 – Clips de la clase  ← COMPLETA
Clase recibida: "T1S02 Números reales y recta real" (58:42), impartida por Arantxa Gomera,
`https://www.youtube.com/watch?v=fQBP3hY39oU`.

- [x] `FetchClassVideo` (`app/Jobs/Clase/FetchClassVideo.php`): yt-dlp descargó el vídeo sin
      bloqueo de YouTube (vídeo + audio por separado, fusionados con FFmpeg: ver D20).
      `ExtractAudio`: pista mono 16kHz a 32kbps (D21: 64kbps superaba el límite de 25MB de
      Groq en el plan gratuito).
- [x] `Transcribe` (`app/Jobs/Clase/Transcribe.php` + `TranscriptionClient`, Groq
      `whisper-large-v3-turbo` con `timestamp_granularities[]=word`, llamado directo a la
      API porque Prism no expone ese parámetro — ver D22): 7.860 palabras con marca de
      tiempo exacta. Coste real: ≈0,034 €.
- [x] `DetectMoments` (`app/Jobs/Clase/DetectMoments.php` + `TranscriptSegmenter`): agrupa
      palabras en frases, pide 8-12 candidatos al LLM, ajusta sus tiempos a los límites de
      frase reales y extrae el texto del clip de la transcripción (no de lo que "recuerde"
      el LLM). Primer intento con el prompt inicial: solo 1/7 aprobados (la clase es muy
      interactiva: la mayoría de momentos dependen de la pizarra o de "lo anterior"). Se
      afinó el prompt (D23: exigir que la frase de inicio introduzca el tema, prohibir
      conectores de continuación y referencias deícticas) y se pidieron más candidatos:
      **3/10 aprobados** en el segundo intento.
- [x] `ReviewClip` (`app/Jobs/Clase/ReviewClip.php`, reutiliza `ReviewerAgent`): a diferencia
      de un guion, un clip rechazado se descarta directo (no hay corrección, "es lo que dijo
      el profesor"). **7 rechazos reales** con errores matemáticos y pedagógicos genuinos
      detectados en la clase en directo (ver ejemplos abajo) — la pieza de evaluación más
      valorada del enunciado, demostrada con creces.
- [x] `CutAndRender` (`FfmpegRenderer::renderClip`): corta el fragmento exacto del vídeo
      original; en 9:16 lo centra sobre un fondo difuminado del mismo vídeo (`gblur`), en
      16:9 lo escala directo; subtítulos quemados alineados por palabra (reutiliza
      `SubtitleBuilder::buildCuesFromWords`, nuevo método junto al ya existente para
      ElevenLabs). `GenerateMetadata`/`GenerateThumbnail`/`Publish` son los mismos jobs que
      la fuente de texto (se movieron a `app/Jobs/Common/`, ver D24: no eran específicos de
      "texto").

**Resultado: 3 clips reales aprobados y publicados en Drive** (9:16 y 16:9 cada uno) — cubre
el entregable "≥3 clips de la clase". Coste total del día (Fase 1+2+3): **≈2,73 €**.

### Clips publicados (clase T1S02)

| Momento | Tipo | 9:16 | 16:9 |
|---|---|---|---|
| Clasifica los números siempre de abajo para arriba | explicacion_completa | [ver](https://drive.google.com/file/d/1JlYtAiPqxclT6p5pTny7tk8rN_B-H16-/view?usp=sharing) | [ver](https://drive.google.com/file/d/1gPDiIzbnaYAPEtCWh5tsfDdUySNaKkMO/view?usp=sharing) |
| Pasar de decimal a fracción: exacto y periódico | explicacion_completa | [ver](https://drive.google.com/file/d/1V121XtMAvaQ_i2f6XpRKtDy2H2U8bLrK/view?usp=sharing) | [ver](https://drive.google.com/file/d/1mUYBBdxRLnbsUWnNMvc0Pw4lWV_MRTQr/view?usp=sharing) |
| Qué es un conjunto y qué es la unión | explicacion_completa | [ver](https://drive.google.com/file/d/1eXWR7jg1KZM3KzKI4xrtDTWRg5s_tiYN/view?usp=sharing) | [ver](https://drive.google.com/file/d/1QVdebbWm2sVZk2AoLKVdr9DsKOuFYI36/view?usp=sharing) |

### Ejemplos de rechazo de clips (entregable)

Todos reales, no simulados. El revisor (Claude Opus) encontró errores que un corte por
tiempo nunca detectaría:

- **"Cómo reconocer un número irracional con la calculadora"** — rechazado: la profesora
  sugiere que la pantalla de la calculadora permite ver si un decimal es periódico o no. Es
  matemáticamente falso (una pantalla finita no puede mostrarlo; contraejemplo: 1/17 parece
  "aleatorio" en pocas cifras y es racional).
- **"La raíz no exacta de un natural es irracional"** — rechazado: π y e se presentan como
  ejemplos de "raíz no exacta de un número natural", lo cual es conceptualmente incorrecto
  (no son raíces de nada).
- **"El producto de dos irracionales puede ser un número natural"** — rechazado: se justifica
  que √2 es irracional por tener "decimales infinitos", criterio falso (1/3 también tiene
  infinitos decimales y es racional).

(Los 7 rechazos completos, con el detalle exacto del revisor, están en la tabla de `reviews`
de la base de datos; se exportarán a CSV en la Fase 6.)

## Fase 4 – Tabla de control y despliegue
- [x] **Login + tabla de control** (`app/Http/Controllers/Auth/LoginController.php`,
      `app/Http/Controllers/ControlPanelController.php`, `resources/views/{layouts,auth,control}/`):
      Filament se abandonó por un bloqueo irreconciliable con `ext-intl` en este entorno
      (ver D25) — se construyó con Laravel puro (Auth nativo + Blade), sin dependencias
      nuevas. Tabla con título, tipo, estado, **origen** (de qué material/vídeo viene cada
      pieza — ya existía vía `Piece->source`, sin añadir columnas ni backfill), coste real,
      enlaces 9:16/16:9 a Drive y motivo de descarte; filtros por estado y tipo; coste total.
      Probado en real en el navegador: login, tabla con las 17 piezas, filtros, logout — y
      con 6 tests Pest (`tests/Feature/ControlPanelTest.php`).
- [x] **Estados reales hasta `publicada`** (D26): hasta ahora ningún job pasaba de `revisada`,
      así que la tabla mostraba las piezas publicadas como "revisada". Ahora el pipeline
      avanza `revisada → aprobada → renderizada → publicada` (métodos `approve()`,
      `markRendered()`, `markPublished()` en `Piece`, llamados desde `RunCommand`,
      `RenderVideo`/`CutAndRender` y `Publish`). La migración
      `2026_10_31_000001_backfill_piece_status_after_publish` corrigió las 10 piezas ya
      publicadas. Estado actual: **10 `publicada` (7 texto + 3 clips), 7 `descartada`**.
      8 tests Pest en `tests/Feature/PieceStatusTransitionsTest.php`.
- [x] **Exportación CSV** (`App\Services\Export\ControlTableExporter`): botones en el panel
      (tabla de control, revisiones, rechazos) y comando `php artisan accesopro:export`
      (escribe `tabla_control.csv`, `revisiones.csv` y `rechazos.csv` en
      `storage/app/private/exports/`). Formato para Excel en español (UTF-8 con BOM, `;`,
      coma decimal). Exportado en real: 17 piezas, 18 revisiones, 8 rechazos.
      4 tests Pest en `tests/Feature/ControlExportTest.php`.
- [x] **Página "Fuentes" en el panel** (D28): añadir temas en PDF y clases por enlace de
      YouTube, y botón "Generar contenido" (con límite de ideas/clips) que encola el pipeline
      (`RunSourcePipeline`) sin tocar la terminal. La lógica del pipeline salió de
      `RunCommand` a `App\Services\Pipeline\PipelineRunner`, compartida por el comando y el
      botón. Cada fuente tiene ahora sus propios archivos (`Source::storageDir()`,
      `classVideoPath()`, `documentTextPath()`); el material original de T1_S01 y la clase
      ya procesada siguen funcionando igual. Alcance limitado a Matemáticas GS Comunitat
      Valenciana (nota visible en la página). Requiere `php artisan queue:work --timeout=3600`.
- [x] **Relanzar sin duplicados — verificado en real (31/10).**
      `accesopro:run T1_S01 --limit=0 --class=<clase> --clip-limit=0` sobre todo lo ya
      publicado (7 ideas + 10 clips): la base de datos quedó idéntica antes y después
      (17 piezas, 18 revisiones, 37 renders, 20 publicaciones, 59 eventos de coste,
      2,728059 € en total). **Cero llamadas de pago y cero duplicados**: cada paso detecta
      que ya hizo su trabajo y sale. También confirma que el refactor a `PipelineRunner` no
      rompió el pipeline real.
- [ ] Ejecución completa desde cero (base de datos vacía) con un solo disparador
- [x] **Código preparado para Cloud** (D29): temario oficial dentro de la app, texto de
      los PDF en la base de datos, comando `accesopro:user`, sección de Cloud en
      `.env.example`. Lista de comprobación en `docs/DESPLIEGUE_CLOUD.md`.
- [ ] Deploy en Laravel Cloud (binarios estáticos, worker, límite de gasto). **Worker
      obligatorio** para el botón "Generar" de `/fuentes`: activar la cola gestionada con
      timeout de 3600 s (y `DB_QUEUE_RETRY_AFTER`/`retry_after` por encima). En local basta
      con `composer run dev` (arranca la web y el worker a la vez).

## Fase 5 – Calidad final, YouTube y Nivel 2 barato
- [x] `YouTubePublisher` (D35): sube los dos formatos como **no listados** con título,
      descripción, etiquetas y miniatura (solo 16:9; los Shorts no la admiten por API).
      Botón **"Conectar YouTube"** (OAuth, token cifrado en `app_settings`) y botón
      **"Subir a YouTube"** por pieza en la tabla de control (job `PublishToYouTube`, por la
      cola). Control de cuota diaria (`YouTubeQuota`, unidades en `cost_events` a 0 €).
      Idempotente por formato. Si YouTube fuerza el vídeo a privado (proyecto sin auditar),
      queda publicado con un aviso visible en la tabla. 9 tests Pest con la API simulada.
- [ ] Subida real al canal de prueba: registrar `{APP_URL}/youtube/callback` como URI de
      redirección, activar YouTube Data API v3, conectar y subir ≥3 vídeos y ≥3 clips.
- [ ] Aprobación humana activable (`REQUIRE_HUMAN_APPROVAL`) con botones en Filament
- [ ] A/B: dos ganchos + juez con lógica explicada
- [ ] Límite de gasto diario visible en el panel

## Fase 6 – Entregables
- [ ] README no técnico
- [ ] Documento de arquitectura (2 páginas)
- [x] Ejemplos de rechazo exportados (`accesopro:export` → `rechazos.csv`; volver a
      exportar justo antes de entregar)
- [x] Tabla de control exportada (`accesopro:export` → `tabla_control.csv`)
- [ ] Vídeo demo ≤ 5 min
- [ ] Correo de entrega con enlaces públicos

## Notas
- Enlace de la clase grabada **recibido** (2026-10-30): guardado en `materiales/clase_link.txt`
  → `https://www.youtube.com/watch?v=fQBP3hY39oU`.
- Brand book y logo **recibidos** (2026-10-31): en `materiales/brand/Brand Book/` y
  `materiales/brand/Brand Logo/` (confidencial, fuera del repo). Pendiente: construir las
  plantillas de `resources/brand/` (Fase 2, `SceneBuilder`/`ThumbnailMaker`) a partir de estos
  archivos — colores en `Brand Book/Images/Brand Color.png` y
  `Brand Book/Primary Green_pantone.jpeg`, logo vectorial en
  `Brand Logo/AccesoPro Logo Vector.svg`.
- Material de origen (temario TXT, JSON del podcast, PDF del enunciado, PDF del temario
  oficial) ya está en `materiales/` (confidencial, fuera del repo vía `.gitignore`).
