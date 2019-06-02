<?php

namespace App\Scrapers;

use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;

use App\Scrapers\Report;

class Hike {
    
    protected $console;
    protected $requestCount;
    protected $dbSaveCount;
    protected $reportScraper;
    protected $client;
    protected $rawHike;

    public $name;
    public $length;
    public $elevationGain;
    public $highestPoint;
    public $rating;
    public $description;
    protected $isPublished = true;

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
        $this->rawHike = $this->request();

        $this->extractName();
        $this->extractLength();
        $this->extractElevationGain();
        $this->extractHighestPoint();
        $this->extractRating();
        $this->extractDescription();
    }

    /**
     * Perform HTTP request to get hike page.
     *
     * @return Crawler
     */
    protected function request() : object
    {
        try {
            $crawler = $this->client->request('GET', $this->reportScraper->hikeUrl);
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

}