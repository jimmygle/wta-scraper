<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    protected $fillable = ['hike_id', 'wta_report_id', 'report', 'date'];

    public function hike()
    {
        return $this->belongsTo('App\Hike');
    }
}
