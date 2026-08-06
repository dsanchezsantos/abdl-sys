<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EditoraRepresentante extends Model
{
    use HasFactory;

    protected $table = 'editora_representantes';

    protected $fillable = [
        'id_feira',
        'editora',
        'representante',
    ];

    /**
     * Relacionamento: Pertence a uma feira.
     */
    public function feira()
    {
        return $this->belongsTo(Feira::class, 'id_feira');
    }
}
