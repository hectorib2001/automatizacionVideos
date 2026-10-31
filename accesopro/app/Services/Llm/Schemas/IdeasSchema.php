<?php

namespace App\Services\Llm\Schemas;

use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;

/** Salida de ExtractIdeas: lista de ideas de vídeo a partir de un tema del temario. */
final class IdeasSchema
{
    public static function schema(): ObjectSchema
    {
        $idea = new ObjectSchema(
            name: 'idea',
            description: 'Una idea de vídeo independiente, basada en el material de origen.',
            properties: [
                new StringSchema('idea_key', 'Identificador corto en snake_case, único dentro del tema (p. ej. suma_fracciones).'),
                new StringSchema('idea_title', 'Título breve y claro de la idea, en español.'),
                new StringSchema('resumen', 'Una o dos frases de qué aprenderá el espectador, en español.'),
                new ArraySchema(
                    name: 'saberes',
                    description: 'Saberes básicos oficiales (docs/TEMARIO_OFICIAL.md) que cubre esta idea, citados literalmente. Al menos uno.',
                    items: new StringSchema('saber', 'Un saber básico oficial.'),
                ),
            ],
            requiredFields: ['idea_key', 'idea_title', 'resumen', 'saberes'],
        );

        return new ObjectSchema(
            name: 'ideas_response',
            description: 'Lista de ideas de vídeo extraídas del tema.',
            properties: [
                new ArraySchema(
                    name: 'ideas',
                    description: 'Entre 5 y 7 ideas (ni menos ni más), cada una con valor propio y sin solaparse entre sí.',
                    items: $idea,
                ),
            ],
            requiredFields: ['ideas'],
        );
    }
}
