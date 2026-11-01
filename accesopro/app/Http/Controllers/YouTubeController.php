<?php

namespace App\Http\Controllers;

use App\Jobs\Common\PublishToYouTube;
use App\Models\Piece;
use App\Models\Publication;
use App\Services\Publishing\YouTube\YouTubeAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/** Conexión con el canal de YouTube (OAuth) y botón "Subir a YouTube" de la tabla de control. */
final class YouTubeController extends Controller
{
    public function __construct(private readonly YouTubeAuth $auth) {}

    public function connect(Request $request): RedirectResponse
    {
        if (! $this->auth->isConfigured()) {
            return redirect()->route('control.index')
                ->withErrors(['youtube' => 'Faltan las credenciales OAuth de Google en el .env (YOUTUBE_CLIENT_ID/SECRET o las de Drive).']);
        }

        $state = Str::random(40);
        $request->session()->put('youtube_oauth_state', $state);

        return redirect()->away($this->auth->authUrl($this->callbackUrl(), $state));
    }

    public function callback(Request $request): RedirectResponse
    {
        $expected = $request->session()->pull('youtube_oauth_state');

        if (! $expected || ! hash_equals($expected, (string) $request->query('state'))) {
            return redirect()->route('control.index')->withErrors(['youtube' => 'La respuesta de Google no corresponde a esta sesión. Vuelve a intentarlo.']);
        }

        if ($request->filled('error') || ! $request->filled('code')) {
            return redirect()->route('control.index')->withErrors(['youtube' => 'Conexión con YouTube cancelada ('.$request->query('error', 'sin código').').']);
        }

        try {
            $channel = $this->auth->connect((string) $request->query('code'), $this->callbackUrl());
        } catch (Throwable $e) {
            return redirect()->route('control.index')->withErrors(['youtube' => $e->getMessage()]);
        }

        return redirect()->route('control.index')->with('status', "YouTube conectado: canal «{$channel}».");
    }

    /**
     * URL de vuelta desde Google, construida con APP_URL y no con la petición: detrás del
     * proxy de Laravel Cloud la petición puede llegar como http y Google exige que coincida
     * exactamente con la URI registrada en el cliente OAuth.
     */
    private function callbackUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/youtube/callback';
    }

    public function disconnect(): RedirectResponse
    {
        $this->auth->disconnect();

        return redirect()->route('control.index')->with('status', 'YouTube desconectado.');
    }

    public function publish(Piece $piece): RedirectResponse
    {
        if (! $this->auth->isConnected()) {
            return back()->withErrors(['youtube' => 'Conecta primero el canal con «Conectar YouTube».']);
        }

        if (! $piece->canPublishToYouTube()) {
            return back()->withErrors(['youtube' => "«{$piece->idea_title}» no tiene vídeo montado: solo se suben piezas renderizadas o publicadas."]);
        }

        $pending = 0;

        foreach (Piece::VIDEO_FORMATS as $format) {
            $publication = Publication::firstOrNew(['piece_id' => $piece->id, 'target' => 'youtube', 'format' => $format]);

            if ($publication->status === 'publicada') {
                continue;
            }

            $publication->fill(['status' => 'pendiente', 'error' => null])->save();
            $pending++;
        }

        if ($pending === 0) {
            return back()->with('status', "«{$piece->idea_title}» ya está en YouTube.");
        }

        PublishToYouTube::dispatch($piece);

        return back()->with('status', "Subida de «{$piece->idea_title}» a YouTube en cola. La tabla se actualiza sola.");
    }
}
