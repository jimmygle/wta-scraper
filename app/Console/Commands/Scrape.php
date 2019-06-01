<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;

use App\Report;
use App\Hike;
use App\Location;
use App\Region;

class Scrape extends Command
{

    const QUANTITY_PER_PAGE = 250;

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
        $client = new Client();
        $crawler = $client->request('GET', 'https://www.wta.org/@@search_tripreport_listing?b_size=100&');

        $this->saveReports($crawler);
    }

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

        return (string) $crawler->filter('#trip-report-heading > h1 > a')->extract('href')[0];
    }

    protected function saveHike(string $hikeUrl) : int
    {
        $client = new Client();
        $crawler = $client->request('GET', $hikeUrl);

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
