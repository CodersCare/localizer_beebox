<?php

namespace Localizationteam\LocalizerBeebox\Api;

/**
 * ApiCalls Class used to make calls to the Localizer API
 *
 * @author      Peter Russ<peter.russ@4many.net>, Jo Hasenau<jh@cybercraft.de>
 * @package     TYPO3
 * @subpackage  localizer
 *
 */
class ApiCalls extends \Localizationteam\Localizer\Api\ApiCalls
{

    protected array $align;

    /**
     * @param int $type
     * @param string $url
     * @param string $workflow
     * @param string $projectKey
     * @param string $username
     * @param string $password
     */
    public function __construct(
        $type,
        $url = '',
        $workflow = '',
        $projectKey = '',
        $username = '',
        $password = ''
    ) {
        parent::__construct($type);
        $this->connectorName = 'Beebox Connector';
        $this->connectorVersion = '9.0.0';
        $this->setUrl($url);
        $this->setWorkflow($workflow);
        $this->setProjectKey($projectKey);
        $this->setUsername($username);
        $this->setPassword($password);
    }

    /**
     * Checks the beebox settings like url, project key, login and password.
     * By default will close connection after check.
     * If there is any existing connection at check time this will be closed prior to check
     *
     * @param bool $closeConnectionAfterCheck
     * @return bool
     * @throws \Exception
     */
    public function areSettingsValid($closeConnectionAfterCheck = true)
    {
        if ($this->isConnected()) {
            $this->disconnect();
        }
        $areValid = $this->connect();
        if ($closeConnectionAfterCheck === true) {
            if ($areValid === true) {
                $this->disconnect();
            }
        }

        return $areValid;
    }

    /**
     * Checks if the token is set
     *
     * @return boolean True if the token is a non empty string, false otherwise
     */
    public function isConnected()
    {
        return !empty($this->token);
    }

