<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employe extends Model
{
    use HasFactory;

    protected $table = 'employes';

    protected $primaryKey = 'id_employe';

    protected $fillable = [
        'id_utilisateur',
        'id_poste',
        'matricule',
        'date_embauche',
        'date_naissance',
        'adresse',
        'ville',
        'photo_identite',
    ];

    protected $casts = [
        'date_embauche' => 'date',
        'date_naissance' => 'date',
    ];

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(
            Utilisateur::class,
            'id_utilisateur',
            'id_utilisateur'
        );
    }

    public function poste(): BelongsTo
    {
        return $this->belongsTo(
            Poste::class,
            'id_poste',
            'id_poste'
        );
    }
}
