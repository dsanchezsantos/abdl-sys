<?php

namespace App\Models;

use App\Enums\FeiraStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Feira extends Model
{
    use HasFactory;

    protected $fillable = [
        "nome",
        "data_inicio",
        "data_fim",
        "evento_id_api",
        "user_id_api",
        "ultima_sincronizacao_em",
        "status",
        "is_sincronizando",
        "ultimo_batch_id",
        "status_integridade",
    ];

    protected $casts = [
        "data_inicio" => "datetime",
        "data_fim" => "datetime",
        "ultima_sincronizacao_em" => "datetime",
        "status" => FeiraStatus::class,
        "is_sincronizando" => "boolean",
    ];

    /**
     * Uma feira tem apenas uma estatística (Snapshot).
     */
    public function estatistica(): HasOne
    {
        return $this->hasOne(FeiraEstatistica::class, "id_feira");
    }

    public function cartoes(): HasMany
    {
        return $this->hasMany(Cartao::class, "id_feira");
    }

    public function livros(): HasMany
    {
        return $this->hasMany(Livro::class, "id_feira");
    }

    public function vendas(): HasMany
    {
        return $this->hasMany(VendaHeader::class, "id_feira");
    }

    public function relatorios(): HasMany
    {
        return $this->hasMany(Relatorio::class, "id_feira");
    }

    public function editorasRepresentantes(): HasMany
    {
        return $this->hasMany(EditoraRepresentante::class, "id_feira");
    }
}
