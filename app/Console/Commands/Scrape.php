<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;

use App\Scrapers\{ReportListing, Report, Hike};

// use App\Models\{Report, Hike, Location, Region};

class Scrape extends Command
{

    const QUANTITY_PER_PAGE = 200;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wta:scrape';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scrape!';

    protected $totalRequestMade = 0;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // @todo Consolidate these into a single Stats class and/or trait
        $requestCount = new class {
            protected $count = 0;
            public function increment() : void { $this->count++; }
            public function __toString() { return (string) $this->count; }
        };
        $dbSaveCount = new class {
            protected $newReportCount = 0;
            protected $newHikeCount = 0;
            protected $newLocationCount = 0;
            protected $newRegionCount = 0;
            public function incrementReport() : void { $this->newReportCount++; }
            public function incrementHike() : void { $this->newHikeCount++; }
            public function incrementLocation() : void { $this->newLocationCount++; }
            public function incrementRegion() : void { $this->newRegionCount++; }
            public function __toString() { return "{$this->newReportCount} reports, {$this->newHikeCount} hikes, {$this->newLocationCount} locations, and {$this->newRegionCount} regions"; }
        };

        try {
            $reportListing = new ReportListing($this, $requestCount);
            $listingPageReports = $reportListing->first();
            while ($listingPageReports !== false) {
                $listingPageReports->each(function ($rawReport) use ($requestCount, $dbSaveCount) {
                    
                    // Extract report data and get its DB model
                    $report = new Report($this, $requestCount, $dbSaveCount);
                    $report->extract($rawReport);
                    $report->getModel();

                    // Get hike data, extract it, and get its DB model
                    $hike = new Hike($this, $requestCount, $dbSaveCount, $report);
                    $hike->extract();
                    dd($hike->isPublished());
                    $hike->getModel();
                });
                dd();
            }

        } catch (\Exception $e) {
            $this->error('ERROR: ' . $e->getMessage());
            $this->error('FILE: ' . $e->getFile());
            $this->error('LINE: ' . $e->getLine());
        }





        $this->info($requestCount);

        dd();
        $this->info('Getting first ' . number_format(static::QUANTITY_PER_PAGE) . ' trip reports from WTA.');

        $client = new Client();
        $crawler = $client->request('GET', 'https://www.wta.org/@@search_tripreport_listing?b_size=' . static::QUANTITY_PER_PAGE);
        $this->totalRequestMade++;

        $totalSavedReports = (int) Report::count();
        $totalReports = (int) $crawler->filter('#count-data')->text();
        $totalNewReports = $totalReports - $totalSavedReports;
        $totalPages = round($totalReports / static::QUANTITY_PER_PAGE);
        $totalRequests = $totalPages + ($totalNewReports * 2);
        $this->info(number_format($totalReports) . ' total reports on WTA.');
        $this->info(number_format($totalPages) . ' total search result pages on WTA.');
        $this->info(number_format($totalSavedReports) . ' total saved reports in the database.');
        $this->info(number_format($totalNewReports) . ' total new reports on WTA.');
        $this->info(number_format($totalRequests) . ' total HTTP requests to make.');
        dd();

        // TODO: keep count of total new reports and subtract as each request is made so all pages aren't scraped every time.

        $this->saveReports($crawler);
    }


    // Parse all the data
    // Insert all the data after the fact


    protected function saveReports(Crawler $crawler) : void 
    {
        $crawler->filter('div.item > div.item-row')->each(function ($node) {
            $reportUrl = $node->filter('div.item-header > a')->extract('href')[0];
            $wtaReportId = explode('.', explode('trip_report.', $reportUrl)[1])[1];

            $report = Report::firstOrNew(
                ['wta_report_id' => $wtaReportId],
                [
                    'report' => $node->filter('div.report-text > div > div.show-with-full')->html(),
                    'date' => explode('.', explode('trip_report.', $reportUrl)[1])[0]
                ]
            );

            if ($report->exists) {
                //dump($report);
                $this->info("Skipped report {$report->wta_report_id}");
                return;
            }

            $hikeUrl = $this->getHikeUrlFromReportPage($reportUrl);
            $hikeId = $this->saveHike($hikeUrl);

            $report->hike_id = $hikeId;
            $report->save();
            $this->info("Saved report {$report->wta_report_id}");
            unset($report);
        });
    }

    protected function getHikeUrlFromReportPage(string $reportUrl) : string
    {
        $client = new Client();
        $crawler = $client->request('GET', $reportUrl);
        $this->totalRequestMade++;

        return (string) $crawler->filter('#trip-report-heading > h1 > a')->extract('href')[0];
    }

    protected function saveHike(string $hikeUrl) : int
    {
        $client = new Client();
        $crawler = $client->request('GET', $hikeUrl);
        $this->totalRequestMade++;

        if ($crawler->filter('#content > h1')->text(null) == 'Unpublished Hike') {
            $hikeName = 'Unpublished Hike';
        } else {
            $hikeName = $crawler->filter('#hike-top > h1')->text();
        }

        $wtaHikeId = explode('/hikes/', $hikeUrl)[1];

        $hike = Hike::firstOrNew(
            ['wta_hike_id' => $wtaHikeId],
            [
                'name' => $hikeName,
                'length' => $crawler->filter('#distance > span')->text(null),
                'elevation_gain' => $crawler->filter('#hike-stats > div:nth-child(3) > div:nth-child(2) > span')->text(null),
                'highest_point' => $crawler->filter('#hike-stats > div:nth-child(3) > div:nth-child(3) > span')->text(null),
                'rating' => $crawler->filter('#rating-stars-view-trail-rating > div > div.star-rating > div')->text(null),
                'description' => trim($crawler->filter('#hike-body-text')->html(null))
            ]
        );

        if ($hikeName == 'Unpublished Hike') {
            $hike->location_id = null;
        } else {
            $region = Region::firstOrCreate(['name' => $crawler->filter('#hike-region > span')->text(null)]);

            $location = explode(' -- ', $crawler->filter('#hike-stats > div:nth-child(1) > div')->text(null));
            $locationName = array_pop($location);
            $location = Location::firstOrCreate(['name' => $locationName], ['region_id' => $region->id]);
            $hike->location_id = $location->id;
        }

        $hike->save();

        return $hike->id;
    }
}
