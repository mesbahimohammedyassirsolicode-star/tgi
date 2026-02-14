<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Module extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    public function filiere()
    {
        return $this->belongsTo(Filiere::class);
    }

    public function formateurs()
    {
        return $this->belongsToMany(Formateur::class, 'teacher_module', 'module_id', 'teacher_id')
            ->withPivot(['academic_year', 'semester', 'weekly_hours'])
            ->withTimestamps();
    }

    public function groupes()
    {
        return $this->belongsToMany(Groupe::class, 'module_groupe', 'module_id', 'groupe_id')
            ->withPivot(['academic_year', 'semester', 'planned_hours'])
            ->withTimestamps();
    }

    public function syllabusItems()
    {
        return $this->hasMany(SyllabusItem::class); // Order by 'order'
    }
}
