<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Scrapers Configurations
    |--------------------------------------------------------------------------
    */

    'reportListings' => [

        'listingUrl' => env('SCRAPERS_REPORTLISTINGS_URL', 'https://www.wta.org/@@search_tripreport_listing?b_size={quantity}&b_start:int={start}'),
        'quantityPerPage' => env('SCRAPERS_REPORTLISTINGS_PER_PAGE', 100)

    ],

];
