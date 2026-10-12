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
        'id_destinataire',
        'travail_effectue',
        'difficultes',
        'temps_passe',
        'commentaire',
        'date_debut_periode',
        'date_fin_periode',
        'date_limite_soumission',
        'statut',
        'niveau_validation',
        'id_decideur',
        'date_decision',
        'motif_decision',
        'rappel_le',
        'date_soumission',
        'date_traitement',
        'commentaire_validation',
    ];

    protected $casts = [
        'temps_passe' => 'decimal:2',
        'date_debut_periode' => 'date',
        'date_fin_periode' => 'date',
        'date_limite_soumission' => 'datetime',
        'date_soumission' => 'datetime',
        'date_traitement' => 'datetime',
        'date_decision' => 'datetime',
        'rappel_le' => 'datetime',
        'niveau_validation' => 'integer',
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

    /**
     * Supérieur hiérarchique destinataire du rapport.
     */
    public function destinataire(): BelongsTo
    {
        return $this->belongsTo(
            Utilisateur::class,
            'id_destinataire',
            'id_utilisateur'
        );
    }

    /**
     * Dernière personne ayant pris une décision.
     */
    public function decideur(): BelongsTo
    {
        return $this->belongsTo(
            Utilisateur::class,
            'id_decideur',
            'id_utilisateur'
        );
    }

    /**
     * Historique complet des étapes et décisions.
     */
    public function validations(): HasMany
    {
        return $this->hasMany(
            RapportValidation::class,
            'id_rapport',
            'id_rapport'
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
