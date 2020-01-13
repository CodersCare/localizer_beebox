<?php

/**
 * Extension Manager/Repository config file for ext "localizer_beebox".
 */
$EM_CONF[$_EXTKEY] = [
    'title' => 'Localizer Beebox',
    'description' => 'Beebox API for the TYPO3 localizer',
    'category' => 'templates',
    'constraints' => [
        'depends' => [
            'typo3' => '9.5.0-9.5.99',
            'localizer' => '9.0.0-0.0.0'
        ],
        'conflicts' => [
        ],
    ],
    'autoload' => [
        'psr-4' => [
            'Localizationteam\\LocalizerBeebox\\' => 'Classes'
        ],
    ],
    'state' => 'stable',
    'uploadfolder' => 0,
    'createDirs' => '',
    'clearCacheOnLoad' => 1,
    'author' => 'Jo Hasenau',
    'author_email' => 'info@cybercraft.de',
    'author_company' => 'Cybercraft GmbH',
    'version' => '9.0.0',
];
