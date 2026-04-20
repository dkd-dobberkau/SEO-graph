<?php
return [
    'web_seograph' => [
        'parent' => 'web',
        'position' => ['after' => 'web_info'],
        'access' => 'user,group',
        'iconIdentifier' => 'module-seograph',
        'labels' => 'LLL:EXT:seo_graph/Resources/Private/Language/locallang_mod.xlf',
        'extensionName' => 'SeoGraph',
        'controllerActions' => [
            \Dkd\SeoGraph\Controller\SeoGraphController::class => ['index'],
        ],
        'moduleClass' => \Dkd\SeoGraph\Controller\SeoGraphController::class,
    ],
];
