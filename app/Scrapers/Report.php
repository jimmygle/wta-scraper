<?php

namespace App\Scrapers;

use Symfony\Component\DomCrawler\Crawler;
use App\Models\{Report as ReportModel, Hike as HikeModel};
use App\Scrapers\ReportPage;

/**
 * Report Entity
 *
 * This class represents a report entity. It expects to be given a Crawler instance
 * that's generated by the ReportListing class. Additionally, this class can utilize
 * the ReportPage class to scrape the hike URL key, but that's only if the DB doesn't
 * have the record. This is to reduce the number of HTTP requests. Requesting the full
 * ReportPage is not necessary most of the time.
 *
 * @todo Extract trip features from report listing
 */
class Report {

    protected $console;
    public $requestCount; // @todo decouple from ReportPage
    protected $dbSaveCount;
    protected $rawReport;

    public $url;
    public $wtaId;
    public $date;
    public $content;
    public $hikeName;
    public $hikeUrlKey;
    public $model;
    protected $reportPageScraper;

    /**
     * Constructor.
     */
    public function __construct($console, $requestCount, $dbSaveCount)
    {
        $this->console = $console;
        $this->requestCount = $requestCount;
        $this->dbSaveCount = $dbSaveCount;
    }

    /**
     * Faciliates extracting data from raw report.
     *
     * @param  Crawler
     * @return void
     */
    public function extract(Crawler $rawReport) : void
    {
        $this->rawReport = $rawReport;

        $this->extractUrl();
        $this->extractDate();
        $this->extractWtaId();
        $this->extractContent();
        $this->extractHikeUrlKey();

        $this->console->line("Report {$this->wtaId} extracted.");
    }

    /**
     * Extracts report URL.
     *
     * @return void
     */
    protected function extractUrl()
    {
        $this->url = $this->rawReport->filter('div.item-header > a')->extract('href')[0];
    }


    /**
     * Extracts report date from report URL.
     *
     * Extracts: 2019-05-28
     * From: https://www.wta.org/go-hiking/trip-reports/trip_report.2019-05-28.2546336829
     *
     * @return void
     */
    protected function extractDate() : void
    {
        $uniqueUrlString = $this->extractUniqueUrlString();
        $this->date = explode('.', $uniqueUrlString)[0];
    }

    /**
     * Extracts report ID from report URL.
     *
     * Extracts: 2546336829
     * From: https://www.wta.org/go-hiking/trip-reports/trip_report.2019-05-28.2546336829
     *
     * @return void
     */
    protected function extractWtaId() : void
    {
        $uniqueUrlString = $this->extractUniqueUrlString();
        $this->wtaId = explode('.', $uniqueUrlString)[1];
    }

    /**
     * Helper for extracting unique report URL string that's used for date and ID extraction.
     *
     * Extracts: 2019-05-28.2546336829
     * From: https://www.wta.org/go-hiking/trip-reports/trip_report.2019-05-28.2546336829
     *
     * @return string
     */
    protected function extractUniqueUrlString() : string 
    {
        return explode('trip_report.', $this->url)[1];
    }

    /**
     * Extracts report content.
     *
     * @return void
     */
    protected function extractContent() : void
    {
        $this->content = $this->rawReport->filter('div.report-text > div > div.show-with-full')->html(null);
    }

    /**
     * Finds or creates a new report in the DB.
     *
     * @return void
     */
    public function getModel() : void
    {
        $this->model = ReportModel::firstOrNew(
            [ 'wta_id' => $this->wtaId ],
            [
                'content' => $this->content,
                'date' => $this->date
            ]
        );

        if ($this->model->exists) {
            $this->console->line("Report {$this->wtaId} exists in DB. It will be skipped.");
        }
    }

    /**
     * Extracts hike URL Key.
     *
     * A report parsed from the listing page doesn't have the hike URL, but it does have the name. This
     * method facilitates looking for a hike in the DB matching the name, and returns its url key. If it
     * can not find the hike, it will request the full report page and extract the URL from that page.
     * This logic is to reduce the number of HTTP requests as the hike table continues to be populated.
     *
     * @return void
     */
    protected function extractHikeUrlKey() : void
    {
        $this->hikeName ?? $this->extractHikeName();

        $hike = HikeModel::where('name', '=', $this->hikeName)->first();

        if ($hike !== null && $hike->url_key != '') {
            $this->hikeUrlKey = $hike->url_key;
        } else {
            $this->getHikeUrlFromReportPage();
        }
    }

    /**
     * Extracts hike name.
     *
     * @return void
     */
    protected function extractHikeName() : void
    {
        $this->hikeName = trim($this->rawReport->filter('div.item-header > a')->text(null));
    }

    /**
     * Gets extracted hike URL key from ReportPage scraper.
     *
     * @return void
     */
    protected function getHikeUrlFromReportPage() : void 
    {
        if ($this->reportPageScraper == null) {
            $this->reportPageScraper = new ReportPage($this); // @todo decouple ReportPage from Report
        }

        if ($this->reportPageScraper->hikeUrlKey == null) {
            $this->reportPageScraper->extractHikeUrlKey();
            $this->hikeUrlKey = $this->reportPageScraper->hikeUrlKey;
        }
    }

}