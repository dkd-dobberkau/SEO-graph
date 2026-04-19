<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'SEO Graph',
    'description' => 'Opinionated JSON-LD graph layer assembling a linked @graph with stable @ids',
    'category' => 'fe',
    'author' => 'dkd Internet Service GmbH',
    'author_email' => 'info@dkd.de',
    'author_company' => 'dkd Internet Service GmbH',
    'state' => 'alpha',
    'version' => '0.1.0',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-13.4.99',
            'php' => '8.2.0-8.4.99',
        ],
        'suggests' => [
            'schema' => '3.0.0-3.99.99',
        ],
    ],
];
