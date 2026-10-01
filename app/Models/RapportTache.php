<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RapportTache extends Model
{
    use HasFactory;

    protected $table = 'rapports_taches';

    protected $primaryKey = 'id_rapport';

    protected $fillable = [
        'id_tache',
        'id_auteur',
        'travail_effectue',
        'difficultes',
        'temps_passe',
        'commentaire',
        'statut',
        'date_soumission',
        'date_traitement',
        'commentaire_validation',
    ];

    protected $casts = [
        'temps_passe' => 'decimal:2',
        'date_soumission' => 'datetime',
        'date_traitement' => 'datetime',
    ];

    public function tache(): BelongsTo
    {
        return $this->belongsTo(
            Tache::class,
            'id_tache',
            'id_tache'
        );
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(
            Utilisateur::class,
            'id_auteur',
            'id_utilisateur'
        );
    }

    public function piecesJointes(): HasMany
    {
        return $this->hasMany(
            PieceJointe::class,
            'id_rapport',
            'id_rapport'
        );
    }
}
