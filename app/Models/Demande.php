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
            'date_demande' =>
                'datetime',

            'date_traitement' =>
                'datetime',
        ];
    }

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
}
