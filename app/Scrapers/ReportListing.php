<?php

namespace App\Scrapers;

use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;

class ReportListing {

    protected $console;
    protected $requestCount;
    protected $quantityPerPage;
    protected $listingUrl;
    protected $client;
    protected $start = 0;

    public $wtaReportCount;
    protected $reportsFetchedCount = 0;

    /**
     * Set initial parameters.
     *
     * @return void
     */
    public function __construct($console, $requestCount)
    {
        $this->console = $console;
        $this->requestCount = $requestCount;
        $this->quantityPerPage = config('scrapers.reportListings.quantityPerPage');
        $this->listingUrl = config('scrapers.reportListings.listingUrl');
        $this->client = new Client();
    }

    /**
     * Initialize report listing scraper and processes first page.
     *
     * @return Crawler
     */
    public function first() : object
    {
        $this->console->info('Fetching first report listings page.');
        $firstListingPage = $this->request($this->start, $this->quantityPerPage);
        $this->extractWtaReportCount($firstListingPage);
        $this->reportsFetchedCount += $this->quantityPerPage;
        return $firstListingPage->filter('div.item > div.item-row');
    }

    public function next() :? object
    {
        $this->start += $this->quantityPerPage;
        $nextListingPage = $this->request($this->start, $this->quantityPerPage);
        $this->reportsFetchedCount += $this->quantityPerPage;
        $listingPage = $nextListingPage->filter('div.item > div.item-row');
        if($listingPage->count() < 1) {
            return null;
        } else {
            return $listingPage;
        }
    }

    /**
     * Perform HTTP request after assembling request URL.
     *
     * @param  int
     * @param  int
     * @return Crawler
     */
    protected function request(int $start, int $quantity = null) : object
    {
        $quantity = $quantity ?? $this->quantityPerPage;
        $listingUrl = $this->listingUrl;

        $listingUrl = str_replace('{start}', $start, $listingUrl);
        $listingUrl = str_replace('{quantity}', $quantity, $listingUrl);

        try {
            $crawler = $this->client->request('GET', $listingUrl);
            $this->requestCount->increment();
        } catch (\Exception $e) {
            throw $e;
        }
        
        return $crawler;
    }

    /**
     * Extracts total number of reports on WTA.
     *
     * @param  Crawler
     * @return void
     */
    protected function extractWtaReportCount(Crawler $listingPage) : void
    {
        $this->wtaReportCount = $listingPage->filter('#count-data')->text(null);
        $formatedCount = number_format($this->wtaReportCount);
        $this->console->info("{$formatedCount} reports on WTA.");
    }

}