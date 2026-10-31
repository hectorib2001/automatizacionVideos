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

## 3. Binarios (pendiente)

- [ ] FFmpeg/FFprobe y yt-dlp no vienen en Cloud. Sin FFmpeg no hay voz montada, render ni
      clips. Opción prevista: binarios estáticos incluidos en el despliegue
      (`mathiasgrimm/laravel-cloud-binaries` u otros) y `FFMPEG_BIN`, `FFPROBE_BIN`,
      `YT_DLP_BIN` apuntando a ellos. El FFmpeg debe traer `libass` y `libfreetype`
      (subtítulos y `drawtext`, ver D18).

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
