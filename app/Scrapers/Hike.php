<?php

namespace App\Scrapers;

use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;

use App\Scrapers\Report;
use App\Models\{Hike as HikeModel, Region as RegionModel, Location as LocationModel};

class Hike {
    
    protected $console;
    protected $requestCount;
    protected $dbSaveCount;
    protected $reportScraper;
    protected $client;
    protected $rawHike;

    public $urlKey;
    public $name;
    public $locationId;
    public $length;
    public $elevationGain;
    public $highestPoint;
    public $rating;
    public $description;
    protected $isPublished = true;
    public $model;
    protected $modelExists = false;
    public $regionModel;
    public $locationModel;

    /**
     * Constructor.
     */
    public function __construct($console, $requestCount, $dbSaveCount, Report $report)
    {
        $this->console = $console;
        $this->requestCount = $requestCount;
        $this->dbSaveCount = $dbSaveCount;
        $this->reportScraper = $report;
        $this->client = new Client();
    }

    /**
     * Extract the hike.
     *
     * @return void
     */
    public function extract() : void
    {
        $this->urlKey = $this->reportScraper->hikeUrlKey;
        $this->getExistingModel();

        if ($this->model instanceof HikeModel === false) {
            $this->rawHike = $this->request();

            $this->extractName();
            $this->extractLength();
            $this->extractElevationGain();
            $this->extractHighestPoint();
            $this->extractRating();
            $this->extractDescription();
            $this->extractRegion();
            $this->extractLocation();
            $this->getModel();
        }
    }

    /**
     * Perform HTTP request to get hike page.
     *
     * @return Crawler
     */
    protected function request() : object
    {
        try {
            $hikeUrl = str_replace('{url_key}', $this->urlKey, config('scrapers.hike.url'));
            $crawler = $this->client->request('GET', $hikeUrl);
            $this->requestCount->increment();
        } catch (\Exception $e) {
            throw $e;
        }
        
        return $crawler;
    }

    /**
     * Extract hike name if hike exists.
     *
     * @return void
     */
    protected function extractName() : void
    {
        if ($this->rawHike->filter('#content > h1')->text(null) == 'Unpublished Hike') {
            $this->name = 'Unpublished Hike';
            $this->isPublished = false;
        } else {
            $this->name = $this->rawHike->filter('#hike-top > h1')->text(null);
        }
    }

    /**
     * Extracts length of hike, reducing to only mileage number.
     *
     * @return void
     */
    protected function extractLength() : void
    {
        $rawLength = $this->rawHike->filter('#distance > span')->text(null);
        $this->length = explode(' miles, roundtrip', $rawLength)[0];
    }

    /**
     * Extracts elevation gain.
     *
     * @return void
     */
    protected function extractElevationGain() : void 
    {
        $this->elevationGain = $this->rawHike->filter('#hike-stats > div:nth-child(3) > div:nth-child(2) > span')->text(null);
    }

    /**
     * Extracts highest point.
     *
     * @return void
     */
    protected function extractHighestPoint() : void 
    {
        $this->highestPoint = $this->rawHike->filter('#hike-stats > div:nth-child(3) > div:nth-child(3) > span')->text(null);
    }

    /**
     * Extracts rating for hike, reducing to only rating number.
     *
     * @return void
     */
    protected function extractRating() : void 
    {
        $rawRating = $this->rawHike->filter('#rating-stars-view-trail-rating > div > div.star-rating > div')->text(null);
        $this->rating = explode(' out of 5', $rawRating)[0];
    }

    /**
     * Extracts description for hike.
     *
     * @return void
     */
    protected function extractDescription() : void 
    {
        $this->description = trim($this->rawHike->filter('#hike-body-text')->html(null));
    }

    /**
     * Is the hike published, or not.
     *
     * @return bool
     */
    public function isPublished() : bool 
    {
        return $this->isPublished;
    }

    /**
     * Attemps to get existing hike model.
     *
     * @return void
     */
    public function getExistingModel() : void 
    {
        $this->model = HikeModel::where('url_key', '=', $this->urlKey)->first();
        if($this->model !== null) 
        {
            $this->modelExists = true;
            $this->console->line("Hike {$this->urlKey} exists in DB. It will be skipped.");
        }
    }

    /**
     * Finds or creates a new hike in the DB.
     *
     * @return void
     */
    public function getModel() : void
    {
        $this->model = HikeModel::create(
            [
                'url_key' => $this->urlKey,
                'location_id' => $this->locationId,
                'name' => $this->name,
                'length' => $this->length,
                'elevation_gain' => $this->elevationGain,
                'highest_point' => $this->highestPoint,
                'rating' => $this->rating,
                'description' => $this->description
            ]
        );
    }

    /**
     * Saves the model if it didn't exist.
     *
     * @return void
     */
    public function saveModel() : void 
    {
        if ($this->modelExists === false) {
            $this->model->save();
        }
    }

    protected function extractRegion() : void 
    {
        if ($this->isPublished === false) {
            return;
        }

        $region = $this->rawHike->filter('#hike-region > span')->text(null);
        $this->regionModel = RegionModel::firstOrCreate(['name' => $region]);
        $this->regionModel->save();
        
    }

    protected function extractLocation() : void 
    {
        if ($this->isPublished === false) {
            return;
        }

        $location = explode(' -- ', $this->rawHike->filter('#hike-stats > div:nth-child(1) > div')->text(null));
        $locationName = array_pop($location);
        $this->locationModel = LocationModel::firstOrCreate(['name' => $locationName], ['region_id' => $this->regionModel->id]);
        $this->locationId = $this->locationModel->id;
        $this->locationModel->save();
    }

}