<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'bag' => [
        'base_url' => env('BAG_API_BASE_URL', 'https://api.bag.kadaster.nl/lvbag/individuelebevragingen/v2'),
        'api_key' => env('BAG_API_KEY'),
    ],

    'google_maps' => [
        'api_key' => env('GOOGLE_MAPS_API_KEY'),
    ],

    'pdok' => [
        'kadastraal_wms_url' => env('PDOK_KADASTRAAL_WMS_URL', 'https://service.pdok.nl/kadaster/kadastralekaart/wms/v5_0'),
        'kadastraal_wms_layer' => env('PDOK_KADASTRAAL_WMS_LAYER', 'Perceel,Label,KadastraleGrens'),
        'wegenkaart_grijs_wmts_url' => env('PDOK_WEGENKAART_GRIJS_WMTS_URL', 'https://service.pdok.nl/brt/achtergrondkaart/wmts/v2_0'),
        'wegenkaart_grijs_wmts_layer' => env('PDOK_WEGENKAART_GRIJS_WMTS_LAYER', 'grijs'),
        'wegenkaart_grijs_wmts_matrixset' => env('PDOK_WEGENKAART_GRIJS_WMTS_MATRIXSET', 'EPSG:3857'),
        'bodemkaart_wms_url' => env('PDOK_BODEMKAART_WMS_URL'),
        'bodemkaart_wms_layer' => env('PDOK_BODEMKAART_WMS_LAYER', 'bodemkaart'),
        'bodemverontreiniging_wms_url' => env('PDOK_BODEMVERONTREINIGING_WMS_URL'),
        'bodemverontreiniging_wms_layer' => env('PDOK_BODEMVERONTREINIGING_WMS_LAYER'),
        'energielabel_wms_url' => env('PDOK_ENERGIELABEL_WMS_URL', 'https://data.rivm.nl/geo/nl/wms'),
        'energielabel_wms_layer' => env('PDOK_ENERGIELABEL_WMS_LAYER', 'rvo_energielabels'),
        'ruimtelijke_plannen_wms_url' => env('PDOK_RUIMTELIJKE_PLANNEN_WMS_URL', 'https://service.pdok.nl/kadaster/ruimtelijke-plannen/wms/v1_0'),
    ],

    'rce' => [
        'sparql_url' => env('RCE_SPARQL_URL', 'https://api.linkeddata.cultureelerfgoed.nl/datasets/rce/cho/services/cho/sparql'),
    ],

];
