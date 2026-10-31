<?php

use App\Services\Review\MathVerifier;

beforeEach(function () {
    $this->verifier = new MathVerifier;
});

it('verifies correct arithmetic claims', function (string $expresion, string $resultado) {
    $result = $this->verifier->verify(['expresion' => $expresion, 'resultado' => $resultado]);

    expect($result['status'])->toBe('pass');
})->with([
    ['2+3*4', '14'],
    ['1/2+1/3', '5/6'],
    ['2^-3', '1/8'],
    ['(3/4)/(2/5)', '15/8'],
    ['2/3+3/4-1/3', '13/12'],
]);

it('rejects incorrect arithmetic claims', function (string $expresion, string $resultado) {
    $result = $this->verifier->verify(['expresion' => $expresion, 'resultado' => $resultado]);

    expect($result['status'])->toBe('fail');
})->with([
    ['2^-3', '-8'],
    ['1/2+1/3', '2/5'],
]);

it('marks unparseable expressions as unverifiable instead of failing', function () {
    $result = $this->verifier->verify(['expresion' => 'no es una expresión', 'resultado' => '14']);

    expect($result['status'])->toBe('unverifiable');
});

it('compares sqrt results with float tolerance', function () {
    $result = $this->verifier->verify(['expresion' => 'sqrt(2)', 'resultado' => '1.4142135624'], approx: true);

    expect($result['status'])->toBe('pass');
});

it('flags any failing claim in a batch via anyFailed', function () {
    $verified = $this->verifier->verifyAll([
        ['expresion' => '2+2', 'resultado' => '4'],
        ['expresion' => '2^-3', 'resultado' => '-8'],
    ]);

    expect($this->verifier->anyFailed($verified))->toBeTrue();
});
