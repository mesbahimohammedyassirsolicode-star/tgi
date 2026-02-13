<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    protected $table = 'attendances';

    protected $fillable = [
        'seance_id', 'stagiaire_id', 'status', 'retard_minutes',
        'justifie', 'motif', 'justification_doc',
    ];

    protected function casts(): array
    {
        return ['justifie' => 'boolean'];
    }

    public function seance(): BelongsTo
    {
        return $this->belongsTo(Seance::class);
    }

    public function stagiaire(): BelongsTo
    {
        return $this->belongsTo(Stagiaire::class);
    }
}
