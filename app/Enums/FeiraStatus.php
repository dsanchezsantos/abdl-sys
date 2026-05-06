<?php

namespace App\Enums;

enum FeiraStatus: string
{
    case PLANEJADA = 'PLANEJADA';
    case EM_ANDAMENTO = 'EM_ANDAMENTO';
    case ENCERRADA = 'ENCERRADA';

    public function label(): string
    {
        return match ($this) {
            self::PLANEJADA => 'Planejada',
            self::EM_ANDAMENTO => 'Em Andamento',
            self::ENCERRADA => 'Encerrada',
        };
    }
}
