<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Utilisateur extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'utilisateurs';

    protected $primaryKey = 'id_utilisateur';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'id_role',
        'nom',
        'prenom',
        'email',
        'telephone',
        'mot_de_passe',
        'photo',
        'statut',
        'dernier_acces',
    ];

    protected $hidden = [
        'mot_de_passe',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'dernier_acces' => 'datetime',
        ];
    }

    /**
     * Laravel doit utiliser mot_de_passe
     * comme champ contenant le mot de passe.
     */
    public function getAuthPasswordName()
    {
        return 'mot_de_passe';
    }

    public function getAuthPassword()
    {
        return $this->mot_de_passe;
    }

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function role()
    {
        return $this->belongsTo(
            Role::class,
            'id_role',
            'id_role'
        );
    }

    public function employe()
    {
        return $this->hasOne(
            Employe::class,
            'id_utilisateur',
            'id_utilisateur'
        );
    }

    public function tachesCreees()
    {
        return $this->hasMany(
            Tache::class,
            'id_createur',
            'id_utilisateur'
        );
    }

    public function tachesAssignees()
    {
        return $this->hasMany(
            Tache::class,
            'id_assigne',
            'id_utilisateur'
        );
    }

    public function demandes()
    {
        return $this->hasMany(
            Demande::class,
            'id_demandeur',
            'id_utilisateur'
        );
    }

    public function demandesTraitees()
    {
        return $this->hasMany(
            Demande::class,
            'id_traiteur',
            'id_utilisateur'
        );
    }

    public function notifications()
    {
        return $this->hasMany(
            Notification::class,
            'id_utilisateur',
            'id_utilisateur'
        );
    }

    public function messages()
    {
        return $this->hasMany(
            Message::class,
            'id_expediteur',
            'id_utilisateur'
        );
    }

    public function piecesJointes()
    {
        return $this->hasMany(
            PieceJointe::class,
            'id_uploader',
            'id_utilisateur'
        );
    }

    public function historiques()
    {
        return $this->hasMany(
            Historique::class,
            'id_utilisateur',
            'id_utilisateur'
        );
    }
}
