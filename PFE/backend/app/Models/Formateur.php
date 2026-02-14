<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Formateur extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function affectations()
    {
        return $this->hasMany(Affectation::class);
    }

    public function modules()
    {
        return $this->belongsToMany(Module::class, 'teacher_module', 'teacher_id', 'module_id')
            ->withPivot(['academic_year', 'semester', 'weekly_hours'])
            ->withTimestamps();
    }

    public function modulesWithGroupesAndFilieres()
    {
        return $this->modules()->with(['groupes.filiere']);
    }
}
