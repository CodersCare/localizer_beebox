<?php

/**
 * Extension Manager/Repository config file for ext "localizer_across".
 */
$EM_CONF[$_EXTKEY] = [
    'title' => 'Localizer Across',
    'description' => 'Across API for the TYPO3 localizer',
    'category' => 'templates',
    'constraints' => [
        'depends' => [
            'typo3' => '8.7.0-8.7.99',
            'localizer' => '8.0.0-8.99.99'
        ],
        'conflicts' => [
        ],
    ],
    'autoload' => [
        'psr-4' => [
            'Localizationteam\\LocalizerAcross\\' => 'Classes'
        ],
    ],
    'state' => 'stable',
    'uploadfolder' => 0,
    'createDirs' => '',
    'clearCacheOnLoad' => 1,
    'author' => 'Jo Hasenau',
    'author_email' => 'info@cybercraft.de',
    'author_company' => 'Cybercraft GmbH',
    'version' => '8.0.0',
];
