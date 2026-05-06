<?php

namespace App\Enums;

enum CartaoClassificacao: string
{
    case ALUNO = 'ALUNO';
    case TESTE = 'TESTE';
    case CORTESIA = 'CORTESIA';
    case STAFF = 'STAFF';

    public function label(): string
    {
        return match ($this) {
            self::ALUNO => 'Aluno',
            self::TESTE => 'Teste',
            self::CORTESIA => 'Cortesia',
            self::STAFF => 'Staff',
        };
    }
}
