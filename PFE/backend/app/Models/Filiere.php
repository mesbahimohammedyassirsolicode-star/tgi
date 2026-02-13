<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Filiere extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    public function niveau()
    {
        return $this->belongsTo(Niveau::class);
    }

    public function groupes()
    {
        return $this->hasMany(Groupe::class);
    }

    public function modules()
    {
        return $this->hasMany(Module::class);
    }
}
