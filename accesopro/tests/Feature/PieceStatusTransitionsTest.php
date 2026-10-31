<?php

use App\Enums\PieceStatus;
use App\Models\Piece;
use App\Models\Publication;
use App\Models\Render;
use App\Models\Source;

function makePiece(PieceStatus $status): Piece
{
    $source = Source::firstOrCreate(['type' => 'temario', 'code' => 'T1_S01'], ['title' => 'T1_S01']);

    return Piece::create([
        'source_id' => $source->id,
        'kind' => 'texto',
        'uid' => 'transition-'.uniqid(),
        'idea_title' => 'Idea de prueba',
        'status' => $status,
    ]);
}

function addRenders(Piece $piece, array $formats): void
{
    foreach ($formats as $format) {
        Render::create(['piece_id' => $piece->id, 'format' => $format, 'path' => "/tmp/{$format}.mp4"]);
    }
}

function addPublications(Piece $piece, array $formats, string $status = 'publicada'): void
{
    foreach ($formats as $format) {
        Publication::create(['piece_id' => $piece->id, 'target' => 'drive', 'format' => $format, 'url' => 'https://x', 'status' => $status]);
    }
}

beforeEach(fn () => config(['accesopro.publish_target' => 'drive']));

it('approves a reviewed piece and lets it continue', function () {
    $piece = makePiece(PieceStatus::Revisada);

    expect($piece->approve())->toBeTrue()
        ->and($piece->fresh()->status)->toBe(PieceStatus::Aprobada);
});

it('does not approve rejected or discarded pieces', function (PieceStatus $status) {
    $piece = makePiece($status);

    expect($piece->approve())->toBeFalse()
        ->and($piece->fresh()->status)->toBe($status);
})->with([PieceStatus::Rechazada, PieceStatus::Descartada, PieceStatus::Generada, PieceStatus::Corregida]);

it('lets an already published piece continue on relaunch without changing its status', function () {
    $piece = makePiece(PieceStatus::Publicada);

    expect($piece->approve())->toBeTrue()
        ->and($piece->fresh()->status)->toBe(PieceStatus::Publicada);
});

it('marks rendered only when both video formats exist', function () {
    $piece = makePiece(PieceStatus::Aprobada);
    addRenders($piece, ['9x16', 'audio']);

    $piece->markRendered();
    expect($piece->fresh()->status)->toBe(PieceStatus::Aprobada);

    addRenders($piece, ['16x9']);
    $piece->markRendered();
    expect($piece->fresh()->status)->toBe(PieceStatus::Renderizada);
});

it('marks published only when both formats are published on the active target', function () {
    $piece = makePiece(PieceStatus::Renderizada);
    addPublications($piece, ['9x16']);
    addPublications($piece, ['16x9'], 'error');

    $piece->markPublished();
    expect($piece->fresh()->status)->toBe(PieceStatus::Renderizada);

    Publication::where('piece_id', $piece->id)->update(['status' => 'publicada']);
    $piece->markPublished();
    expect($piece->fresh()->status)->toBe(PieceStatus::Publicada);
});

it('never advances a discarded piece even if it has renders and publications', function () {
    $piece = makePiece(PieceStatus::Descartada);
    addRenders($piece, Piece::VIDEO_FORMATS);
    addPublications($piece, Piece::VIDEO_FORMATS);

    $piece->markRendered();
    $piece->markPublished();

    expect($piece->fresh()->status)->toBe(PieceStatus::Descartada);
});
