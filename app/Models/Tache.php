<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tache extends Model
{
    use HasFactory;

    protected $table = 'taches';

    protected $primaryKey = 'id_tache';

    protected $fillable = [
        'id_createur',
        'id_assigne',
        'titre',
        'description',
        'priorite',
        'statut',
        'date_debut',
        'date_echeance',
        'date_terminaison',
        'date_validation',
        'commentaire_validation',
    ];

    protected $casts = [
        'date_debut' => 'datetime',
        'date_echeance' => 'datetime',
        'date_terminaison' => 'datetime',
        'date_validation' => 'datetime',
    ];

    public function createur(): BelongsTo
    {
        return $this->belongsTo(
            Utilisateur::class,
            'id_createur',
            'id_utilisateur'
        );
    }

    public function assigne(): BelongsTo
    {
        return $this->belongsTo(
            Utilisateur::class,
            'id_assigne',
            'id_utilisateur'
        );
    }

    public function rapports(): HasMany
    {
        return $this->hasMany(
            RapportTache::class,
            'id_tache',
            'id_tache'
        );
    }

    public function messages(): HasMany
    {
        return $this->hasMany(
            Message::class,
            'id_tache',
            'id_tache'
        );
    }

    public function piecesJointes(): HasMany
    {
        return $this->hasMany(
            PieceJointe::class,
            'id_tache',
            'id_tache'
        );
    }
}
