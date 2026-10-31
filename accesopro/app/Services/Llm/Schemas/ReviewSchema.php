<?php

namespace App\Services\Llm\Schemas;

use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\EnumSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;

/** Salida del agente revisor. Ver docs/REVISOR.md para la especificación completa. */
final class ReviewSchema
{
    public const CHECK_NAMES = ['matematica', 'temario', 'datos_inventados', 'pedagogia', 'autonomia', 'formato'];

    public static function schema(): ObjectSchema
    {
        $claim = new ObjectSchema(
            name: 'claim',
            description: 'Una afirmación numérica verificable encontrada en la pieza.',
            properties: [
                new StringSchema('expresion', "Expresión matemática en notación simple, p. ej. '1/2+1/3'."),
                new StringSchema('resultado', "Resultado afirmado, en la misma notación, p. ej. '5/6'."),
                new StringSchema('texto_original', 'Frase original de la que proviene la afirmación.'),
            ],
            requiredFields: ['expresion', 'resultado', 'texto_original'],
        );

        $checkProperties = array_map(
            fn (string $name) => new ObjectSchema(
                name: $name,
                description: "Resultado de la comprobación '{$name}'.",
                properties: [
                    new EnumSchema('status', 'pass si cumple, fail si no, n/a si no aplica a esta pieza.', ['pass', 'fail', 'n/a']),
                    new StringSchema('detail', 'Motivo concreto del resultado, en español.'),
                    new StringSchema('saber_oficial', 'Saber básico oficial citado literalmente (solo en el check "temario").', nullable: true),
                ],
                requiredFields: ['status', 'detail'],
            ),
            self::CHECK_NAMES,
        );

        $checks = new ObjectSchema(
            name: 'checks',
            description: 'Las seis comprobaciones del revisor.',
            properties: $checkProperties,
            requiredFields: self::CHECK_NAMES,
        );

        return new ObjectSchema(
            name: 'review',
            description: 'Veredicto del agente revisor sobre una pieza (guion o clip).',
            properties: [
                new EnumSchema('verdict', 'aprobado solo si todas las comprobaciones aplicables son pass.', ['aprobado', 'rechazado']),
                $checks,
                new ArraySchema('claims', 'Afirmaciones numéricas verificables encontradas en la pieza.', items: $claim),
                new ArraySchema(
                    name: 'reasons',
                    description: 'Motivos concretos y accionables para corregir, vacío si se aprueba.',
                    items: new StringSchema('reason', 'Un motivo concreto.'),
                ),
                new StringSchema('fix_suggestion', 'Qué cambiar exactamente para corregir la pieza, vacío si se aprueba.'),
            ],
            requiredFields: ['verdict', 'checks', 'claims', 'reasons', 'fix_suggestion'],
        );
    }
}
