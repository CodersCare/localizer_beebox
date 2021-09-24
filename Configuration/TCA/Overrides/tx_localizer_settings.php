<?php

if (!defined('TYPO3_MODE')) {
    die('Access denied.');
}

$GLOBALS['TCA']['tx_localizer_settings']['columns']['type']['config']['items'][] = [
    'LLL:EXT:localizer_beebox/Resources/Private/Language/locallang_db.xlf:tx_localizer_settings.type.I.localizer_beebox', 'localizer_beebox',
];

$GLOBALS['TCA']['tx_localizer_settings']['types']['localizer_beebox']['showitem'] = 'hidden, --palette--;;1, type, title, description, url, projectkey, username, password, --palette--;;2, --palette--;;3, l10n_cfg, source_locale, target_locale';
