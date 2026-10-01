<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParametreEntreprise extends Model
{
    use HasFactory;

    protected $table = 'parametre_entreprises';

    protected $primaryKey = 'id_parametre';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'nom_entreprise',
        'sigle',
        'adresse',
        'ville',
        'pays',
        'telephone',
        'email',
        'site_web',
        'logo',
        'devise',
        'langue',
        'fuseau_horaire',
        'format_date',
        'duree_session',
        'notifications_systeme',
        'notifications_email',
        'statut',
    ];

    protected $casts = [
        'duree_session' =>
            'integer',

        'notifications_systeme' =>
            'boolean',

        'notifications_email' =>
            'boolean',

        'statut' =>
            'boolean',
    ];
}
