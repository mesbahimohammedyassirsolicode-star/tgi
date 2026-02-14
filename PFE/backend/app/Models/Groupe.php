<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Groupe extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'groupes';
    protected $guarded = ['id'];

    public function filiere()
    {
        return $this->belongsTo(Filiere::class);
    }

    public function anneeScolaire()
    {
        return $this->belongsTo(AnneeScolaire::class);
    }

    public function stagiaires()
    {
        return $this->belongsToMany(Stagiaire::class, 'groupe_stagiaire')
                    ->withTimestamps();
    }

    public function seances()
    {
        return $this->hasMany(Seance::class, 'groupe_id');
    }
}
