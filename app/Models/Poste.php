<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Poste extends Model
{
    use HasFactory;

    protected $table = 'postes';

    protected $primaryKey = 'id_poste';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'id_service',
        'libelle',
        'description',
        'statut',
    ];

    public function service()
    {
        return $this->belongsTo(
            Service::class,
            'id_service',
            'id_service'
        );
    }

    public function employes()
    {
        return $this->hasMany(
            Employe::class,
            'id_poste',
            'id_poste'
        );
    }
}
