<?php

return [
    'frontend' => [
        'dkd/seo-graph/inject-json-ld' => [
            'target' => \Dkd\SeoGraph\Middleware\SeoGraphMiddleware::class,
            'after' => [
                'typo3/cms-frontend/page-resolver',
            ],
            'before' => [
                'typo3/cms-frontend/shortcut-and-mountpoint-redirect',
            ],
        ],
    ],
];
