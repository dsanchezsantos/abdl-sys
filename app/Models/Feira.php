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
        "endpoint_url",
        "api_username",
        "api_password",
        "evento_id_api",
        "user_id_api",
        "ultima_sincronizacao_em",
        "status",
        "is_sincronizando",
        "ultimo_batch_id",
        "status_integridade",
    ];

    protected $hidden = [
        "api_password",
    ];

    protected $casts = [
        "data_inicio" => "datetime",
        "data_fim" => "datetime",
        "ultima_sincronizacao_em" => "datetime",
        "status" => FeiraStatus::class,
        "is_sincronizando" => "boolean",
        "api_password" => "encrypted",
    ];

    /**
     * Retorna o scheme e host base do endpoint_url (ex: https://feiradolivro-cidade.nowigo.com.br).
     */
    public function getBaseUrlAttribute(): string
    {
        if (empty($this->endpoint_url)) {
            $default = config('services.nowigo.base_url', '');
            $parsed = parse_url($default);
            return isset($parsed['host']) ? "{$parsed['scheme']}://{$parsed['host']}" : $default;
        }

        $parsed = parse_url($this->endpoint_url);
        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'] ?? '';
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';

        return !empty($host) ? "{$scheme}://{$host}{$port}" : $this->endpoint_url;
    }

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
