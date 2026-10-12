<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invitation extends Model
{
    use HasFactory;

    protected $table = 'invitations';

    protected $fillable = [
        'id_utilisateur',
        'email',
        'token',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(
            Utilisateur::class,
            'id_utilisateur',
            'id_utilisateur'
        );
    }
}
