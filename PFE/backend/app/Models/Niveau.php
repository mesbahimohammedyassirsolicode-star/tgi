<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Niveau extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'niveaux';
    protected $guarded = ['id'];

    public function filieres()
    {
        return $this->hasMany(Filiere::class);
    }
}
