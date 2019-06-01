<?php

namespace App\Scrapers;

use App\Scrapers\Report;

class Hike {
    
    protected $console;
    protected $requestCount;
    protected $dbSaveCount;
    protected $report;

    public function __construct($console, $requestCount, $dbSaveCount, Report $report)
    {
        $this->console = $console;
        $this->requestCount = $requestCount;
        $this->dbSaveCount = $dbSaveCount;
        $this->report = $report;
    }

    public function request() : void
    {

    }

}