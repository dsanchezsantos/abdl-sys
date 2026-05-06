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
                    ->whereColumn('venda_headers.id_feira', 'pagamentos.id_feira');
    }

    /**
     * Relacionamento: O pagamento conecta a transação ao utilizador físico (Tag).
     */
    public function cartao()
    {
        return $this->belongsTo(Cartao::class, 'tag_code', 'tag_code')
                    ->whereColumn('cartoes.id_feira', 'pagamentos.id_feira');
    }

    /**
     * Scope: Filtro de Ouro - Filtra apenas pagamentos válidos para repasse às editoras.
     */
    public function scopeValidosParaRateio($query)
    {
        return $query
            // 1. Réplica do Python: df[paymentWay != 'DESCONTO']
            ->whereRaw('UPPER(payment_way) NOT LIKE ?', ['%DESCONTO%'])
            
            // 2. Réplica do Python: df[payment_group != 'PAGAMENTO SEM GRUPO']
            ->whereRaw('UPPER(payment_group) NOT LIKE ?', ['%PAGAMENTO SEM GRUPO%'])
            
            // 3. Réplica do Python: merge(df_cartoes, how='inner')
            // Exige que o pagamento tenha um cartão vinculado no banco e que NÃO seja de teste.
            // Isso automaticamente barra PIX, Dinheiro e Cartões Inválidos.
            ->whereHas('cartao', function ($q) {
                $q->where('classificacao', '!=', CartaoClassificacao::TESTE->value);
            });
    }
}
