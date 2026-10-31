@extends('layouts.app')

@section('title', 'Tabla de control')

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
                        $url9x16 = $piece->publications->firstWhere('format', '9x16')?->url;
                        $url16x9 = $piece->publications->firstWhere('format', '16x9')?->url;
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
                        <td>{{ $piece->discard_reason ? \Illuminate\Support\Str::limit($piece->discard_reason, 140) : '' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted">No hay piezas todavía.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="pagination-wrap">
            {{ $pieces->links() }}
        </div>
    </div>
@endsection
