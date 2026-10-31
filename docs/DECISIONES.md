# Registro de decisiones

Formato: decisión · motivo · consecuencias. Añadir nuevas al final.

**D1 · Laravel en lugar de Python/n8n.** El autor domina Laravel y dispone de Laravel Cloud.
Colas, cadenas, reintentos, `ShouldBeUnique` y Filament cubren gran parte del Nivel 2 casi gratis.

**D2 · Prism PHP detrás de `LlmGateway`.** Un solo cliente multi-proveedor; cambiar Gemini ↔
Claude es cambiar el `.env`. Los jobs piden un rol (`generator`/`reviewer`), no un modelo.

**D3 · Gemini gratis solo con material público.** El plan gratuito usa entradas/salidas para
mejorar productos de Google y permite revisión humana; el material de AccesoPro es confidencial.
Para procesar `materiales/` se usa Claude o Gemini de pago. En producción (usuarios en España) el
plan gratuito tampoco serviría por restricciones regionales.

**D4 · Voz clonada obligatoria (ElevenLabs IVC).** El enunciado la exige en el Nivel 1 y dice que
evalúan saber montar la clonación. Sin avatar (opcional, caro y lento).

**D5 · Drive antes que YouTube.** Iterar la calidad viendo resultados en Drive. `Publisher` como
interfaz permite activar YouTube al final sin tocar el pipeline. El mismo proyecto de Google
Cloud/OAuth sirve para ambos (añadir scope `youtube.upload`).

**D6 · YouTube: riesgo de bloqueo en privado.** Proyectos de API sin auditar (creados después
del 28/07/2020) suben vídeos forzados a privado. La auditoría no da tiempo. Plan B: subir por API
igualmente (demuestra la automatización), documentarlo, y compartir los vídeos de otra forma
(privados compartidos con el evaluador o enlaces de Drive). Cuota: 10.000 unidades/día;
`videos.insert` costaba 1.600 (comprobar el valor actual en Cloud Console).

**D7 · Sin Chromium.** Laravel Cloud Starter no ofrece Puppeteer (es add-on de Private Cloud).
Visuales con plantillas + SVG → ImageMagick, y montaje con FFmpeg.

**D8 · Verificación numérica determinista.** El revisor extrae afirmaciones `{expresion,
resultado}` y PHP (brick/math) las recalcula exactamente. El LLM no es la única garantía de
corrección; es el diferenciador frente a una "revisión genérica".

**D9 · Desarrollo y render en local; Cloud para la ejecución final.** El crédito de 5 $ del plan
Starter no da para iterar renders en la nube.

**D10 · Subtítulos desde el alineamiento de ElevenLabs.** El endpoint `with-timestamps` da tiempos
por carácter; no hace falta transcribir el audio generado.

**D11 · Estructura monorepo: `automatizacionVideos/` (raíz, docs + materiales) contiene
`accesopro/` (proyecto Laravel).** `CLAUDE.md` y `docs/` viven en la raíz del repo (ya era así
al empezar); el proyecto Laravel se crea dentro como subcarpeta, tal como indica
`SETUP_WINDOWS.md`. No se duplica `CLAUDE.md` dentro de `accesopro/`: se borró el que generó
el propio `laravel new` (plantilla de Laravel Boost) para evitar instrucciones contradictorias.

**D12 · Pest en vez de PHPUnit.** El esqueleto de Laravel 12 trae PHPUnit por defecto; el
`CLAUDE.md` pide Pest, así que se añadió `pestphp/pest` + `pest-plugin-laravel` sobre el
proyecto nuevo.

**D13 · CA bundle manual en PHP de php.new (Windows).** La instalación de PHP 8.4 vía
`php.new` no trae `curl.cainfo`/`openssl.cafile` configurados, así que toda llamada HTTPS
(Gemini, ElevenLabs, Google) fallaba con "SSL peer certificate ... not OK". Se descargó
`cacert.pem` de curl.se y se apuntó desde `php.ini`. Documentado para no perder tiempo si se
reinstala el entorno.

