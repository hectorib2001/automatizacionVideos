@extends('layouts.app')

@section('title', 'Fuentes')

@section('head-extra')
    @if ($sources->contains(fn ($s) => $s->isRunning()))
        {{-- Mientras haya algo en cola o procesándose, la página se refresca sola. --}}
        <meta http-equiv="refresh" content="15">
    @endif
@endsection

@section('content')
    <style>
        h1 { margin: 0 0 16px; font-size: 22px; }
        h2 { margin: 0 0 6px; font-size: 16px; }
        .note { background: #FFF8E6; border: 1px solid #F2D58A; color: #5C4A12; border-radius: 10px; padding: 14px 18px; font-size: 13px; line-height: 1.5; margin-bottom: 20px; }
        .note strong { color: #3E3109; }
        .flash { background: #DDF2E8; color: #1D7A4C; border-radius: 8px; padding: 10px 14px; font-size: 13px; margin-bottom: 16px; font-weight: 600; }
        .errors { background: #FDECEC; color: #B3261E; border-radius: 8px; padding: 10px 14px; font-size: 13px; margin-bottom: 16px; }
        .errors ul { margin: 0; padding-left: 18px; }
        .forms { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .forms p.help { margin: 0 0 14px; font-size: 13px; color: #67717E; }
        label { display: block; font-size: 12px; font-weight: 700; color: #25394F; margin: 10px 0 4px; text-transform: uppercase; letter-spacing: .03em; }
        input[type=text], input[type=url], input[type=file], input[type=number] { width: 100%; padding: 8px 10px; border: 1px solid #D7DCE1; border-radius: 6px; font-size: 14px; background: #fff; }
        .btn { margin-top: 14px; background: var(--primary-green); color: #fff; border: 0; padding: 9px 16px; border-radius: 6px; font-weight: 700; cursor: pointer; font-size: 14px; }
        .btn:disabled { background: #B9C2CA; cursor: not-allowed; }
        table.sources { width: 100%; border-collapse: collapse; font-size: 13px; }
        table.sources th { text-align: left; padding: 10px 12px; border-bottom: 2px solid #E4E8EC; color: #25394F; font-size: 12px; text-transform: uppercase; letter-spacing: .03em; }
        table.sources td { padding: 10px 12px; border-bottom: 1px solid #F0F2F4; vertical-align: top; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; }
        .badge-temario { background: #DCEEFB; color: #0B5F8A; }
        .badge-clase { background: #FDF0DC; color: #8A5A0B; }
        .run-en_cola, .run-procesando { background: #DCEEFB; color: #0B5F8A; }
        .run-completado { background: #DDF2E8; color: #1D7A4C; }
        .run-error { background: #FDECEC; color: #B3261E; }
        small { display: block; color: #67717E; margin-top: 3px; word-break: break-all; }
        .run-form { display: flex; align-items: center; gap: 8px; }
        .run-form input { width: 64px; }
        .run-form .btn { margin-top: 0; padding: 6px 12px; font-size: 13px; }
        .muted { color: #9AA3AA; }
        .err-detail { color: #B3261E; font-size: 12px; margin-top: 4px; max-width: 260px; overflow-wrap: anywhere; }
    </style>

    <h1>Fuentes de contenido</h1>

    <div class="note">
        <strong>Solo Matemáticas · Grado Superior · Comunitat Valenciana.</strong>
        El revisor compara todo con ese temario oficial: otro material se rechazaría.
        Coste aproximado: 0,22 € por vídeo de texto y 0,05 € por clip.
        @if ($isLocal)
            <br>Local: arranca con <code>composer run dev</code> para que el botón «Generar» procese la cola.
        @endif
    </div>

    @if (session('status'))
        <div class="flash">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="errors"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="forms">
        <form class="card" method="POST" action="{{ route('sources.documents.store') }}" enctype="multipart/form-data">
            @csrf
            <h2>Añadir un tema (PDF)</h2>
            <p class="help">Un tema por documento, con texto seleccionable (no escaneado). Tamaño máximo: {{ $uploadLimit }}.</p>
            <label for="doc-code">Código del tema</label>
            <input id="doc-code" type="text" name="code" value="{{ old('code') }}" placeholder="T1_S02" required>
            <label for="doc-title">Título</label>
            <input id="doc-title" type="text" name="title" value="{{ old('title') }}" placeholder="Potencias y radicales" required>
            <label for="doc-file">Documento PDF</label>
            <input id="doc-file" type="file" name="document" accept="application/pdf,.pdf" required>
            <button class="btn" type="submit">Subir tema</button>
        </form>

        <form class="card" method="POST" action="{{ route('sources.classes.store') }}">
            @csrf
            <h2>Añadir una clase grabada (YouTube)</h2>
            <p class="help">Enlace de YouTube (público o no listado). El vídeo se descarga al generar el contenido.</p>
            <label for="class-url">Enlace de YouTube</label>
            <input id="class-url" type="url" name="url" value="{{ old('url') }}" placeholder="https://www.youtube.com/watch?v=..." required>
            <label for="class-title">Título</label>
            <input id="class-title" type="text" name="title" value="{{ old('title', '') }}" placeholder="T1S03 Potencias" required>
            <button class="btn" type="submit">Añadir clase</button>
        </form>
    </div>

    <div class="card">
        <table class="sources">
            <thead>
                <tr>
                    <th>Fuente</th>
                    <th>Tipo</th>
                    <th>Piezas</th>
                    <th>Coste (€)</th>
                    <th>Última generación</th>
                    <th>Generar contenido</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sources as $source)
                    <tr>
                        <td>
                            {{ $source->title ?: $source->code }}
                            <small>
                                {{ $source->code }} ·
                                @if ($source->type === 'clase')
                                    {{ $source->ref }}
                                @elseif ($source->isUploaded())
                                    PDF: {{ $source->ref }}
                                @else
                                    material original (materiales/)
                                @endif
                            </small>
                        </td>
                        <td><span class="badge badge-{{ $source->type }}">{{ $source->type === 'clase' ? 'Clase' : 'Tema' }}</span></td>
                        <td>
                            @if ($source->pieces_count)
                                <a href="{{ route('control.index') }}">{{ $source->pieces_count }}</a>
                                <small>{{ $source->published_count }} publicadas · {{ $source->discarded_count }} descartadas</small>
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                        <td>{{ number_format((float) $source->pieces_sum_cost_eur, 5, ',', '') }}</td>
                        <td>
                            @if ($source->run_status)
                                <span class="badge run-{{ $source->run_status }}">{{ ucfirst(str_replace('_', ' ', $source->run_status)) }}</span>
                                @if ($source->run_finished_at)
                                    <small>{{ $source->run_finished_at->format('d/m H:i') }}</small>
                                @elseif ($source->run_started_at)
                                    <small>desde {{ $source->run_started_at->format('H:i') }}</small>
                                @endif
                                @if ($source->run_error)
                                    <div class="err-detail">
                                        {{ \Illuminate\Support\Str::limit(strtok($source->run_error, "\n"), 70) }}
                                        <button type="button" class="detail-more" data-detail-code data-detail-title="Error en «{{ $source->title ?: $source->code }}»" data-detail="{{ $source->run_error }}">Ver detalle</button>
                                    </div>
                                @endif
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                        <td>
                            <form class="run-form" method="POST" action="{{ route('sources.run', $source) }}">
                                @csrf
                                <input type="number" name="limit" value="1" min="0" max="20" title="Cuántas {{ $source->type === 'clase' ? 'clips' : 'ideas' }} procesar (0 = todas)" aria-label="Límite">
                                <button class="btn" type="submit" @disabled($source->isRunning())>Generar</button>
                            </form>
                            <small>{{ $source->type === 'clase' ? 'clips' : 'ideas' }} a procesar (0 = todas)</small>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">Todavía no hay fuentes.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include("partials.detail-dialog")
@endsection
