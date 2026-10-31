# Instalación en Windows (guía para humanos)

## 1. PHP 8.4 y Composer

Tu PHP actual es 8.1.25 (probablemente de XAMPP). Laravel reciente y Prism necesitan ≥ 8.2.

**Opción A (recomendada): Laravel Herd para Windows.** Instala PHP 8.4, Composer y el instalador
de Laravel, y permite cambiar de versión desde su interfaz. https://herd.laravel.com

**Opción B: php.new** (PowerShell como administrador):
```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.4'))
```

Después, **abre una terminal nueva** y comprueba:
```powershell
where.exe php      # la primera ruta NO debe ser la de XAMPP
php -v             # 8.4.x
composer -V
```
Si sigue saliendo XAMPP primero: Configuración → Variables de entorno → `Path` → quita o baja
`C:\xampp\php`.

## 2. FFmpeg
```powershell
winget install --id Gyan.FFmpeg -e
# terminal nueva
ffmpeg -version
```

## 3. Proyecto Laravel nuevo

El proyecto creado con PHP 8.1 es una versión antigua (de ahí las alertas de seguridad de
`composer audit`). Bórralo y créalo de nuevo:
```powershell
cd C:\Users\Hector\Documents\CodingProjects\AutomatizacionVideos\automatizacionVideos
Remove-Item -Recurse -Force accesopro
composer create-project laravel/laravel accesopro
cd accesopro
composer require prism-php/prism google/apiclient masbug/flysystem-google-drive-ext brick/math
php artisan vendor:publish --tag=prism-config
```

## 4. Contexto para la IA y material

- Copia `CLAUDE.md` en la raíz del proyecto y la carpeta `docs/`.
- Crea `materiales/` y mete ahí el TXT, el JSON, los PDF y (cuando lleguen) la clase y el brand book.
- Añade a `.gitignore`:
  ```
  /materiales
  /storage/app/private/runs
  ```
- Abre la CLI de Claude en la raíz del proyecto: lee `CLAUDE.md` automáticamente.
  Primera instrucción sugerida: *"Lee docs/PLAN.md y completa la Fase 0: integra los archivos
  de accesopro-fase0.zip (están en _fase0/) y deja accesopro:check funcionando."*

## 5. Claves y servicios

- **Gemini**: clave en Google AI Studio → `GEMINI_API_KEY`.
- **ElevenLabs**: plan Starter (incluye clonación) → `ELEVENLABS_API_KEY`. Guarda recibos (reembolso hasta 30 €).
- **Google Drive (OAuth)**:
  1. Google Cloud Console → proyecto nuevo → activar Google Drive API.
  2. Pantalla de consentimiento: tipo Externo, añadirte como usuario de prueba.
  3. Credenciales → ID de cliente OAuth → Aplicación web → URI de redirección
     `https://developers.google.com/oauthplayground`.
  4. OAuth Playground → engranaje → "Use your own OAuth credentials" → scope
     `https://www.googleapis.com/auth/drive.file` → canjear código → copiar refresh token.
  5. En modo Testing el refresh token caduca a los 7 días.

## 6. Voz clonada

Graba 1–2 minutos (habitación silenciosa, distancia constante, tono de explicación, sin música),
guárdalo en `storage/muestras/` y ejecuta:
```powershell
php artisan migrate
php artisan accesopro:check
php artisan accesopro:voice:clone storage/muestras/voz1.mp3 storage/muestras/voz2.mp3
# copia el voice_id al .env → ELEVENLABS_VOICE_ID
php artisan accesopro:voice:test
```
