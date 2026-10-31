<?php

namespace App\Enums;

enum PieceStatus: string
{
    case Generada = 'generada';
    case Revisada = 'revisada';
    case Rechazada = 'rechazada';
    case Corregida = 'corregida';
    case Aprobada = 'aprobada';       // aprobación humana (Nivel 2) o automática
    case Renderizada = 'renderizada';
    case Publicada = 'publicada';
    case Descartada = 'descartada';
}
