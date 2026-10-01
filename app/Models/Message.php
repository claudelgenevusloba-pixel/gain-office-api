<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    protected $table = 'messages';

    protected $primaryKey = 'id_message';

    protected $fillable = [
        'id_tache',
        'id_expediteur',
        'contenu',
        'lu',
        'date_lecture',
    ];

    protected $casts = [
        'lu' => 'boolean',
        'date_lecture' => 'datetime',
    ];

    public function tache(): BelongsTo
    {
        return $this->belongsTo(
            Tache::class,
            'id_tache',
            'id_tache'
        );
    }

    public function expediteur(): BelongsTo
    {
        return $this->belongsTo(
            Utilisateur::class,
            'id_expediteur',
            'id_utilisateur'
        );
    }
}
