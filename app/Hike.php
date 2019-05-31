<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Hike extends Model
{
    protected $fillable = ['region_id', 'name', 'wta_hike_id', 'location', 'length', 'elevation_gain', 'highest_point', 'rating', 'description'];

    public function reports()
    {
        return $this->hasMany('App\Report');
    }
}
