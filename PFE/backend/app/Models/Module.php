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

    public function syllabusItems()
    {
        return $this->hasMany(SyllabusItem::class); // Order by 'order'
    }
}
