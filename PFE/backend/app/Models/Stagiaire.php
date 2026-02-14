<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Stagiaire extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function filiere()
    {
        return $this->belongsTo(Filiere::class);
    }

    public function groupe()
    {
        return $this->belongsTo(Groupe::class);
    }

    public function parent()
    {
        return $this->belongsTo(StudentParent::class, 'parent_id');
    }

    public function groupes()
    {
        return $this->belongsToMany(Groupe::class, 'groupe_stagiaire')
                    ->withTimestamps();
    }

    public function absences()
    {
        return $this->hasMany(Absence::class);
    }

    public function notes()
    {
        return $this->hasMany(Note::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function stages()
    {
        return $this->hasMany(Stage::class);
    }

    /**
     * Canonical filière ID for data isolation: stagiaire's filiere_id or from their groupe.
     * Use when scoping modules/groups/affectations so students only see their filière.
     */
    public function getFiliereIdForScope(): ?int
    {
        if ($this->filiere_id) {
            return (int) $this->filiere_id;
        }
        // Prefer direct groupe_id relation first (legacy/student records may miss pivot enrollment).
        $groupe = $this->relationLoaded('groupe')
            ? $this->groupe
            : ($this->groupe_id ? $this->groupe()->first() : $this->groupes()->first());
        if ($groupe !== null && $groupe->filiere_id !== null && $groupe->filiere_id !== '') {
            return (int) $groupe->filiere_id;
        }
        return null;
    }

    /**
     * Groupe IDs for this stagiaire that belong to the given filière (for strict isolation).
     */
    public function getGroupeIdsInFiliere(int $filiereId): \Illuminate\Support\Collection
    {
        $ids = $this->groupes()->where('groupes.filiere_id', $filiereId)->pluck('groupes.id');
        $groupe = $this->relationLoaded('groupe') ? $this->groupe : ($this->groupe_id ? $this->groupe()->first() : null);
        if ($ids->isEmpty() && $this->groupe_id && $groupe !== null) {
            $groupeFiliereId = $groupe->filiere_id ?? null;
            if ($groupeFiliereId !== null && (int) $groupeFiliereId === $filiereId) {
                return collect([$this->groupe_id]);
            }
        }
        return $ids;
    }
}
