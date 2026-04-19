<?php

declare(strict_types=1);

defined('TYPO3') or die();

$tempColumns = [
    'tx_seograph_schema_type' => [
        'label' => 'LLL:EXT:seo_graph/Resources/Private/Language/locallang.xlf:pages.tx_seograph_schema_type',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectSingle',
            'items' => [
                ['label' => 'WebPage', 'value' => ''],
                ['label' => 'Article', 'value' => 'Article'],
                ['label' => 'BlogPosting', 'value' => 'BlogPosting'],
                ['label' => 'NewsArticle', 'value' => 'NewsArticle'],
                ['label' => 'FAQPage', 'value' => 'FAQPage'],
                ['label' => 'CollectionPage', 'value' => 'CollectionPage'],
                ['label' => 'AboutPage', 'value' => 'AboutPage'],
                ['label' => 'ContactPage', 'value' => 'ContactPage'],
            ],
            'default' => '',
        ],
    ],
    'tx_seograph_primary_image' => [
        'label' => 'LLL:EXT:seo_graph/Resources/Private/Language/locallang.xlf:pages.tx_seograph_primary_image',
        'config' => [
            'type' => 'file',
            'maxitems' => 1,
            'allowed' => 'common-image-types',
        ],
    ],
    'tx_seograph_author' => [
        'label' => 'LLL:EXT:seo_graph/Resources/Private/Language/locallang.xlf:pages.tx_seograph_author',
        'config' => [
            'type' => 'input',
            'size' => 50,
            'max' => 255,
        ],
    ],
    'tx_seograph_exclude' => [
        'label' => 'LLL:EXT:seo_graph/Resources/Private/Language/locallang.xlf:pages.tx_seograph_exclude',
        'config' => [
            'type' => 'check',
            'renderType' => 'checkboxToggle',
        ],
    ],
];

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('pages', $tempColumns);
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes(
    'pages',
    '--div--;SEO Graph, tx_seograph_schema_type, tx_seograph_primary_image, tx_seograph_author, tx_seograph_exclude'
);
