<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RapportValidation extends Model
{
    use HasFactory;

    protected $table = 'rapport_validations';

    protected $primaryKey = 'id_validation';

    protected $fillable = [
        'id_rapport',
        'id_utilisateur',
        'action',
        'niveau',
        'motif',
    ];

    protected $casts = [
        'niveau' => 'integer',
    ];

    public function rapport(): BelongsTo
    {
        return $this->belongsTo(
            RapportTache::class,
            'id_rapport',
            'id_rapport'
        );
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(
            Utilisateur::class,
            'id_utilisateur',
            'id_utilisateur'
        );
    }
}
