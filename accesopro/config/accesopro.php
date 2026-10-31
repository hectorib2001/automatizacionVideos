<?php

return [

    // Cada rol puede usar un proveedor y modelo distinto. Cambiar de Gemini a
    // Claude es solo cambiar estas variables en el .env.
    'llm' => [
        'generator' => [
            'provider' => env('LLM_GENERATOR_PROVIDER', 'anthropic'),
            'model' => env('LLM_GENERATOR_MODEL', 'claude-sonnet-5-5'),
        ],
        'reviewer' => [
            'provider' => env('LLM_REVIEWER_PROVIDER', 'anthropic'),
            'model' => env('LLM_REVIEWER_MODEL', 'claude-opus-5-5'),
        ],

        // USD por millón de tokens: [entrada, salida]. 0 = plan gratuito.
        // Cifras de Claude tomadas de agregadores de precios (no de la página oficial de
        // Anthropic): VERIFICAR en https://platform.claude.com/docs/en/about-claude/pricing
        // antes de confiar en la tabla de control para el informe de coste real.
        'prices_usd_per_mtok' => [
            'claude-sonnet-5-5' => [2, 10],
            'claude-opus-5-5' => [4, 20],
            'gemini-3.8-flash' => [0, 0],
            'gemini-3.1-pro-preview' => [0, 0],
        ],
    ],

    'elevenlabs' => [
        'api_key' => env('ELEVENLABS_API_KEY'),
        'base_url' => 'https://api.elevenlabs.io/v1',
        'model' => env('ELEVENLABS_MODEL', 'eleven_multilingual_v2'),
        'voice_id' => env('ELEVENLABS_VOICE_ID'),
        // Coste real por 1.000 caracteres = precio del plan / créditos del plan.
        // Ajústalo a tu plan (verifica los créditos actuales del Starter).
        'usd_per_1k_chars' => (float) env('ELEVENLABS_USD_PER_1K_CHARS', 0.17),
    ],

    // MP4 ya descargado de la clase (yt-dlp o subido a mano). FetchClassVideo lo usa si ya
    // existe; si no, intenta yt-dlp y lo guarda aquí (ver docs/ARQUITECTURA.md).
    'class_video_path' => env('CLASS_VIDEO_PATH', rtrim(env('MATERIALES_PATH', base_path('../materiales')), '/').'/clase/clase.mp4'),

    'yt_dlp_bin' => env('YT_DLP_BIN', 'yt-dlp'),

    // Transcripción de la clase grabada (ver docs/ARQUITECTURA.md). whisper-large-v3-turbo:
    // $0.04/hora de audio, precio oficial de groq.com/pricing (verificar antes de confiar
    // en el coste real reportado si cambia).
    'groq' => [
        'api_key' => env('GROQ_API_KEY'),
        'base_url' => 'https://api.groq.com/openai/v1',
        'whisper_model' => env('GROQ_WHISPER_MODEL', 'whisper-large-v3-turbo'),
        'usd_per_hour' => (float) env('GROQ_WHISPER_USD_PER_HOUR', 0.04),
    ],

    'usd_to_eur' => (float) env('USD_TO_EUR', 0.92),
    'daily_budget_eur' => (float) env('DAILY_BUDGET_EUR', 5),

    // Material confidencial de Grupo Mentor (temario, podcast, clase, brand book).
    // Vive fuera del repositorio de código, en materiales/ (ver .gitignore raíz).
    'materiales_path' => env('MATERIALES_PATH', base_path('../materiales')),

    // Temario oficial (público, DOGV) con el que el revisor compara todo. Vive dentro de la
    // app para que viaje con el despliegue: docs/TEMARIO_OFICIAL.md es la copia de
    // documentación y un test comprueba que ambas sean idénticas.
    'official_syllabus_path' => env('OFFICIAL_SYLLABUS_PATH', resource_path('syllabus/temario_oficial.md')),

    // Imagen de marca. Colores y tipografía (Inter) son públicos y van en el repo
    // (resources/brand/fonts); el logo real es de Grupo Mentor y se lee en tiempo de
    // ejecución desde materiales/ (confidencial, nunca se commitea). Si no está disponible
    // (p. ej. al clonar el repo sin materiales/), SceneBuilder sigue funcionando sin logo.
    'brand' => [
        'logo_path' => env('BRAND_LOGO_PATH', rtrim(env('MATERIALES_PATH', base_path('../materiales')), '/')
            .'/brand/Brand Logo/AccesoPro-Logo-Vector-recorte.png'),
        'font_path' => resource_path('brand/fonts/Inter-Variable.ttf'),
        'colors' => [
            'dark_blue' => '#111E32',
            'primary_green' => '#59C267',
            'light_green' => '#DDF2E8',
            'soft_blue' => '#25394F',
            'light_blue' => '#67717E',
            'white' => '#FFFFFF',
        ],
    ],

    'ffmpeg' => env('FFMPEG_BIN', 'ffmpeg'),
    'ffprobe' => env('FFPROBE_BIN', 'ffprobe'),

    // drive | youtube
    'publish_target' => env('PUBLISH_TARGET', 'drive'),
    'drive_root' => env('GOOGLE_DRIVE_ROOT', 'AccesoPro'),
];
