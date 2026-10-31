<?php

namespace App\Http\Controllers;

use App\Enums\PieceStatus;
use App\Models\Piece;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Tabla de control del enunciado (ver docs/RETO.md): estado, coste real y enlace de
 * cada pieza, más el origen (de qué material o vídeo viene el contenido). Esto último ya
 * existía vía Piece->source (columna obligatoria desde la migración original de Fase 0):
 * no hizo falta añadir ninguna columna nueva ni rellenar valores por defecto.
 */
final class ControlPanelController extends Controller
{
    public function index(Request $request): View
    {
        $pieces = Piece::query()
            ->with(['source', 'publications' => fn ($q) => $q->where('status', 'publicada')])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('kind'), fn ($q) => $q->where('kind', $request->string('kind')))
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        $totalCost = Piece::sum('cost_eur');

        return view('control.index', [
            'pieces' => $pieces,
            'statuses' => PieceStatus::cases(),
            'totalCost' => $totalCost,
            'selectedStatus' => $request->string('status')->toString(),
            'selectedKind' => $request->string('kind')->toString(),
        ]);
    }
}
