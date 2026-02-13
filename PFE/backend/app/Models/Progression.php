<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Progression extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    public function affectation()
    {
        return $this->belongsTo(Affectation::class);
    }

    public function syllabusItem()
    {
        return $this->belongsTo(SyllabusItem::class);
    }
}
