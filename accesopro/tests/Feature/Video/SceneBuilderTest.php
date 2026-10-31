<?php

use App\Services\Video\SceneBuilder;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->builder = new SceneBuilder;
    $this->outDir = storage_path('app/private/pruebas/scene-builder-test');
    @mkdir($this->outDir, 0777, true);
});

afterEach(function () {
    if (! empty($this->outDir) && str_starts_with($this->outDir, storage_path())) {
        File::deleteDirectory($this->outDir);
    }
});

it('renders a brand background PNG of the requested size', function () {
    config(['accesopro.brand.logo_path' => '/ruta/que/no/existe.png']);

    $out = "{$this->outDir}/bg.png";
    $this->builder->renderBackground(1080, 1920, $out);

    [$width, $height, $type] = getimagesize($out);

    expect($type)->toBe(IMAGETYPE_PNG)
        ->and($width)->toBe(1080)
        ->and($height)->toBe(1920);
});

it('renders backgrounds for both 9:16 and 16:9', function () {
    config(['accesopro.brand.logo_path' => '/ruta/que/no/existe.png']);

    $this->builder->renderBackground(1080, 1920, "{$this->outDir}/vertical.png");
    $this->builder->renderBackground(1920, 1080, "{$this->outDir}/horizontal.png");

    [$w1, $h1] = getimagesize("{$this->outDir}/vertical.png");
    [$w2, $h2] = getimagesize("{$this->outDir}/horizontal.png");

    expect([$w1, $h1])->toBe([1080, 1920])
        ->and([$w2, $h2])->toBe([1920, 1080]);
});

it('composites a logo when one is configured, without erroring', function () {
    $logoPath = "{$this->outDir}/fake-logo.png";
    $logo = imagecreatetruecolor(200, 60);
    imagesavealpha($logo, true);
    imagefill($logo, 0, 0, imagecolorallocatealpha($logo, 0, 0, 0, 127));
    imagepng($logo, $logoPath);
    imagedestroy($logo);

    config(['accesopro.brand.logo_path' => $logoPath]);

    $out = "{$this->outDir}/with-logo.png";
    $this->builder->renderBackground(1080, 1920, $out);

    expect(is_file($out))->toBeTrue();
});

it('does not throw when the configured logo file does not exist', function () {
    config(['accesopro.brand.logo_path' => "{$this->outDir}/no-existe.png"]);

    $out = "{$this->outDir}/no-logo.png";
    $this->builder->renderBackground(1080, 1920, $out);

    expect(is_file($out))->toBeTrue();
});
