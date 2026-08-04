<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendaHeader extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_feira',
        'sell_number',
        'sale_type',
        'total_value',
        'date_hour',
        'box',
        'processado',
        'raw_payload',
    ];

    protected $casts = [
        'date_hour' => 'datetime',
        'total_value' => 'decimal:2',
        'processado' => 'boolean',
        'raw_payload' => 'array',
    ];

    /**
     * Relacionamento: Sabe exatamente a que evento pertence.
     */
    public function feira()
    {
        return $this->belongsTo(Feira::class, 'id_feira');
    }

    /**
     * Relacionamento: Pagamentos realizados nesta venda.
     */
    public function pagamentos()
    {
        return $this->hasMany(Pagamento::class, 'sell_number', 'sell_number');
    }

    /**
     * Relacionamento: Itens comprados nesta venda.
     */
    public function itensVenda()
    {
        return $this->hasMany(ItemVenda::class, 'sell_number', 'sell_number');
    }
}
