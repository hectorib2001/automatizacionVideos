<?php

namespace App\Services\Llm\Schemas;

use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;

/** Salida de GenerateMetadata: título, descripción y etiquetas para publicar el vídeo. */
final class MetadataSchema
{
    public static function schema(): ObjectSchema
    {
        return new ObjectSchema(
            name: 'metadata',
            description: 'Metadatos de publicación del vídeo (YouTube/Drive).',
            properties: [
                new StringSchema('title', 'Título para YouTube, máximo 90 caracteres.'),
                new StringSchema('description', 'Descripción de 2-4 frases, en español.'),
                new ArraySchema(
                    name: 'tags',
                    description: 'Entre 6 y 12 etiquetas cortas en español.',
                    items: new StringSchema('tag', 'Una etiqueta corta.'),
                ),
            ],
            requiredFields: ['title', 'description', 'tags'],
        );
    }
}
