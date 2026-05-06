<?php

namespace App\Models;

use App\Enums\CartaoClassificacao;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cartao extends Model
{
    use HasFactory;

    protected $table = 'cartoes';

    protected $fillable = [
        'id_feira',
        'tag_code',
        'grupo',
        'classificacao',
        'identificacao_aluno',
    ];

    protected $casts = [
        'classificacao' => CartaoClassificacao::class,
    ];

    public function feira()
    {
        return $this->belongsTo(Feira::class, 'id_feira');
    }
}
