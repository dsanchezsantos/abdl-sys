<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Livro extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_feira',
        'produto_id_api',
        'produto',
        'valor',
        'editora',
        'representante',
        'categoria',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
    ];

    /**
     * Relacionamento: Um livro pertence ao catálogo de uma feira específica.
     */
    public function feira()
    {
        return $this->belongsTo(Feira::class, 'id_feira');
    }

    /**
     * Relacionamento: Histórico de vendas do livro.
     * Nota: A ligação real deve considerar também o id_feira.
     */
    public function itensVenda()
    {
        return $this->hasMany(ItemVenda::class, 'produto_id_api', 'produto_id_api');
    }
}
