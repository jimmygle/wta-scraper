<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $fillable = ['name', 'region_id'];

    public function region()
    {
        return $this->belongsTo('App\Region');
    }

    public function hikes()
    {
        return $this->hasMany('App\Hike');
    }
}
