<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;

    protected $table = 'services';

    protected $primaryKey = 'id_service';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'nom',
        'description',
        'statut',
    ];

    public function postes()
    {
        return $this->hasMany(
            Poste::class,
            'id_service',
            'id_service'
        );
    }

    public function employes()
    {
        return $this->hasManyThrough(
            Employe::class,
            Poste::class,
            'id_service',
            'id_poste',
            'id_service',
            'id_poste'
        );
    }
}
