<?php

namespace Localizationteam\LocalizerBeebox\Hooks;

use Exception;
use Localizationteam\Localizer\Constants;
use Localizationteam\Localizer\Language;
use Localizationteam\LocalizerBeebox\Api\ApiCalls;
use TYPO3\CMS\Core\Messaging\FlashMessage;

/**
 * DataHandler $COMMENT$
 *
 * @author      Peter Russ<peter.russ@4many.net>
 * @date        20150803-2107
 */
class DataHandler
{
    use Language;

    /**
     * hook to post process TCA - Field Array
     * and to alter the configuration
     *
     * @param string $status
     * @param string $table
     * @param mixed $id
     * @param array $fieldArray
     * @param \TYPO3\CMS\Core\DataHandling\DataHandler $tceMain
     */
    public function processDatamap_postProcessFieldArray(
        $status,
        $table,
        $id,
        &$fieldArray,
        \TYPO3\CMS\Core\DataHandling\DataHandler &$tceMain
    ) {
        if ($table !== Constants::TABLE_LOCALIZER_SETTINGS) {
            return;
        }

        if ($this->isSaveAction()) {
            $currentRecord = $tceMain->recordInfo($table, $id, '*');
            if ($currentRecord === null) {
                $currentRecord = [];
            }
            $checkArray = array_merge($currentRecord, $fieldArray);
            if ($checkArray['type'] === 'localizer_beebox') {
                $localizerApi = new ApiCalls(
                    $checkArray['type'],
                    (string)$checkArray['url'],
                    (string)$checkArray['workflow'],
                    (string)$checkArray['projectkey'],
                    (string)$checkArray['username'],
                    (string)$checkArray['password']
                );
                try {
                    $valid = $localizerApi->areSettingsValid();
                    if ($valid === false) {
                        //should never arrive here as exception should occur!
                        $fieldArray['hidden'] = 1;
                    } else {
                        $fieldArray['hidden'] = 0;
                        $fieldArray['project_settings'] = $localizerApi->getProjectInformation(true);
                        $fieldArray['last_error'] = '';
                        new FlashMessage(
                            'Localizer settings [' . $checkArray['title'] . '] successfully validated and saved',
                            'Success',
                            0
                        );
                    }
                } catch (Exception $e) {
                    $fieldArray['last_error'] = $localizerApi->getLastError();
                    $fieldArray['hidden'] = 1;
                    $fieldArray['project_settings'] = '';
                    new FlashMessage($e->getMessage());
                    new FlashMessage('Localizer settings [' . $checkArray['title'] . '] set to hidden', 'Error', 1);
                }
                $localizerApi->disconnect();
            }
        }
    }

    /**
     * @return bool
     */
    protected function isSaveAction()
    {
        return
            isset($_REQUEST['doSave']) && (bool)$_REQUEST['doSave'];
    }
}
