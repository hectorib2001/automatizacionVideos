<?php

use App\Services\Video\SceneBuilder;
use App\Services\Video\ThumbnailMaker;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    config(['accesopro.brand.logo_path' => '/ruta/que/no/existe.png']);
    $this->outDir = storage_path('app/private/pruebas/thumbnail-maker-test');
    @mkdir($this->outDir, 0777, true);
});

afterEach(function () {
    if (! empty($this->outDir) && str_starts_with($this->outDir, storage_path())) {
        File::deleteDirectory($this->outDir);
    }
});

it('renders a 1280x720 thumbnail with the given title', function () {
    $maker = new ThumbnailMaker(new SceneBuilder);

    $out = "{$this->outDir}/thumb.png";
    $maker->make('Sumar fracciones sin errores en el examen', $out, "{$this->outDir}/work");

    [$width, $height, $type] = getimagesize($out);

    expect($type)->toBe(IMAGETYPE_PNG)
        ->and($width)->toBe(1280)
        ->and($height)->toBe(720);
});
