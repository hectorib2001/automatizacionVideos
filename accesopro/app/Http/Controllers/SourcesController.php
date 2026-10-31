<?php

namespace App\Http\Controllers;

use App\Enums\PieceStatus;
use App\Jobs\Common\RunSourcePipeline;
use App\Models\Source;
use App\Services\Materials\InvalidDocumentException;
use App\Services\Materials\PdfTextExtractor;
use App\Services\Pipeline\PipelineRunner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Página "Fuentes" del panel: añadir temas (PDF) y clases (enlace de YouTube) y lanzar el
 * pipeline sobre ellos sin tocar la terminal. Solo para Matemáticas GS de la Comunitat
 * Valenciana: el revisor compara siempre con ese temario oficial (ver D28).
 */
final class SourcesController extends Controller
{
    private const YOUTUBE_URL = '#^https?://(www\.|m\.)?(youtube\.com/(watch\?.*v=|live/|shorts/|embed/)|youtu\.be/)[A-Za-z0-9_-]{6,}#';

    public function index(): View
    {
        $sources = Source::query()
            ->withCount([
                'pieces',
                'pieces as published_count' => fn ($q) => $q->where('status', PieceStatus::Publicada),
                'pieces as discarded_count' => fn ($q) => $q->where('status', PieceStatus::Descartada),
            ])
            ->withSum('pieces', 'cost_eur')
            ->latest()
            ->get();

        return view('sources.index', [
            'sources' => $sources,
            'uploadLimit' => ini_get('upload_max_filesize'),
            'queueConnection' => config('queue.default'),
        ]);
    }

    public function storeDocument(Request $request, PdfTextExtractor $extractor): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('sources', 'code')->where('type', 'temario')],
            'title' => ['required', 'string', 'max:150'],
            'document' => ['required', 'file', 'mimes:pdf', 'max:20480'],
        ], [
            'code.regex' => 'El código solo puede tener letras, números, "_" y "-" (p. ej. T1_S02).',
            'code.unique' => 'Ya existe un tema con ese código.',
            'document.mimes' => 'El documento tiene que ser un PDF.',
            'document.uploaded' => 'No se pudo subir el PDF: supera el límite del servidor (upload_max_filesize = '
                .ini_get('upload_max_filesize').').',
        ]);

        $file = $request->file('document');

        try {
            $text = $extractor->extract($file->getRealPath());
        } catch (InvalidDocumentException $e) {
            return back()->withInput()->withErrors(['document' => $e->getMessage()]);
        }

        // Solo se guarda el texto, y en la base de datos: en Laravel Cloud el disco es efímero
        // y el worker que genera el contenido no ve los archivos de la web (ver D29).
        $source = Source::create([
            'type' => 'temario',
            'code' => $data['code'],
            'title' => $data['title'],
            'ref' => $file->getClientOriginalName(),
            'document_text' => $text,
            'meta' => ['origin' => 'panel', 'document_chars' => mb_strlen($text)],
        ]);

        return redirect()->route('sources.index')
            ->with('status', "Tema «{$source->title}» añadido (".number_format(mb_strlen($text), 0, ',', '.').' caracteres de texto).');
    }

    public function storeClass(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'url' => ['required', 'url', 'max:255', 'regex:'.self::YOUTUBE_URL],
            'title' => ['required', 'string', 'max:150'],
        ], [
            'url.regex' => 'Tiene que ser un enlace de YouTube (youtube.com/watch?v=… o youtu.be/…).',
        ]);

        $code = PipelineRunner::classCode($data['url']);

        if (Source::where('type', 'clase')->where('code', $code)->exists()) {
            return back()->withInput()->withErrors(['url' => 'Esa clase ya está añadida.']);
        }

        $source = new Source(['type' => 'clase', 'code' => $code, 'title' => $data['title'], 'ref' => $data['url']]);
        $source->meta = ['origin' => 'panel', 'video_path' => $source->storageDir().'/clase.mp4'];
        $source->save();

        return redirect()->route('sources.index')->with('status', "Clase «{$source->title}» añadida.");
    }

    public function run(Request $request, Source $source): RedirectResponse
    {
        $data = $request->validate(['limit' => ['required', 'integer', 'min:0', 'max:20']]);

        if ($source->isRunning()) {
            return back()->withErrors(['run' => "«{$source->title}» ya se está procesando."]);
        }

        $source->update([
            'run_status' => Source::RUN_QUEUED,
            'run_error' => null,
            'run_started_at' => null,
            'run_finished_at' => null,
        ]);

        RunSourcePipeline::dispatch($source, (int) $data['limit']);

        return back()->with('status', "Generación de «{$source->title}» en cola. El estado se actualiza solo en esta página.");
    }
}
