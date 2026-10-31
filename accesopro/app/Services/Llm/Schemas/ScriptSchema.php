<?php

namespace App\Services\Llm\Schemas;

use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\EnumSchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;

/** Salida de GenerateScript: guion de 30-60s para un vídeo desde texto. Ver docs/ARQUITECTURA.md. */
final class ScriptSchema
{
    public static function schema(): ObjectSchema
    {
        $hook = new ObjectSchema(
            name: 'hook',
            description: 'Una variante de gancho inicial (primeros 3-5 segundos).',
            properties: [
                new EnumSchema('variant', 'Identificador de la variante.', ['A', 'B']),
                new StringSchema('text', 'Texto del gancho, en español, que atrapa en los primeros segundos.'),
            ],
            requiredFields: ['variant', 'text'],
        );

        $scene = new ObjectSchema(
            name: 'scene',
            description: 'Una escena del vídeo.',
            properties: [
                new StringSchema('texto', 'Texto que aparece en pantalla durante la escena.'),
                new StringSchema('formula', 'Fórmula o cálculo en texto plano a mostrar, si aplica.', nullable: true),
                new StringSchema('svg', 'Descripción de un SVG de apoyo (diagrama, recta numérica...), si aplica.', nullable: true),
                new NumberSchema('duracion_seg', 'Duración aproximada de la escena en segundos (entre 1 y 30).'),
            ],
            requiredFields: ['texto', 'duracion_seg'],
        );

        $claim = new ObjectSchema(
            name: 'claim',
            description: 'Una afirmación numérica verificable del guion.',
            properties: [
                new StringSchema('expresion', "Expresión matemática en notación simple, p. ej. '1/2+1/3'."),
                new StringSchema('resultado', "Resultado afirmado, en la misma notación, p. ej. '5/6'."),
                new StringSchema('texto_original', 'Frase del guion de la que proviene la afirmación.'),
            ],
            requiredFields: ['expresion', 'resultado', 'texto_original'],
        );

        return new ObjectSchema(
            name: 'script',
            description: 'Guion de vídeo corto (30-60s, 75-150 palabras habladas) para preparar un examen.',
            properties: [
                new StringSchema('idea_title', 'Título de la idea que desarrolla este guion.'),
                new ArraySchema('hooks', 'Exactamente dos variantes de gancho (A y B, ni más ni menos) para prueba A/B.', items: $hook),
                new StringSchema('explanation', 'Explicación central, clara, correcta y aplicable para un adulto que prepara un examen.'),
                new StringSchema('cta', 'Llamada a la acción orientada a AccesoPro, sin promesas inventadas.'),
                new ArraySchema('scenes', 'Escenas del vídeo en orden (al menos una), con duración total de 30-60s.', items: $scene),
                new ArraySchema('claims', 'Todas las afirmaciones numéricas verificables del guion.', items: $claim),
                new ArraySchema(
                    name: 'syllabus_refs',
                    description: 'Saberes básicos oficiales (docs/TEMARIO_OFICIAL.md) que trata este guion, citados literalmente. Al menos uno.',
                    items: new StringSchema('syllabus_ref', 'Un saber básico oficial.'),
                ),
            ],
            requiredFields: ['idea_title', 'hooks', 'explanation', 'cta', 'scenes', 'claims', 'syllabus_refs'],
        );
    }
}
