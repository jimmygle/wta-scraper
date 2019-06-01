<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    protected $fillable = ['name'];

    public function hikes()
    {
        return $this->hasManyThrough('App\Location', 'App\Hike');
    }

    public function locations()
    {
        return $this->hasMany('App\Location');
    }
}