**D14 · Modelos Gemini actualizados.** `gemini-2.5-flash` y `gemini-2.5-pro` (los que traía el
zip de Fase 0) ya no están disponibles para cuentas nuevas; Google indica como reemplazo
`gemini-3.8-flash` (generador) y `gemini-3.1-pro-preview` (revisor). Actualizado en `.env`,
`.env.example` y `config/accesopro.php`. Nota operativa: la capa gratuita de Gemini tiene
cuota diaria baja y se agotó solo con las pruebas de `accesopro:check`; para Fase 1 en
adelante, si se sigue en plan gratuito, limitar las llamadas de prueba o pasar a clave de
pago / Claude como generador-revisor alterno (ver D2).

**D15 · Generador y revisor pasan a Claude (Anthropic), no Gemini.** La capa gratuita de
Gemini se agotó durante las pruebas y, de todas formas, D3 prohíbe usarla con material
confidencial. En vez de activar facturación en Gemini, Hector eligió conseguir una clave de
Anthropic: `LLM_GENERATOR_PROVIDER/MODEL=anthropic/claude-sonnet-5-5` y
`LLM_REVIEWER_PROVIDER/MODEL=anthropic/claude-opus-5-5`. Gemini queda en `.env` como
alternativa comentada (sirve para pruebas rápidas con `docs/TEMARIO_OFICIAL.md`, nunca con
`materiales/`). Las cifras de coste de Claude en `config/accesopro.php.prices_usd_per_mtok`
vienen de agregadores de terceros (no de la página oficial de Anthropic): verificar antes de
reportar coste real en la tabla de control.

**D16 · Esquemas estructurados sin `minItems`/`maxItems`/`minimum`/`maximum`.** La API de
Anthropic (usada vía Prism) rechaza esos campos en JSON Schema salvo `minItems`/`maxItems`
0 o 1: `output_config.format.schema: For 'array' type, 'minItems' values other than 0 or 1
are not supported`. Se quitaron de `IdeasSchema`, `ScriptSchema` y `ReviewSchema`; las
restricciones ("entre 5 y 7 ideas", "exactamente 2 ganchos", "1-30 segundos") quedan solo en
el texto del prompt. Si se vuelve a Gemini, estas restricciones sí se podrían reintroducir en
el esquema (Gemini es más permisivo), pero se dejan fuera para que el mismo esquema sirva
para ambos proveedores sin ramas por proveedor.

**D17 · `accesopro:run` añade `--limit` (no estaba en la especificación original).**
Por defecto procesa solo 1 idea (guion + revisión) por ejecución, para controlar el gasto
mientras se valida la calidad del contenido; `--limit=0` o uno mayor que el nº de ideas
procesa todas. Se documenta aquí porque es una desviación consciente del comando descrito en
`ARQUITECTURA.md` (que no menciona límite), motivada por control de costes en desarrollo, no
por una limitación técnica.

**D18 · Todo el texto del vídeo lo pone FFmpeg (`drawtext`/`ass`), no GD.** El GD que trae
`php.new` en Windows está compilado sin FreeType (`gd_info()['FreeType Support']` vacío) ni
JPEG: `imagettftext`/`imagettfbbox` no existen. Probamos primero dibujar el texto de cada
escena con GD y falló. En vez de pelear con una DLL de GD distinta, se cambió de
arquitectura: `SceneBuilder` (GD) solo genera el fondo de marca (color + logo, sin texto,
cosa que GD sí hace bien); `FfmpegRenderer` pone el texto de cada escena con el filtro
`drawtext` (libfreetype) y los subtítulos con el filtro `ass` (libass) — este FFmpeg (el que
se instaló con winget) sí trae ambas librerías. Efecto secundario positivo: toda la
tipografía queda en un solo sitio con el mismo motor de render, en vez de repartida entre
PNGs estáticos (GD) y subtítulos quemados (FFmpeg) con posible inconsistencia visual. Las
posiciones verticales de "texto de escena" + "fórmula" se calculan en PHP (recuento de
líneas × alto de línea) en vez de con expresiones `y=(h-text_h)/2` de FFmpeg, porque cada
`drawtext` solo conoce su propio `text_h`: dos bloques "centrados" de forma independiente se
solapaban en cuanto el texto ocupaba más de una línea.