    public function disconnect()
    {

        if (!$this->isConnected()) {
            return;
        }

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL,
            $this->url .
            '/api/disconnect?token=' . urlencode($this->token)
        );
        curl_exec($curl);
        $this->token = null;
    }

    /**
     * Tries to connect to the Beebox using the plugin parameters
     *
     * @return boolean true if the connection is successful, false otherwise
     * @throws \Exception This Exception contains details of an eventual error
     */
    public function connect()
    {
        if ($this->doesLocalizerExist()) {
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 20);
            curl_setopt($curl, CURLOPT_TIMEOUT, 15);
            curl_setopt($curl, CURLOPT_URL,
                $this->url .
                '/api/connect?connector=' . urlencode($this->connectorName) .
                '&version=' . urlencode($this->connectorVersion) .
                '&project=' . urlencode($this->projectKey) .
                '&login=' . urlencode($this->username) .
                '&pwd=' . urlencode($this->password)
            );
            $content = curl_exec($curl);
            $this->checkResponse($curl, $content);
            $this->token = $content;

            return $this->isConnected();
        } else {
            throw new \Exception('No Beebox found at given URL ' . $this->url . '. Either the URL is wrong or Beebox is not active!');
        }

    }

    /**
     * @return bool
     */
    protected function doesLocalizerExist()
    {
        $doesExist = false;
        $response = file_get_contents($this->url . '/whois');
        if ($response !== '') {
            $answer = json_decode($response, true);
            if ($answer !== null) {
                if (is_array($answer)) {
                    if (isset($answer['name'])) {
                        $doesExist = strtolower($answer['name']) === 'beebox api';
                    }
                }
            }
        }
        return $doesExist;
    }

    /**
     * @param resource $curl
     * @param string $content
     * @throws \Exception
     */
    private function checkResponse($curl, $content)
    {
        $http_status_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $this->lastError = '';
        if ($http_status_code != 200 && $http_status_code != 204) {
            $details = json_decode($content, true);
            if (is_array($details) === false) {
                $details = (array)$details;
            }
            $details['http_status_code'] = $http_status_code;
            if (curl_errno($curl) !== CURLE_OK) {
                $details['curl_error'] = curl_error($curl);
            }

            $this->lastError = $details['message'];

            throw new \Exception('Communication error with the Beebox, see the details : (' . var_export($details,
                    true) . ')');
        }
    }

    /**
     * @param string $sourceLanguage
     * @throws \Exception
     */
    public function setSourceLanguage($sourceLanguage)
    {
        if ($sourceLanguage !== '') {
            $projectLanguages = $this->getProjectLanguages();
            if (isset($projectLanguages[$sourceLanguage])) {
                $this->sourceLanguage = $sourceLanguage;
            } else {
                throw new \Exception('Source language ' . $sourceLanguage . ' not specified for this project ' .
                    $this->projectKey . '. Allowed ' . join(' ', array_keys($projectLanguages)));
            }
        }
    }

    /**
     * Calls the Beebox API to retrieve the Beebox project source and target
     * languages
     *
     * @return array the language pairs available in the Beebox project like 'source' => 'target1' => 1
     *                                                                                   'target2' => 1
     *                                                                                   'targetX' => 1
     * @throws \Exception This Exception contains details of an eventual error
     */
    public function getProjectLanguages()
    {
        if ($this->projectLanguages === null) {

            $array = $this->getProjectInformation();
            $target = [];
            foreach ($array['targetLocales'] as $num => $targetLocale) {
                $target[$targetLocale] = 1;
            }
            $this->projectLanguages[$array['sourceLocale']] = $target;

        }
        return $this->projectLanguages;
    }

    /**
     * @param bool $asJson
     * @return string|array
     * @throws \Exception
     */
    public function getProjectInformation($asJson = false)
    {
        if ($this->projectInformation === null) {
            if (!$this->isConnected()) {
                $this->connect();
            }

            $curl = curl_init();
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_URL,
                $this->url .
                '/api/details?token=' . urlencode($this->token)
            );
            $content = curl_exec($curl);

            $this->checkResponse($curl, $content);
            $this->projectInformation = $content;
        }

        return $asJson === true ? $this->projectInformation : json_decode($this->projectInformation, true);
    }

    /**
     *
     * Instructs the Beebox to look for translated files in the Beebox "out" directory.
     * If translated files are found, these will be aligned with the source file for the purpose of pretranslation.
     *
     * @param array $align
     * @throws \Exception
     */
    public function setAlign(array $align)
    {
        $this->align = $this->validateTargetLocales($align);
    }

    public function isAlignSet(): bool
    {
        return !empty($this->align);
    }

    /**
     * @param array $locales
     * @return array validated locales should be same as input otherwise an exception will be thrown
     * @throws \Exception
     */
    private function validateTargetLocales(array $locales)
    {
        $validateLocales = [];
        $sourceLanguage = $this->getSourceLanguage();
        $projectLanguages = $this->getProjectLanguages();
        foreach ($locales as $locale) {
            if (isset($projectLanguages[$sourceLanguage][$locale])) {
                $validateLocales[] = $locale;
            } else {
                throw new \Exception($locale . ' not defined for this project ' . $this->projectKey
                    . '. Available locales ' . join(' ', array_keys($projectLanguages[$sourceLanguage])));
            }
        }

        return $validateLocales;
    }

    /**
     * Determine the source language for a Beebox project.
     * Will throw an exception if there are more so the source ha to be set
     *
     * @return string the source language
     * @throws \Exception
     */
    public function getSourceLanguage()
    {
        if ($this->sourceLanguage === '') {
            $projectLanguages = $this->getProjectLanguages();
            $sourceLanguages = array_keys($projectLanguages);
            if (count($sourceLanguages) === 1) {
                $this->sourceLanguage = $sourceLanguages[0];
            } else {
                throw new \Exception('For this project ' . $this->projectKey
                    . ' is more than one source language available. Please specify ' . join(' ', $sourceLanguages));
            }
        }

        return $this->sourceLanguage;
    }

    /**
     * Deletes the specified file in the Beebox
     *
     * @param String $filename Name of the file you wish to delete
     * @param String $source source language of the file
     * @throws \Exception This Exception contains details of an eventual error
     */
    public function deleteFile($filename, $source)
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL,
            $this->url .
            '/api/files/file?token=' . urlencode($this->token) .
            '&locale=' . $source . '&filename=' . urlencode($filename) .
            '&folder='
        );
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
        $content = curl_exec($curl);

        $this->checkResponse($curl, $content);
    }

    /**
     * Retrieves work progress of the Beebox for the specified files, if no file specified it will retrieve every file
     *
     * @param mixed $files Can be an array containing a list of file-names or false if you do no want to filter
     * (false by default)
     * @param int $skip Optional number, default is 0. Used for pagination. The files to skip.
     * @param int $count Optional number, default is 100. Used for pagination and indicates the total number of files
     *                   to return from this call. Make sure to specify a limit corresponding to your page
     *                   size (e.g. 100).
     * @return array corresponding to the json returned by the Beebox API
     * @throws \Exception This Exception contains details of an eventual error
     */
    public function getWorkProgress($files = false, $skip = null, $count = null)
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        $query = [
            'token'  => $this->token,
            'filter' => [],
        ];

        if (is_array($files)) {
            $query['filter']['filePaths'] = [];
            foreach ($files as $file) {
                if ($file !== '') {
                    $query['filter']['filePaths'][] = [
                        'Item1' => '',
                        'Item2' => $file,
                    ];
                }
            }
            if (empty($query['filter']['filePaths'])) {
                unset($query['filter']['filePaths']);
            }
        }

        if ($skip !== null) {
            if ($skip > 0) {
                $query['skip'] = (int)$skip;
            }
        }
        if ($count !== null) {
            if ($count > 0) {
                $query['count'] = (int)$count;
            }
        }
        $json = json_encode($query);
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL,
            $this->url .
            '/api/workprogress/translatedfiles'
        );

        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl, CURLOPT_POSTFIELDS, $json);
        $content = curl_exec($curl);

        $this->checkResponse($curl, $content);
        return json_decode($content, true);
    }

    /**
     * Downloads the specified file
     *
     * @param String $filename Name of the file you wish to retrieve
     * @param String $folder Name of the folder where the file is located (usually the target language)
     * @return String The content of the file
     * @throws \Exception This Exception contains details of an eventual error
     */
    public function getFile($filename, $folder)
    {
        if (!$this->isConnected()) {
            $this->connect();
        }
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_URL,
            $this->url .
            '/api/files/file?token=' . urlencode($this->token) .
            '&locale=&folder=' . urlencode($folder) .
            '&filename=' . urlencode($filename)
        );
        $content = curl_exec($curl);

        $this->checkResponse($curl, $content);

        return $content;
    }

    /**
     * Tells the Beebox to scan its files
     *
     * @throws \Exception This Exception contains details of an eventual error
     */
    public function scanFiles()
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_URL,
            $this->url .
            '/api/files/operations/scan?token=' . urlencode($this->token)
        );
        curl_setopt($curl, CURLOPT_PUT, 1);
        $content = curl_exec($curl);

        $this->checkResponse($curl, $content);
    }

    /**
     * Asks to the Beebox if a scan is required
     *
     * @return boolean True if a scan is required, false otherwise
     * @throws \Exception This Exception contains details of an eventual error
     */
    public function scanRequired()
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_URL,
            $this->url .
            '/api/files/status?token=' . urlencode($this->token)
        );
        $content = curl_exec($curl);

        $this->checkResponse($curl, $content);

        $array = json_decode($content, true);
        if (is_array($array) && isset($array['scanRequired'])) {
            return (boolean)$array['scanRequired'];
        } else {
            throw new \Exception('unexpected result from: scan required');
        }
    }

    /**
     * This method empties the sandbox.
     *
     * If you organise your files in sub directories such as in "folder1000\file1.dox", etc. you may selectively empty
     * the sandbox by folder ("directory name" set to "folder1000").
     *
     * @param string $directoryName
     * @throws \Exception
     */
    public function sandboxClear($directoryName = '')
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        $curl = curl_init();
        $url = $this->url .
            '/api/files/directory?token=' . urlencode($this->token) .
            '&locale=sandbox';

        if ($directoryName !== '') {
            $url .= '&directoryname=' . urlencode((string)$directoryName);
        }

        curl_setopt($curl, CURLOPT_URL,
            $url
        );
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
        $content = curl_exec($curl);

        $this->checkResponse($curl, $content);

    }

    /**
     * @param String $fileName Name the file will have in the Localizer
     * @param string $source Source language of the file
     * @throws Exception This Exception contains details of an eventual error
     */
    public function sendInstructions($fileName, $source)
    {
        $instructions = $this->getInstructions();
        if (is_array($instructions)) {
            $content = json_encode($instructions);
            $instructionFilename = $fileName . '.beebox';
            $this->sendFile($content, $instructionFilename, $source, false);
        }
    }


    /**
     * Sends 1 file to the Beebox 'in' folder
     *
     * @param String $fileContent The content of the file you wish to send
     * @param String $fileName Name the file will have in the Beebox
     * @param string $source Source language of the file
     * @param bool $attachInstruction
     * @throws \Exception This Exception contains details of an eventual error
     */
    public function sendFile($fileContent, $fileName, $source, $attachInstruction = true)
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        if ($attachInstruction === true) {
            $this->sendInstructions($fileName, $source);
        }

        $fh = fopen('php://temp/maxmemory:256000', 'w');
        if ($fh) {
            fwrite($fh, $fileContent);
        }

        fseek($fh, 0);
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL,
            $this->url .
            '/api/files/file?token=' . urlencode($this->token) .
            '&locale=' . $source .
            '&filename=' . urlencode($fileName)
        );
        curl_setopt($curl, CURLOPT_PUT, 1);
        curl_setopt($curl, CURLOPT_INFILE, $fh);
        curl_setopt($curl, CURLOPT_INFILESIZE, strlen($fileContent));
        $content = curl_exec($curl);

        fclose($fh);

        $this->checkResponse($curl, $content);
    }

    /**
     * (GET) /api/async/operation/status?token=&opid=
     *
     * @param string $operationId
     */

    /**
     *
     * Request counts/cost
     *
     * (@see http://documents.wordbee.com/display/bb/API+-+Sandbox+-+Counts+and+cost
     *
     * @param bool $includeCost
     * @return string returns a JSON object with property “op id” and which identifies the asynchronous operation
     * @throws \Exception
     */
    public function sandboxRequestCostAndCounts($includeCost = false)
    {

        if (!$this->isConnected()) {
            $this->connect();
        }

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_URL,
            $this->url .
            '/api/files/operations/sandbox/count?token=' . urlencode($this->token) .
            '&getcost=' . ($includeCost === true ? 'true' : 'false')
        );
        curl_setopt($curl, CURLOPT_PUT, 1);
        $operationId = curl_exec($curl);

        $this->checkResponse($curl, $operationId);


        return $operationId;
    }

    /**
     * @param $operationId
     * @return string JSON string
     * @throws \Exception
     * @see http://documents.wordbee.com/display/bb/API+-+Sandbox+-+Counts+and+cost Returns
     */
    public function sandboxGetAsynchronousCostAndCountsResult($operationId)
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_URL,
            $this->url .
            '/api/async/operation/status?token=' . urlencode($this->token) .
            '&opid=' . $operationId
        );
        $content = curl_exec($curl);

        $this->checkResponse($curl, $content);

        return $content;
    }

    /**
     * This method copies all sandbox content for regular translation.
     * This is equivalent to sending all the source content one by one to the Beebox using the regular method.
     */
    public function sandboxCommitContent()
    {
        if ($this->isAlignSet()) {
            throw new \Exception('Sandbox alignment limitation. ' .
                'Clear the sandbox and copy source content, instructions and translated content again to the Beebox. ' .
                'For further information read http://documents.wordbee.com/display/bb/API+-+Sandbox+-+Commit+content'
            );
        } else {
            if (!$this->isConnected()) {
                $this->connect();
            }
            $content = [
                'token'   => $this->token,
                'locale1' => 'sandbox',
                'locale2' => $this->getSourceLanguage(),
            ];
            $json = json_encode($content);

            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL,
                $this->url .
                '/api/files/copy'
            );

            curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($curl, CURLOPT_POSTFIELDS, $json);
            $content = curl_exec($curl);

            $this->checkResponse($curl, $content);
        }
    }

}
