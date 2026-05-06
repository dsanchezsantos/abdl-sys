<?php

namespace App\Enums;

enum RelatorioStatus: string
{
    case FILA = 'FILA';
    case PROCESSANDO = 'PROCESSANDO';
    case CONCLUIDO = 'CONCLUIDO';
    case FALHA = 'FALHA';

    public function label(): string
    {
        return match ($this) {
            self::FILA => 'Aguardando início do processamento',
            self::PROCESSANDO => 'A processar dados e a gerar PDF...',
            self::CONCLUIDO => 'Concluído',
            self::FALHA => 'Falha na geração',
        };
    }
}
