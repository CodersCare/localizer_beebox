<?php

/**
 * Extension Manager/Repository config file for ext "localizer_beebox".
 */
$EM_CONF['localizer_beebox'] = [
    'title' => 'Localizer Beebox',
    'description' => 'Beebox API for the TYPO3 localizer',
    'category' => 'templates',
    'constraints' => [
        'depends' => [
            'typo3' => '10.4.0-10.4.99',
            'localizer' => '10.0.0-0.0.0',
        ],
        'conflicts' => [
        ],
    ],
    'autoload' => [
        'psr-4' => [
            'Localizationteam\\LocalizerBeebox\\' => 'Classes',
        ],
    ],
    'state' => 'stable',
    'uploadfolder' => 0,
    'createDirs' => '',
    'clearCacheOnLoad' => 1,
    'author' => 'Jo Hasenau',
    'author_email' => 'info@cybercraft.de',
    'author_company' => 'Cybercraft GmbH',
    'version' => '10.0.0',
];
