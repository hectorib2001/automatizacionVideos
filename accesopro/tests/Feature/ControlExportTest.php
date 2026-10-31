<?php

use App\Enums\PieceStatus;
use App\Models\Piece;
use App\Models\Publication;
use App\Models\Review;
use App\Models\Source;
use App\Models\User;

beforeEach(function () {
    $source = Source::create(['type' => 'temario', 'code' => 'T1_S01', 'title' => 'T1_S01']);

    $this->published = Piece::create([
        'source_id' => $source->id, 'kind' => 'texto', 'uid' => 'export-ok',
        'idea_title' => 'Sumar fracciones', 'status' => PieceStatus::Publicada, 'cost_eur' => 0.21234,
    ]);
    Publication::create(['piece_id' => $this->published->id, 'target' => 'drive', 'format' => '9x16', 'url' => 'https://drive/9x16', 'status' => 'publicada']);
    Review::create(['piece_id' => $this->published->id, 'attempt' => 1, 'model' => 'claude-opus-5-5', 'verdict' => 'aprobado', 'reasons' => [], 'checks' => []]);

    $this->discarded = Piece::create([
        'source_id' => $source->id, 'kind' => 'texto', 'uid' => 'export-ko',
        'idea_title' => 'Potencias; con "comillas"', 'status' => PieceStatus::Descartada, 'discard_reason' => 'Dato inventado',
    ]);
    Review::create([
        'piece_id' => $this->discarded->id, 'attempt' => 1, 'model' => 'claude-opus-5-5', 'verdict' => 'rechazado',
        'reasons' => ['Afirma un 90 % sin fuente'],
        'checks' => [
            'matematica' => ['status' => 'pass', 'detail' => 'ok'],
            'datos_inventados' => ['status' => 'fail', 'detail' => 'El 90 % no está en las fuentes'],
            'temario' => ['status' => 'pass', 'detail' => 'ok', 'saber_oficial' => 'Bloque 1'],
        ],
    ]);
});

function csvRows(string $content): array
{
    expect(str_starts_with($content, "\xEF\xBB\xBF"))->toBeTrue();
    $lines = array_filter(explode("\n", substr($content, 3)));

    return array_map(fn ($l) => str_getcsv($l, ';', '"', ''), array_values($lines));
}

it('requires login to export', function () {
    $this->get(route('control.export.pieces'))->assertRedirect(route('login'));
});

it('exports the control table with status, cost in spanish format and links', function () {
    $response = $this->actingAs(User::factory()->create())->get(route('control.export.pieces'));

    $response->assertOk()->assertDownload();
    $rows = csvRows($response->streamedContent());

    expect($rows[0])->toContain('estado', 'coste_eur', 'enlace_9x16')
        ->and($rows)->toHaveCount(3)
        ->and($rows[1])->toContain('publicada', '0,21234', 'https://drive/9x16')
        ->and($rows[2])->toContain('Potencias; con "comillas"', 'descartada', 'Dato inventado');
});

it('exports only rejected reviews with failed checks and reasons', function () {
    $response = $this->actingAs(User::factory()->create())->get(route('control.export.rejections'));
    $rows = csvRows($response->streamedContent());

    expect($rows)->toHaveCount(2)
        ->and($rows[1])->toContain('rechazado', 'datos_inventados', 'Bloque 1', 'Afirma un 90 % sin fuente')
        ->and(implode(' ', $rows[1]))->toContain('datos_inventados: El 90 % no está en las fuentes');
});

it('writes the three csv files from the command', function () {
    $dir = sys_get_temp_dir().'/accesopro-export-'.uniqid();

    $this->artisan('accesopro:export', ['--dir' => $dir])->assertSuccessful();

    expect(csvRows(file_get_contents("{$dir}/tabla_control.csv")))->toHaveCount(3)
        ->and(csvRows(file_get_contents("{$dir}/revisiones.csv")))->toHaveCount(3)
        ->and(csvRows(file_get_contents("{$dir}/rechazos.csv")))->toHaveCount(2);
});
