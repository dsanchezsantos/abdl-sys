<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\CartaoClassificacao;

class Pagamento extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_feira',
        'sell_number',
        'pagamento_id_api',
        'tag_code',
        'cpf',
        'payment_way',
        'value',
        'payment_group',
        'raw_payload',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'raw_payload' => 'array',
    ];

    /**
     * Relacionamento: O pagamento sabe a que venda pertence.
     */
    public function vendaHeader()
    {
        return $this->belongsTo(VendaHeader::class, 'sell_number', 'sell_number')
                    ->where('id_feira', $this->id_feira);
    }

    /**
     * Relacionamento: O pagamento conecta a transação ao utilizador físico (Tag).
     */
    public function cartao()
    {
        return $this->belongsTo(Cartao::class, 'tag_code', 'tag_code')
                    ->where('id_feira', $this->id_feira);
    }

    /**
     * Scope: Filtro de Ouro - Filtra apenas pagamentos válidos para repasse às editoras.
     */
    public function scopeValidosParaRateio($query)
    {
        return $query->where('payment_way', '!=', 'Desconto')
                     ->where('payment_group', '!=', 'Pagamento sem grupo')
                     ->whereHas('cartao', function ($q) {
                         // Ignora tags marcadas como TESTE
                         $q->where('classificacao', '!=', CartaoClassificacao::TESTE->value);
                     });
    }
}
