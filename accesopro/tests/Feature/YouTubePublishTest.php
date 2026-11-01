<?php

use App\Enums\PieceStatus;
use App\Jobs\Common\PublishToYouTube;
use App\Models\AppSetting;
use App\Models\CostEvent;
use App\Models\Piece;
use App\Models\Publication;
use App\Models\Render;
use App\Models\Source;
use App\Models\User;
use App\Services\Publishing\Publisher;
use App\Services\Publishing\YouTube\YouTubeApi;
use App\Services\Publishing\YouTube\YouTubeAuth;
use App\Services\Publishing\YouTubePublisher;
use Illuminate\Support\Facades\Queue;

/** API de YouTube simulada: guarda lo que se sube, sin red. */
final class FakeYouTubeApi implements YouTubeApi
{
    public array $uploads = [];

    public array $thumbnails = [];

    public string $privacy = 'unlisted';

    public bool $failThumbnail = false;

    public function upload(string $path, array $meta): array
    {
        $this->uploads[] = ['path' => $path, 'meta' => $meta];

        return ['id' => 'vid'.count($this->uploads), 'privacy' => $this->privacy];
    }

    public function setThumbnail(string $videoId, string $imagePath): void
    {
        if ($this->failThumbnail) {
            throw new RuntimeException('The authenticated user doesnt have permissions to upload and set custom video thumbnails.');
        }

        $this->thumbnails[] = $videoId;
    }
}

function renderedPiece(PieceStatus $status = PieceStatus::Publicada): Piece
{
    $source = Source::firstOrCreate(['code' => 'T1_S01'], ['type' => 'temario', 'title' => 'T1_S01']);
    $piece = Piece::create([
        'source_id' => $source->id,
        'kind' => 'texto',
        'uid' => 'yt-'.uniqid(),
        'idea_title' => 'Sumar fracciones',
        'status' => $status,
        'script' => ['metadata' => ['title' => 'Suma <fracciones> sin errores', 'description' => 'Aprende el mcm.', 'tags' => ['fracciones', 'mcm']]],
    ]);

    $dir = storage_path('framework/testing/yt');
    @mkdir($dir, 0775, true);

    foreach (['9x16' => 'mp4', '16x9' => 'mp4', 'thumb' => 'png'] as $format => $ext) {
        $path = "{$dir}/{$piece->uid}_{$format}.{$ext}";
        file_put_contents($path, 'x');
        Render::create(['piece_id' => $piece->id, 'format' => $format, 'path' => $path]);
    }

    return $piece;
}

beforeEach(function () {
    $this->api = new FakeYouTubeApi;
    $this->app->instance(YouTubeApi::class, $this->api);
});

it('uploads both formats as unlisted with metadata and thumbnail, recording quota', function () {
    $piece = renderedPiece();

    app(YouTubePublisher::class)->publish($piece);

    expect($this->api->uploads)->toHaveCount(2)
        ->and($this->api->uploads[0]['meta']['privacy'])->toBe('unlisted')
        ->and($this->api->uploads[0]['meta']['title'])->toBe('Suma fracciones sin errores')
        ->and($this->api->uploads[0]['meta']['description'])->toContain('#Shorts')
        ->and($this->api->uploads[1]['meta']['description'])->not->toContain('#Shorts')
        ->and($this->api->uploads[0]['meta']['synthetic'])->toBeTrue()
        ->and($this->api->thumbnails)->toBe(['vid2']);

    $pubs = Publication::where('target', 'youtube')->get()->keyBy('format');
    expect($pubs['9x16']->url)->toBe('https://www.youtube.com/shorts/vid1')
        ->and($pubs['16x9']->url)->toBe('https://www.youtube.com/watch?v=vid2')
        ->and($pubs->every(fn ($p) => $p->status === 'publicada'))->toBeTrue();

    expect((int) CostEvent::where('provider', 'youtube')->sum('units'))->toBe(1600 * 2 + 50)
        ->and((float) CostEvent::where('provider', 'youtube')->sum('cost_eur'))->toBe(0.0);
});

it('never uploads the same format twice when relaunched', function () {
    $piece = renderedPiece();

    app(YouTubePublisher::class)->publish($piece);
    app(YouTubePublisher::class)->publish($piece);

    expect($this->api->uploads)->toHaveCount(2)
        ->and(Publication::where('target', 'youtube')->count())->toBe(2);
});

