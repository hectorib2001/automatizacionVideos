# Despliegue en Laravel Cloud: lista de comprobación

Plan Starter (5 $ de crédito, disco efímero, scale-to-zero). Ver D7, D9, D28 y D29.

## 0. Antes de conectar el repositorio

- [ ] **La app está en la subcarpeta `accesopro/`**, no en la raíz del repo. Al crear la
      aplicación, comprueba si Cloud permite indicar el directorio raíz de la app. Si no lo
      permite: o se mueve el contenido de `accesopro/` a la raíz del repo, o se publica un
      repo aparte solo con esa carpeta (`git subtree split --prefix=accesopro`).
- [x] El temario oficial viaja con la app (`resources/syllabus/temario_oficial.md`).
- [x] Los PDF subidos se guardan como texto en la base de datos, no en disco.

## 1. Entorno

- [ ] PHP 8.4.
- [ ] **Base de datos gestionada** (no SQLite: el disco se borra en cada despliegue).
      Adjúntala al entorno para que Cloud configure las variables `DB_*`; verifica que
      aparecen en Variables.
- [ ] Variables de entorno: ver la sección "LARAVEL CLOUD" al final de
      `accesopro/.env.example`. Imprescindibles: `APP_ENV=production`, `APP_DEBUG=false`,
      claves de Anthropic, ElevenLabs (+ `ELEVENLABS_VOICE_ID`), Groq y Google Drive
      (`GOOGLE_DRIVE_CLIENT_ID/SECRET/REFRESH_TOKEN/ROOT`), `PUBLISH_TARGET=drive`,
      `DAILY_BUDGET_EUR`, `DB_QUEUE_RETRY_AFTER=3700`.
- [ ] Comando de despliegue: `php artisan migrate --force`.

## 2. Cola (necesaria para el botón "Generar")

- [ ] Activar la cola gestionada / worker con **timeout de 3600 s** (una clase de una hora
      tarda decenas de minutos). Sin worker, las fuentes se quedan "en cola".

## 3. Binarios (FFmpeg, FFprobe, yt-dlp), ver D30 y D32

- [ ] Comandos de build (Settings → Deployments → Build commands), sustituyendo los actuales:
      ```bash
      composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
      mkdir -p bin
      curl -fsSL https://github.com/BtbN/FFmpeg-Builds/releases/download/latest/ffmpeg-n8.1-latest-linuxarm64-gpl-8.1.tar.xz -o /tmp/ffmpeg.tar.xz
      tar -xJf /tmp/ffmpeg.tar.xz -C /tmp
      cp /tmp/ffmpeg-n8.1-latest-linuxarm64-gpl-8.1/bin/ffmpeg /tmp/ffmpeg-n8.1-latest-linuxarm64-gpl-8.1/bin/ffprobe bin/
      curl -fsSL https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp_linux_aarch64 -o bin/yt-dlp
      chmod +x bin/ffmpeg bin/ffprobe bin/yt-dlp
      ./bin/ffmpeg -version | head -1
      test "$(./bin/ffmpeg -hide_banner -filters | grep -cE '^ [^ ]+ (drawtext|ass) ')" -eq 2 || { echo "ERROR: FFmpeg sin drawtext/ass"; exit 1; }
      ```
      **No usar el build de johnvansickle** (7.0.2): está compilado sin `libharfbuzz` y, desde
      FFmpeg 6.1, `drawtext` lo necesita → "No such filter: 'drawtext'" (D32). La última línea
      hace fallar el build si vuelve a pasar con cualquier otro binario.
      Son binarios **arm64** porque el build de Cloud es `linux/arm64`. Si cambia a x86_64,
      usar `...-linux64-gpl-8.1.tar.xz` y `yt-dlp_linux`.
- [ ] Variables: `FFMPEG_BIN=bin/ffmpeg`, `FFPROBE_BIN=bin/ffprobe`, `YT_DLP_BIN=bin/yt-dlp`
      (rutas relativas a la app) y, con 512 MiB, `FFMPEG_THREADS=1` y
      `FFMPEG_PRESET=veryfast` (D31).
- [ ] Memoria: renderizar 1080×1920 con x264 puede no caber en 512 MiB. Si el worker muere
      sin error (OOM), subir la memoria del worker a 1–2 GiB.

## 4. Primer acceso

- [ ] Crear el usuario del panel desde la consola de comandos de Cloud:
      `php artisan accesopro:user tu@correo.com` (genera y muestra una contraseña segura;
      con `--password=...` la fijas tú). Sirve también para restablecerla.
- [ ] Comprobar conexiones: `php artisan accesopro:check`.

## Limitaciones conocidas en Cloud

- **YouTube suele bloquear yt-dlp desde IPs de centros de datos** ("Sign in to confirm
  you're not a bot"). La descarga de clases puede fallar en Cloud aunque funcione en local:
  el error queda registrado en la fuente, en la página "Fuentes".
- **`materiales/` no existe en Cloud** (es confidencial y no está en git): el tema original
  T1_S01 (podcast) y la clase ya procesada no se pueden regenerar allí, y los vídeos salen
  sin logo. Solo funcionan las fuentes subidas desde el panel.
- **La tabla de control empieza vacía**: las piezas ya publicadas están en la base de datos
  local. Sus enlaces de Drive y los CSV exportados siguen siendo válidos para la entrega.
- **Coste**: renders y clases largas consumen el crédito de cómputo. Probar con límite 1.
