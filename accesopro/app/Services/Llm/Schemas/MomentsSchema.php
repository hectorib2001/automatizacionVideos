<?php

namespace App\Services\Llm\Schemas;

use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\EnumSchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;

/** Salida de DetectMoments: momentos con valor propio dentro de la clase grabada. */
final class MomentsSchema
{
    public static function schema(): ObjectSchema
    {
        $moment = new ObjectSchema(
            name: 'moment',
            description: 'Un momento de la clase con valor propio para un clip independiente.',
            properties: [
                new NumberSchema('start_seconds', 'Segundo exacto de inicio, copiado literalmente de una de las marcas de tiempo [inicio - fin] del listado.'),
                new NumberSchema('end_seconds', 'Segundo exacto de fin, copiado literalmente de una de las marcas de tiempo [inicio - fin] del listado.'),
                new EnumSchema('type', 'Tipo de momento.', ['explicacion_completa', 'truco_examen', 'error_tipico']),
                new StringSchema('title', 'Título breve del clip, en español.'),
                new StringSchema('why_standalone', 'Por qué se entiende sin el resto de la clase.'),
            ],
            requiredFields: ['start_seconds', 'end_seconds', 'type', 'title', 'why_standalone'],
        );

        return new ObjectSchema(
            name: 'moments_response',
            description: 'Entre 8 y 12 momentos candidatos de la clase, varios se descartarán en la revisión.',
            properties: [
                new ArraySchema('moments', 'Los momentos detectados, sin solaparse entre sí.', items: $moment),
            ],
            requiredFields: ['moments'],
        );
    }
}
