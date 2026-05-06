<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeiraEstatistica extends Model
{
    use HasFactory;

    protected $table = "feira_estatisticas";

    protected $fillable = [
        "id_feira",
        "faturamento_bruto",
        "faturamento_liquido_valido",
        "ticket_medio",
        "total_livros_vendidos",
        "qtd_inconsistencias_catalogo",
        "dados_graficos",
        "atualizado_em",
    ];

    protected $casts = [
        "faturamento_bruto" => "decimal:2",
        "faturamento_liquido_valido" => "decimal:2",
        "ticket_medio" => "decimal:2",
        "total_livros_vendidos" => "integer",
        "qtd_inconsistencias_catalogo" => "integer",
        "dados_graficos" => "array",
        "atualizado_em" => "datetime",
    ];

    /**
     * A estatística pertence a uma feira.
     */
    public function feira(): BelongsTo
    {
        return $this->belongsTo(Feira::class, "id_feira");
    }

    /**
     * Atualiza ou cria o snapshot estatístico para uma feira.
     */
    public static function atualizarSnapshot(Feira $feira, array $novosCalculos)
    {
        return self::updateOrCreate(
            ["id_feira" => $feira->id],
            array_merge($novosCalculos, ["atualizado_em" => now()])
        );
    }
}
