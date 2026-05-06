<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\RelatorioStatus;
use Illuminate\Support\Facades\URL;

class Relatorio extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_feira',
        'usuario_id',
        'tipo',
        'parametros_filtro',
        'status',
        'caminho_arquivo',
        'tamanho_bytes',
        'mensagem_erro',
        'tempo_execucao_segundos',
    ];

    protected $casts = [
        'parametros_filtro' => 'array',
        'status' => RelatorioStatus::class,
    ];

    /**
     * Relacionamento: O relatório pertence a um evento específico.
     */
    public function feira()
    {
        return $this->belongsTo(Feira::class, 'id_feira');
    }

    /**
     * Relacionamento: O relatório foi solicitado por um utilizador específico.
     */
    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * Método Auxiliar de Download (A Segurança)
     * Gera um link assinado temporário que expira em 30 minutos.
     */
    public function urlDownloadSegura()
    {
        if ($this->status !== RelatorioStatus::CONCLUIDO || !$this->caminho_arquivo) {
            return null;
        }

        // Gera um link que se destrói em 30 minutos
        // Nota: A rota 'relatorios.download' deve ser definida nas routes/web.php
        return URL::temporarySignedRoute(
            'relatorios.download', now()->addMinutes(30), ['relatorio' => $this->id]
        );
    }
}
