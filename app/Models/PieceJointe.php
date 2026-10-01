<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PieceJointe extends Model
{
    use HasFactory;

    protected $table = 'pieces_jointes';

    protected $primaryKey = 'id_piece';

    protected $fillable = [
        'id_tache',
        'id_rapport',
        'id_uploader',
        'nom_original',
        'nom_stockage',
        'chemin',
        'extension',
        'mime_type',
        'taille',
    ];

    public function tache(): BelongsTo
    {
        return $this->belongsTo(
            Tache::class,
            'id_tache',
            'id_tache'
        );
    }

    public function rapport(): BelongsTo
    {
        return $this->belongsTo(
            RapportTache::class,
            'id_rapport',
            'id_rapport'
        );
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(
            Utilisateur::class,
            'id_uploader',
            'id_utilisateur'
        );
    }
}
