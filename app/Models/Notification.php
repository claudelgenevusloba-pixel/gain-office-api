<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $table = 'notifications';

    protected $primaryKey =
        'id_notification';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'id_utilisateur',
        'titre',
        'message',
        'type',
        'lien',
        'lu',
        'date_lecture',
    ];

    protected function casts(): array
    {
        return [
            'lu' =>
                'boolean',

            'date_lecture' =>
                'datetime',
        ];
    }

    public function utilisateur()
    {
        return $this->belongsTo(
            Utilisateur::class,
            'id_utilisateur',
            'id_utilisateur'
        );
    }
}
