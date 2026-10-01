<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Historique extends Model
{
    use HasFactory;

    protected $table = 'historiques';

    protected $primaryKey = 'id_historique';

    public $timestamps = false;

    protected $fillable = [
        'id_utilisateur',
        'action',
        'module',
        'description',
        'ancienne_valeur',
        'nouvelle_valeur',
        'adresse_ip',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'ancienne_valeur' => 'array',
        'nouvelle_valeur' => 'array',
        'created_at' => 'datetime',
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
