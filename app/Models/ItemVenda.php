<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemVenda extends Model
{
    use HasFactory;

    protected $table = 'itens_venda';

    protected $fillable = [
        'id_feira',
        'sell_number',
        'produto_id_api',
        'name',
        'amount',
        'unit_value',
        'total_value',
        'raw_payload',
    ];

    protected $casts = [
        'amount' => 'integer',
        'unit_value' => 'decimal:2',
        'total_value' => 'decimal:2',
        'raw_payload' => 'array',
    ];

    /**
     * Relacionamento: O item sabe a que cabeçalho pertence.
     */
    public function vendaHeader()
    {
        return $this->belongsTo(VendaHeader::class, 'sell_number', 'sell_number')
                    ->where('id_feira', $this->id_feira);
    }

    /**
     * Relacionamento: O item sabe qual é o livro no catálogo.
     */
    public function livro()
    {
        return $this->belongsTo(Livro::class, 'name', 'produto')
                    ->whereColumn('livros.id_feira', 'itens_venda.id_feira');
    }
}
