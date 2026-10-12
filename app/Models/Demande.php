<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Demande extends Model
{
    use HasFactory;

    protected $table = 'demandes';

    protected $primaryKey = 'id_demande';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'id_demandeur',
        'id_traiteur',
        'type_demande',
        'objet',
        'description',
        'statut',
        'date_demande',
        'date_traitement',
        'commentaire_traitement',
    ];

    protected function casts(): array
    {
        return [
            'date_demande' => 'datetime',
            'date_traitement' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function demandeur()
    {
        return $this->belongsTo(
            Utilisateur::class,
            'id_demandeur',
            'id_utilisateur'
        );
    }

    public function traiteur()
    {
        return $this->belongsTo(
            Utilisateur::class,
            'id_traiteur',
            'id_utilisateur'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | LIBELLÉ DU TYPE
    |--------------------------------------------------------------------------
    */

    public function getTypeLibelleAttribute(): string
    {
        return match ($this->type_demande) {
            'conge' => 'Congé',
            'permission' => 'Permission',
            'absence' => 'Absence',
            'avance' => 'Avance / Acompte',
            'achat' => 'Demande d’achat',
            'materiel' => 'Matériel',
            'support_informatique' => 'Support informatique',
            'mission' => 'Mission / Déplacement',
            'document_administratif' => 'Document administratif',
            'fournitures' => 'Fournitures',
            'recrutement' => 'Recrutement',
            'autre' => 'Autre',
            default => ucfirst(
                str_replace('_', ' ', $this->type_demande)
            ),
        };
    }

    /*
    |--------------------------------------------------------------------------
    | LIBELLÉ DU STATUT
    |--------------------------------------------------------------------------
    */

    public function getStatutLibelleAttribute(): string
    {
        return match ($this->statut) {
            'en_attente' => 'En attente',
            'acceptee' => 'Acceptée',
            'refusee' => 'Refusée',
            'annulee' => 'Annulée',
            default => ucfirst(
                str_replace('_', ' ', $this->statut)
            ),
        };
    }
}