**D19 · Logo real fuera de git; colores y tipografía (Inter) sí van en el repo.** Igual que
con el resto de `materiales/brand/`, el PNG del logo de AccesoPro se lee en tiempo de
ejecución desde `materiales/` (confidencial) vía `config('accesopro.brand.logo_path')`, y
`SceneBuilder` sigue funcionando sin logo si ese archivo no existe (p. ej. al clonar el
repo). Los colores corporativos (son solo números hexadecimales, no un activo de marca en sí
mismos) y la tipografía Inter (fuente open-source de Google, licencia SIL OFL) sí se
commitean: colores en `config/accesopro.php`, fuente en `resources/brand/fonts/`.

**D20 · yt-dlp no fusiona vídeo+audio si FFmpeg no está en el PATH del proceso que lo
lanza.** La primera descarga con `--merge-output-format mp4` dejó dos archivos sueltos
(`.f399.mp4` + `.f140.m4a`) en vez de un `clase.mp4`: yt-dlp necesita `ffmpeg` en el PATH
para fusionar pistas y, al lanzarlo en segundo plano sin el PATH ya ajustado, no lo
encontró. Se fusionó a mano con `ffmpeg -i video -i audio -c copy` (sin recodificar, rápido).
`FetchClassVideo` asume que quien lo ejecuta ya tiene FFmpeg en el PATH (como el resto del
proyecto). YouTube no bloqueó la descarga en este caso (clase pública/no listada accesible).

**D21 · Audio a 32kbps, no 64kbps, para la transcripción.** El plan gratuito de Groq rechaza
(`413 Request Entity Too Large`) archivos de más de ~25 MB, aunque la documentación general
hable de un límite de 100 MB (ese es para planes de pago). 64kbps mono daban 28 MB para los
58 minutos de clase; a 32kbps son 14 MB. Para voz hablada, Whisper no pierde precisión
relevante a 32kbps/16kHz.

**D22 · Transcripción de la clase: llamada directa a la API de Groq, no vía Prism.** El
`Prism\Groq\Handlers\Audio` solo reenvía `model`, `language`, `prompt`, `response_format` y
`temperature` al endpoint de transcripción; no reenvía `timestamp_granularities`, que es
justo lo que necesitamos para marcas de tiempo por palabra (imprescindibles para cortar los
clips en límites de frase). Se construyó `App\Services\Transcription\TranscriptionClient`
con el patrón de `ElevenLabsClient` (llamada HTTP directa, no Prism) en vez de forzar el uso
de Prism para algo que no soporta. Confirmado en real: `whisper-large-v3-turbo` con
`timestamp_granularities[]=word` devuelve `words: [{word, start, end}]` exactos; `segments`
viene vacío si no se pide también `segment` (no lo necesitamos: construimos las frases
nosotros mismos con `TranscriptSegmenter`, agrupando por puntuación).

**D23 · El primer prompt de `DetectMoments` dio solo 1/7 clips aprobados; hubo que
endurecerlo.** La clase es muy interactiva (chat, pizarra, alumnos), así que la mayoría de
momentos "temáticamente buenos" empiezan a mitad de frase o dependen de algo visual no
descrito en voz alta — el revisor los rechazó correctamente por `autonomia`, pero dejaba
muy pocos aprobados para cubrir el entregable de "≥3 clips". Se reescribió
`resources/prompts/detect_moments.md` para pedir explícitamente que la frase de inicio
*introduzca* el tema (no continúe algo anterior), prohibir conectores de continuación
("entonces", "bueno", "vale, pues...") y referencias deícticas ("esto", "esta tablita"), y
pedir más candidatos (8-12 en vez de 5-8) para tener margen. Segundo intento: 3/10
aprobados. Lección: con contenido muy conversacional, generar más candidatos y ser muy
explícito sobre qué hace que un inicio sea "autónomo" importa más que el número de
candidatos pedidos inicialmente.