it('refuses to upload when the daily quota is exhausted', function () {
    config(['accesopro.youtube.daily_quota' => 1000]);
    $piece = renderedPiece();

    app(YouTubePublisher::class)->publish($piece);

    expect($this->api->uploads)->toBeEmpty();
    expect(Publication::where('target', 'youtube')->first()->error)->toContain('Cuota diaria de YouTube agotada');
});

it('keeps the video published but warns when YouTube forces private or rejects the thumbnail', function () {
    $this->api->privacy = 'private';
    $this->api->failThumbnail = true;
    $piece = renderedPiece();

    app(YouTubePublisher::class)->publish($piece);

    $pub = Publication::where('target', 'youtube')->where('format', '16x9')->first();
    expect($pub->status)->toBe('publicada')
        ->and($pub->error)->toContain('«private»')
        ->and($pub->error)->toContain('Miniatura no aplicada');
});

it('marks the piece published when PUBLISH_TARGET is youtube', function () {
    config(['accesopro.publish_target' => 'youtube']);
    $piece = renderedPiece(PieceStatus::Renderizada);

    expect(app(Publisher::class))->toBeInstanceOf(YouTubePublisher::class);

    (new PublishToYouTube($piece))->handle(app(YouTubePublisher::class));

    expect($piece->fresh()->status)->toBe(PieceStatus::Publicada);
});

it('queues the upload from the control panel button and shows it as pending', function () {
    Queue::fake();
    AppSetting::put(YouTubeAuth::TOKEN_KEY, 'refresh-token');
    $piece = renderedPiece();

    $this->actingAs(User::factory()->create())
        ->post(route('youtube.publish', $piece))
        ->assertRedirect();

    Queue::assertPushed(PublishToYouTube::class);
    expect(Publication::where('target', 'youtube')->pluck('status')->unique()->all())->toBe(['pendiente']);

    $this->get(route('control.index'))->assertSee('Subiendo…');
});

it('does not queue anything when YouTube is not connected or the piece has no video', function () {
    Queue::fake();
    config(['accesopro.youtube.refresh_token' => null]);
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('youtube.publish', renderedPiece()))->assertSessionHasErrors('youtube');

    AppSetting::put(YouTubeAuth::TOKEN_KEY, 'refresh-token');
    $this->post(route('youtube.publish', renderedPiece(PieceStatus::Descartada)))->assertSessionHasErrors('youtube');

    Queue::assertNothingPushed();
});

it('shows the upload button and connection bar in the control panel', function () {
    config(['accesopro.youtube.client_id' => 'id', 'accesopro.youtube.client_secret' => 'secret', 'accesopro.youtube.refresh_token' => null]);
    renderedPiece();

    $this->actingAs(User::factory()->create())
        ->get(route('control.index'))
        ->assertSee('Conectar YouTube')
        ->assertSee('Subir a YouTube');
});

it('rejects an OAuth callback whose state does not match the session', function () {
    $this->actingAs(User::factory()->create())
        ->withSession(['youtube_oauth_state' => 'expected'])
        ->get(route('youtube.callback', ['state' => 'forged', 'code' => 'abc']))
        ->assertRedirect(route('control.index'))
        ->assertSessionHasErrors('youtube');

    expect(AppSetting::get(YouTubeAuth::TOKEN_KEY))->toBeNull();
});

it('summarises Google JSON errors and shows the full error behind "Ver detalle"', function () {
    $piece = renderedPiece();
    $json = json_encode(['error' => ['code' => 403, 'message' => 'The request cannot be completed because you have exceeded your quota.', 'errors' => [['reason' => 'quotaExceeded']]]]);

    $pub = Publication::create(['piece_id' => $piece->id, 'target' => 'youtube', 'format' => '16x9', 'status' => 'error', 'error' => $json]);

    expect($pub->errorSummary())->toBe('The request cannot be completed because you have exceeded your quota.')
        ->and($pub->errorDetail())->toContain('"reason": "quotaExceeded"');

    $plain = new Publication(['error' => 'Fallo de red']);
    expect($plain->errorSummary())->toBe('Fallo de red')->and($plain->errorDetail())->toBe('Fallo de red');

    $this->actingAs(User::factory()->create())
        ->get(route('control.index'))
        ->assertSee('Error 16:9: The request cannot be completed', false)
        ->assertSee('Ver detalle')
        ->assertSee('Reintentar');
});
