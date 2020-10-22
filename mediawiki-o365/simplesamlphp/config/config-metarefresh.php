<?php

$config = [
    'sets' => [
        'o365' => [
            'cron' => ['hourly'],
            'sources' => [
                [
                    'src' => getenv('FEDERATION_URL'),
                ],
            ],
            'expireAfter' => 34560060, // Maximum 4 days cache time (3600*24*4)
            'outputDir' => 'metadata/o365/',
            'outputFormat' => 'flatfile',
            'types' => ['saml20-idp-remote'],
        ],
    ],
];
