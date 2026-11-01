@extends('layouts.app')

@section('title', 'Tabla de control')

@section('head-extra')
    @if ($pieces->contains(fn ($p) => $p->publications->contains(fn ($pub) => $pub->target === 'youtube' && $pub->status === 'pendiente')))
        {{-- Mientras haya una subida a YouTube en curso, la tabla se refresca sola. --}}
        <meta http-equiv="refresh" content="15">
    @endif
@endsection

@section('content')
    <style>
        .toolbar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 12px; }
        .toolbar h1 { margin: 0; font-size: 22px; }
        .toolbar .total { font-size: 14px; color: #25394F; background: #DDF2E8; padding: 8px 14px; border-radius: 8px; font-weight: 600; }
        .filters { display: flex; gap: 10px; margin-bottom: 16px; }
        .filters select { padding: 7px 10px; border-radius: 6px; border: 1px solid #D7DCE1; font-size: 13px; }
        table.control { width: 100%; border-collapse: collapse; font-size: 13px; }
        table.control th { text-align: left; padding: 10px 12px; border-bottom: 2px solid #E4E8EC; color: #25394F; font-size: 12px; text-transform: uppercase; letter-spacing: .03em; }
        table.control td { padding: 10px 12px; border-bottom: 1px solid #F0F2F4; vertical-align: top; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; }
        .badge-texto { background: #DCEEFB; color: #0B5F8A; }
        .badge-clip { background: #FDF0DC; color: #8A5A0B; }
        .badge-status-gray { background: #EAEDEF; color: #52606A; }
        .badge-status-success { background: #DDF2E8; color: #1D7A4C; }
        .badge-status-warning { background: #FDF0DC; color: #8A5A0B; }
        .badge-status-danger { background: #FDECEC; color: #B3261E; }
        .origin small { display: block; color: #67717E; margin-top: 2px; }
        .muted { color: #9AA3AA; }
        .pagination-wrap { margin-top: 16px; }
        .exports { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; font-size: 13px; color: #52606A; }
        .exports a { padding: 6px 12px; border: 1px solid #D7DCE1; border-radius: 6px; text-decoration: none; color: #25394F; font-weight: 600; background: #fff; }
        .exports a:hover { background: #F4F6F8; }
        .flash { background: #DDF2E8; color: #1D7A4C; border-radius: 8px; padding: 10px 14px; font-size: 13px; margin-bottom: 16px; font-weight: 600; }
        .errors { background: #FDECEC; color: #B3261E; border-radius: 8px; padding: 10px 14px; font-size: 13px; margin-bottom: 16px; }
        .yt-bar { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; font-size: 13px; color: #52606A; background: #fff; border: 1px solid #E4E8EC; border-radius: 8px; padding: 10px 14px; }
        .yt-bar strong { color: #25394F; }
        .yt-bar form { margin: 0; }
        .yt-btn { display: inline-block; background: #C4302B; color: #fff; border: 0; padding: 6px 12px; border-radius: 6px; font-weight: 700; font-size: 12px; cursor: pointer; text-decoration: none; white-space: nowrap; }
        .yt-btn:disabled { background: #B9C2CA; cursor: not-allowed; }
        .yt-link-btn { background: none; border: 0; color: #67717E; text-decoration: underline; cursor: pointer; font-size: 12px; padding: 0; }
        td.yt { min-width: 130px; }
        td.yt form { margin: 0; }
        td.yt small { display: block; margin-top: 4px; color: #8A5A0B; max-width: 220px; overflow-wrap: anywhere; }
        td.yt small.err { color: #B3261E; }
        td.reason { max-width: 280px; overflow-wrap: anywhere; color: #52606A; }
    </style>

    <div class="toolbar">
        <h1>Tabla de control</h1>
        <div class="total">Coste total: {{ number_format($totalCost, 5) }} €</div>
    </div>

    <div class="exports">
        Exportar CSV:
        <a href="{{ route('control.export.pieces') }}">Tabla de control</a>
        <a href="{{ route('control.export.reviews') }}">Revisiones</a>
        <a href="{{ route('control.export.rejections') }}">Rechazos</a>
    </div>

    @if (session('status'))
        <div class="flash">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="errors">{{ $errors->first() }}</div>
    @endif

    <div class="yt-bar">
        @if ($youtube['connected'])
            <span>YouTube: <strong>{{ $youtube['channel'] ?? 'conectado (token del .env)' }}</strong></span>
            <span>Cuota hoy: {{ $youtube['quotaRemaining'] }} / {{ $youtube['quotaDaily'] }} unidades</span>
            <a class="yt-link-btn" href="{{ route('youtube.connect') }}">Cambiar de canal</a>
            <form method="POST" action="{{ route('youtube.disconnect') }}">
                @csrf
                <button type="submit" class="yt-link-btn">Desconectar</button>
            </form>
        @elseif ($youtube['configured'])
            <span>YouTube no está conectado: conecta el canal de prueba para poder subir vídeos (se suben como <strong>no listados</strong>).</span>
            <a class="yt-btn" href="{{ route('youtube.connect') }}">Conectar YouTube</a>
        @else
            <span>YouTube no configurado: faltan las credenciales OAuth de Google en el <code>.env</code>.</span>
        @endif
    </div>

    <form method="GET" class="filters">
        <select name="status" onchange="this.form.submit()">
            <option value="">Todos los estados</option>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected($selectedStatus === $status->value)>{{ ucfirst($status->value) }}</option>
            @endforeach
        </select>
        <select name="kind" onchange="this.form.submit()">
            <option value="">Todos los tipos</option>
            <option value="texto" @selected($selectedKind === 'texto')>Texto</option>
            <option value="clip" @selected($selectedKind === 'clip')>Clip</option>
        </select>
    </form>

    <div class="card">
        <table class="control">
            <thead>
                <tr>
                    <th>Título</th>
                    <th>Tipo</th>
                    <th>Estado</th>
                    <th>Origen</th>
                    <th>Coste (€)</th>
                    <th>9:16</th>
                    <th>16:9</th>
                    <th>YouTube</th>
                    <th>Motivo de descarte</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($pieces as $piece)
                    @php
                        $statusColor = match ($piece->status) {
                            \App\Enums\PieceStatus::Generada => 'gray',
                            \App\Enums\PieceStatus::Revisada, \App\Enums\PieceStatus::Aprobada, \App\Enums\PieceStatus::Renderizada, \App\Enums\PieceStatus::Publicada => 'success',
                            \App\Enums\PieceStatus::Rechazada, \App\Enums\PieceStatus::Corregida => 'warning',
                            \App\Enums\PieceStatus::Descartada => 'danger',
                        };
                        $drive = $piece->publications->where('target', 'drive')->where('status', 'publicada');
                        $url9x16 = $drive->firstWhere('format', '9x16')?->url;
                        $url16x9 = $drive->firstWhere('format', '16x9')?->url;
                        $yt = $piece->publications->where('target', 'youtube');
                        $ytDone = $yt->where('status', 'publicada');
                        $ytPending = $yt->contains('status', 'pendiente');
                        $ytFailed = $yt->where('status', 'error')->sortByDesc('format');
                        $ytError = $ytFailed->isNotEmpty();
                        // Avisos no bloqueantes (vídeo forzado a privado, miniatura no aplicada).
                        $ytWarned = $ytDone->filter(fn ($pub) => filled($pub->error))->sortByDesc('format');
                    @endphp
                    <tr>
                        <td>{{ $piece->idea_title }}</td>
                        <td><span class="badge badge-{{ $piece->kind }}">{{ $piece->kind === 'texto' ? 'Texto' : 'Clip' }}</span></td>
                        <td><span class="badge badge-status-{{ $statusColor }}">{{ ucfirst($piece->status->value) }}</span></td>
                        <td class="origin">
                            {{ $piece->source->title ?: $piece->source->code }}
                            ({{ $piece->source->type === 'clase' ? 'clase' : 'temario' }})
                            @if ($piece->kind === 'clip' && $piece->clip_start !== null)
                                <small>{{ gmdate('i:s', (int) $piece->clip_start) }}–{{ gmdate('i:s', (int) $piece->clip_end) }}</small>
                            @endif
                        </td>
                        <td>{{ number_format($piece->cost_eur, 5) }}</td>
                        <td>@if ($url9x16)<a href="{{ $url9x16 }}" target="_blank" rel="noopener">Ver</a>@else<span class="muted">—</span>@endif</td>
                        <td>@if ($url16x9)<a href="{{ $url16x9 }}" target="_blank" rel="noopener">Ver</a>@else<span class="muted">—</span>@endif</td>
                        <td class="yt">
                            @foreach ($ytDone->sortByDesc('format') as $pub)
                                <a href="{{ $pub->url }}" target="_blank" rel="noopener">{{ $pub->format === '9x16' ? 'Short' : '16:9' }}</a>@if (! $loop->last) · @endif
                            @endforeach
                            @if ($ytPending)
                                <span class="muted">Subiendo…</span>
                            @elseif ($piece->canPublishToYouTube() && $ytDone->count() < count(\App\Models\Piece::VIDEO_FORMATS))
                                <form method="POST" action="{{ route('youtube.publish', $piece) }}">
                                    @csrf
                                    <button type="submit" class="yt-btn" @disabled(! $youtube['connected'])
                                        @if (! $youtube['connected']) title="Conecta primero el canal de YouTube" @endif>
                                        {{ $ytError ? 'Reintentar' : 'Subir a YouTube' }}
                                    </button>
                                </form>
                            @elseif ($ytDone->isEmpty())
                                <span class="muted">—</span>
                            @endif
                            @unless ($ytPending)
                                @foreach ($ytFailed as $pub)
                                    @php $label = $pub->format === '9x16' ? '9:16' : '16:9'; @endphp
                                    <small class="err">
                                        Error {{ $label }}: {{ \Illuminate\Support\Str::limit($pub->errorSummary(), 90) }}
                                        <button type="button" class="detail-more" data-detail-code
                                            data-detail-title="Error al subir a YouTube ({{ $label }}) · {{ $piece->idea_title }}"
                                            data-detail="{{ $pub->errorDetail() }}">Ver detalle</button>
                                    </small>
                                @endforeach
                            @endunless
                            @foreach ($ytWarned as $pub)
                                @php $label = $pub->format === '9x16' ? '9:16' : '16:9'; @endphp
                                <small>
                                    Aviso {{ $label }}: {{ \Illuminate\Support\Str::limit($pub->errorSummary(), 90) }}
                                    @if (mb_strlen($pub->errorSummary()) > 90)
                                        <button type="button" class="detail-more"
                                            data-detail-title="Aviso de YouTube ({{ $label }}) · {{ $piece->idea_title }}"
                                            data-detail="{{ $pub->errorDetail() }}">Ver detalle</button>
                                    @endif
                                </small>
                            @endforeach
                        </td>
                        <td class="reason">
                            @if ($piece->discard_reason)
                                {{ \Illuminate\Support\Str::limit($piece->discard_reason, 90) }}
                                @if (mb_strlen($piece->discard_reason) > 90)
                                    {{-- Los motivos del revisor vienen unidos con "; ": en la ventana, uno por párrafo. --}}
                                    <button type="button" class="detail-more" data-detail-title="Motivo de descarte · {{ $piece->idea_title }}"
                                        data-detail="{{ preg_replace('/(?<=[.!?»”)])\s*;\s+/u', "\n\n", $piece->discard_reason) }}">Ver detalle</button>
                                @endif
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="muted">No hay piezas todavía.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="pagination-wrap">
            {{ $pieces->links() }}
        </div>
    </div>

    @include('partials.detail-dialog')
@endsection
