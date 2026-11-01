<?php

namespace App\Services\Publishing\YouTube;

use App\Models\AppSetting;
use Google\Client;
use Google\Service\YouTube;
use RuntimeException;

/**
 * OAuth de YouTube. Usa un refresh token propio (scope `youtube.upload`), separado del de
 * Drive (`drive.file`): conectar YouTube no toca la publicación en Drive (ver D35).
 */
final class YouTubeAuth
{
    public const TOKEN_KEY = 'youtube.refresh_token';

    public const CHANNEL_KEY = 'youtube.channel_title';

    /** youtube.upload basta para videos.insert y thumbnails.set; readonly da el nombre del canal. */
    public const SCOPES = [YouTube::YOUTUBE_UPLOAD, YouTube::YOUTUBE_READONLY];

    public function isConfigured(): bool
    {
        return filled(config('accesopro.youtube.client_id')) && filled(config('accesopro.youtube.client_secret'));
    }

    public function isConnected(): bool
    {
        return $this->refreshToken() !== null;
    }

    public function channelTitle(): ?string
    {
        return AppSetting::get(self::CHANNEL_KEY);
    }

    /** El token conectado desde el panel manda; YOUTUBE_REFRESH_TOKEN es la alternativa por .env. */
    public function refreshToken(): ?string
    {
        return AppSetting::get(self::TOKEN_KEY) ?? (config('accesopro.youtube.refresh_token') ?: null);
    }

    public function authUrl(string $redirectUri, string $state): string
    {
        $client = $this->baseClient($redirectUri);
        $client->setState($state);

        return $client->createAuthUrl();
    }

    /** Canjea el código de Google, guarda el refresh token y devuelve el nombre del canal. */
    public function connect(string $code, string $redirectUri): string
    {
        $client = $this->baseClient($redirectUri);
        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            throw new RuntimeException('Google rechazó el código: '.($token['error_description'] ?? $token['error']));
        }

        if (empty($token['refresh_token'])) {
            throw new RuntimeException('Google no devolvió refresh token. Quita el acceso de la app en myaccount.google.com/permissions y vuelve a conectar.');
        }

        $channels = (new YouTube($client))->channels->listChannels('snippet', ['mine' => true]);
        $title = ($channels->getItems()[0] ?? null)?->getSnippet()?->getTitle();

        if ($title === null) {
            throw new RuntimeException('La cuenta de Google no tiene canal de YouTube. Crea uno y vuelve a conectar.');
        }

        AppSetting::put(self::TOKEN_KEY, $token['refresh_token']);
        AppSetting::put(self::CHANNEL_KEY, $title);

        return $title;
    }

    public function disconnect(): void
    {
        AppSetting::put(self::TOKEN_KEY, null);
        AppSetting::put(self::CHANNEL_KEY, null);
    }

    /** Cliente autenticado, listo para llamar a la API. */
    public function client(): Client
    {
        $refreshToken = $this->refreshToken()
            ?? throw new RuntimeException('YouTube no está conectado: pulsa «Conectar YouTube» en la tabla de control.');

        $client = $this->baseClient();
        $token = $client->fetchAccessTokenWithRefreshToken($refreshToken);

        if (isset($token['error'])) {
            throw new RuntimeException('No se pudo renovar el acceso a YouTube ('.($token['error_description'] ?? $token['error']).'). Vuelve a pulsar «Conectar YouTube».');
        }

        return $client;
    }

    private function baseClient(?string $redirectUri = null): Client
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Faltan YOUTUBE_CLIENT_ID/SECRET (o GOOGLE_DRIVE_CLIENT_ID/SECRET) en el .env.');
        }

        $client = new Client;
        $client->setClientId(config('accesopro.youtube.client_id'));
        $client->setClientSecret(config('accesopro.youtube.client_secret'));
        $client->setScopes(self::SCOPES);
        $client->setAccessType('offline');
        $client->setPrompt('consent'); // fuerza un refresh token nuevo en cada conexión

        if ($redirectUri !== null) {
            $client->setRedirectUri($redirectUri);
        }

        return $client;
    }
}
