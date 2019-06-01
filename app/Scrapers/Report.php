<?php

namespace App\Scrapers;

use Symfony\Component\DomCrawler\Crawler;
use App\Models\Report as ReportModel;

/**
 * @todo Extract trip features
 */
class Report {

    protected $console;
    protected $rawReport;

    public $url;
    public $wtaId;
    public $date;
    public $content;
    public $model;

    /**
     * Constructor.
     */
    public function __construct($console, $dbSaveCount)
    {
        $this->console = $console;
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
     * Getter method for report URL.
     *
     * @return string
     */
    public function getUrl() : string
    {
        return $this->url;
    }

}