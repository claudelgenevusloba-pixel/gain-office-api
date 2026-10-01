<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $table = 'roles';

    protected $primaryKey = 'id_role';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'nom',
        'description',
    ];

    public function utilisateurs()
    {
        return $this->hasMany(
            Utilisateur::class,
            'id_role',
            'id_role'
        );
    }
}