**D24 · `GenerateMetadata`, `GenerateThumbnail` y `Publish` se movieron a
`app/Jobs/Common/`.** Estaban en `app/Jobs/Texto/` pero no tienen nada específico de esa
fuente (operan sobre cualquier `Piece`, sea `kind=texto` o `kind=clip`): los clips de la
clase los reutilizan sin cambios. Coincide con la estructura de carpetas de
`docs/ARQUITECTURA.md` (`Jobs/{Texto,Clase,Common}/`). De paso, `DrivePublisher::remotePath`
pasó de tener "texto" fijo en la ruta a usar `$piece->kind`, para que los clips se suban a
`.../clase/...` en vez de `.../texto/...`.

**D25 · Panel de control sin Filament: login + tabla con Laravel puro.** Se instaló
Filament v5.11 (compatible con Laravel 13) y se construyó el recurso `Piece`, pero
`Illuminate\Support\Number::format()` — que Filament llama internamente en la paginación de
cualquier tabla, en la exportación y en el contador de notificaciones — exige la extensión
`intl`, que no se pudo instalar en este PHP (ver nota técnica abajo). No es evitable por
código de aplicación: pasa dentro de las vistas propias del paquete. En vez de parchear
vistas de un paquete de terceros una por una (frágil: cualquier función nueva de Filament
puede volver a chocar con lo mismo), se desinstaló Filament y se construyó login + tabla de
control con Laravel puro, sin dependencias nuevas:
- `App\Http\Controllers\Auth\LoginController` + rutas `/login`, `/logout` (Auth nativo de
  Laravel, usuario en la tabla `users` ya existente).
- `App\Http\Controllers\ControlPanelController` + vista `resources/views/control/index.blade.php`:
  la misma tabla (título, tipo, estado, origen, coste, enlaces 9:16/16:9, motivo de
  descarte) con filtros por estado y tipo, paginación nativa de Laravel (no usa
  `Number::format`, así que no choca con `intl`).
- "/" redirige a `/panel`, que exige sesión (middleware `auth`); sin sesión redirige a
  `/login`. `resources/views/layouts/app.blade.php` da el estilo común (colores de marca,
  sin Tailwind/Vite para no añadir una dependencia de build en un entorno ya frágil).

*Nota técnica — por qué falló `intl`*: se probó copiar `php_intl.dll` + DLLs de ICU desde el
paquete oficial de windows.php.net (variante vs17, la única que existe para PHP 8.4.0) y
también se instaló el redistribuible de Visual C++ 2015-2022; siguió sin cargar
("No se puede encontrar el módulo especificado"). El build de `php.new` reporta un ABI no
estándar (`API20240924,NTS,VS16`) que no coincide con ningún `php_intl.dll` precompilado
disponible. Si se reinstala el entorno con Laravel Herd (opción recomendada en
`SETUP_WINDOWS.md`) en vez de `php.new`, probablemente sí traiga `intl` y Filament volvería
a ser viable sin este rodeo.

**D26 · Las transiciones de estado posteriores a la revisión viven en `Piece`.** Hasta el
31/10 ningún job movía la pieza más allá de `revisada`, aunque estuviera montada y publicada.
Se añadieron `Piece::approve()`, `markRendered()` y `markPublished()`. Cada uno solo avanza
desde el estado anterior y cuando la condición se cumple de verdad: los dos formatos
renderizados, o los dos publicados en el destino activo (`PUBLISH_TARGET`). Así una pieza
descartada nunca avanza y relanzar el pipeline no hace retroceder a una `publicada`.
`approve()` es automático (el veredicto del revisor basta) y es justo donde entrará la
aprobación humana del Nivel 2. Las piezas antiguas se corrigieron con una migración de
datos idempotente, no a mano.

**D27 · CSV para Excel en español.** La exportación usa UTF-8 con BOM, separador `;` y coma
decimal, para que quien evalúa (no técnico) pueda abrirla con doble clic en Excel con los
acentos y los importes bien. Un mismo servicio (`ControlTableExporter`) alimenta las
descargas del panel y el comando `accesopro:export`.
