<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Hike extends Model
{
    protected $fillable = ['location_id', 'name', 'url_key', 'length', 'elevation_gain', 'highest_point', 'rating', 'description'];

    public function reports()
    {
        return $this->hasMany('App\Report');
    }

    public function location()
    {
        return $this->belongsTo('App\Location');
    }
}
