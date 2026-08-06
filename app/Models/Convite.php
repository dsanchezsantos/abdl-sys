<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Convite extends Model
{
    protected $fillable = [
        'email',
        'token',
        'expires_at',
        'used_at'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime'
    ];

    /**
     * Verifica se o convite expirou.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Verifica se o convite já foi utilizado.
     */
    public function isUsed(): bool
    {
        return !is_null($this->used_at);
    }

    /**
     * Verifica se o convite ainda é válido para uso.
     */
    public function isValid(): bool
    {
        return !$this->isExpired() && !$this->isUsed();
    }
}
