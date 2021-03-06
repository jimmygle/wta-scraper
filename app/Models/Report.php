<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    protected $fillable = ['hike_id', 'wta_id', 'content', 'date'];

    public function hike()
    {
        return $this->belongsTo('App\Hike');
    }
}
