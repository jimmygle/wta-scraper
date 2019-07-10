<?php

namespace App\Scrapers;

use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;

use App\Scrapers\Report;

/**
 * Report Page Entity
 *
 * @todo decouple from Report class
 */
class ReportPage {
    
    protected $reportScraper;
    protected $client;

    public $hikeUrl;
    public $hikeUrlKey;

    /**
     * Constructor.
     *
     * @param Report
     */
    public function __construct(Report $report)
    {
        $this->reportScraper = $report;
        $this->client = new Client();
    }

    /**
     * Extracts hike URL and key after requesting report page.
     *
     * @return void
     */
    public function extractHikeUrlKey() : void
    {
        $reportPage = $this->request();
        try {
            $this->hikeUrl = $reportPage->filter('#trip-report-heading > h1 > a')->extract('href')[0];
            $this->hikeUrlKey = explode('/hikes/', $this->hikeUrl)[1];
        } catch (\Exception $e) {
            $this->hikeUrl = null;
            $this->hikeUrlKey = null;
        }
    }

    /**
     * Perform HTTP request to get report page.
     *
     * @return Crawler
     */
    protected function request() : object
    {
        try {
            $crawler = $this->client->request('GET', $this->reportScraper->url);
            $this->reportScraper->requestCount->increment();
        } catch (\Exception $e) {
            throw $e;
        }
        
        return $crawler;
    }

}