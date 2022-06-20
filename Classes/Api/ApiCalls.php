<?php

namespace Localizationteam\LocalizerBeebox\Api;

use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * ApiCalls Class used to make calls to the Localizer API
 *
 * @author      Peter Russ<peter.russ@4many.net>, Jo Hasenau<jh@cybercraft.de>
 */
class ApiCalls extends \Localizationteam\Localizer\Api\ApiCalls
{
    protected RequestFactory $requestFactory;

    /**
     * @var array
     */
    protected $align;

    /**
     * @param string $type
     * @param string $url
     * @param string $workflow
     * @param string $projectKey
     * @param string $username
     * @param string $password
     */
    public function __construct(
        string $type,
        string $url = '',
        string $workflow = '',
        string $projectKey = '',
        string $username = '',
        string $password = ''
    ) {
        parent::__construct($type);
        $this->connectorName = 'Beebox Connector';
        $this->connectorVersion = '10.1.0';
        $this->setUrl($url);
        $this->setWorkflow($workflow);
        $this->setProjectKey($projectKey);
        $this->setUsername($username);
        $this->setPassword($password);
        $this->requestFactory = GeneralUtility::makeInstance(RequestFactory::class);
    }

    /**
     * Checks the beebox settings like url, project key, login and password.
     * By default will close connection after check.
     * If there is any existing connection at check time this will be closed prior to check
     *
     * @param bool $closeConnectionAfterCheck
     * @return bool
     * @throws Exception
     */
    public function areSettingsValid(bool $closeConnectionAfterCheck = true): bool
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

    public function isConnected(): bool
    {
        return !empty($this->token);
    }

    public function isDisconnected(): bool
    {
        return !$this->isConnected();
    }

    public function disconnect(): void
    {
        if ($this->isDisconnected()) {
            return;
        }

        $queryData = [
            'token' => $this->token,
        ];

        $url = $this->url . '/api/disconnect?' . http_build_query($queryData);

        $response = $this->requestFactory->request($url);
        if ($response->getStatusCode() === 204) {
            $this->token = null;
        }
    }

    /**
     * Tries to connect to the Beebox using the plugin parameters
     *
     * @return bool true if the connection is successful, false otherwise
     * @throws Exception This Exception contains details of an eventual error
     */
    public function connect() : bool
    {
        if ($this->doesLocalizerExist()) {
            $queryData = [
                'connector' => $this->connectorName,
                'version' => $this->connectorVersion,
                'project' => $this->projectKey,
                'login' => $this->username,
                'pwd' => $this->password,
            ];

            $options = [
                'connect_timeout' => 20,
                'timeout' => 15,
            ];

            $url = $this->url . '/api/connect?' . http_build_query($queryData);
            try {
                $response = $this->requestFactory->request($url, 'GET', $options);
                $this->token = $response->getBody()->getContents();
            } catch (\Exception $exception) {
                $this->checkResponse($exception);
            }

            return $this->isConnected();
        }

        throw new \Exception('No Beebox found at given URL ' . $this->url . '. Either the URL is wrong or Beebox is not active!');
    }

    /**
     * @throws \JsonException
     */
    protected function doesLocalizerExist() : bool
    {
        $doesExist = false;
        $response = $this->requestFactory->request($this->url . '/whois');
        if ($response->getStatusCode() === 200) {
            $answer = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
            if (is_array($answer) && isset($answer['name'])) {
                $doesExist = strtolower($answer['name']) === 'beebox api';
            }
        }

        return $doesExist;
    }

    /**
     * @throws \Exception
     */
    private function checkResponse(\Exception $exception) : void
    {
        $details = [];
        $httpStatusCode = $exception->getCode();
        $details['http_status_code'] = $httpStatusCode;
        $details['message'] = $exception->getMessage();
        $details['file'] = $exception->getFile();
        $details['line'] = $exception->getLine();

        $this->lastError = $exception->getMessage();

        throw new \Exception('Communication error with the Beebox, see the details : (' . var_export($details, true) . ')');
    }

    /**
     * @param string $sourceLanguage
     * @throws Exception
     */
    public function setSourceLanguage(string $sourceLanguage)
    {
        if ($sourceLanguage !== '') {
            $projectLanguages = $this->getProjectLanguages();
            if (isset($projectLanguages[$sourceLanguage])) {
                $this->sourceLanguage = $sourceLanguage;
            } else {
                throw new \Exception('Source language ' . $sourceLanguage . ' not specified for this project ' .
                    $this->projectKey . '. Allowed ' . implode(' ', array_keys($projectLanguages)));
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
     * @throws Exception This Exception contains details of an eventual error
     */
    public function getProjectLanguages(): array
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
     * @throws Exception
     */
    public function getProjectInformation(bool $asJson = false)
    {
        if ($this->projectInformation === null) {
            if ($this->isDisconnected()) {
                $this->connect();
            }

            $queryData = [
                'token' => $this->token,
            ];

            $url = $this->url . '/api/details?' . http_build_query($queryData);

            try {
                $response = $this->requestFactory->request($url);
                $this->projectInformation = $response->getBody()->getContents();
            } catch (\Exception $exception) {
                $this->checkResponse($exception);
            }
        }

        return $asJson === true ? $this->projectInformation : json_decode($this->projectInformation, true, 512,
            JSON_THROW_ON_ERROR);
    }

    /**
     * Instructs the Beebox to look for translated files in the Beebox "out" directory.
     * If translated files are found, these will be aligned with the source file for the purpose of pre-translation.
     *
     * @param array $align
     * @throws Exception
     */
    public function setAlign(array $align) : void
    {
        $this->align = $this->validateTargetLocales($align);
    }

    public function isAlignSet() : bool
    {
        return !empty($this->align);
    }

    /**
     * @param array $locales
     * @return array validated locales should be same as input otherwise an exception will be thrown
     * @throws Exception
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
                throw new Exception($locale . ' not defined for this project ' . $this->projectKey
                    . '. Available locales ' . implode(' ', array_keys($projectLanguages[$sourceLanguage])));
            }
        }

        return $validateLocales;
    }

    /**
     * Determine the source language for a Beebox project.
     * Will throw an exception if there are more so the source ha to be set
     *
     * @return string the source language
     * @throws Exception
     */
    public function getSourceLanguage(): string
    {
        if ($this->sourceLanguage === '') {
            $projectLanguages = $this->getProjectLanguages();
            $sourceLanguages = array_keys($projectLanguages);
            if (count($sourceLanguages) === 1) {
                $this->sourceLanguage = $sourceLanguages[0];
            } else {
                throw new Exception('For this project ' . $this->projectKey
                    . ' is more than one source language available. Please specify ' . implode(' ', $sourceLanguages));
            }
        }

        return $this->sourceLanguage;
    }

    /**
     * Deletes the specified file in the Beebox
     *
     * @param string $filename Name of the file you wish to delete
     * @param string $source source language of the file
     * @throws Exception This Exception contains details of an eventual error
     */
    public function deleteFile(string $filename, string $source) : void
    {
        if ($this->isDisconnected()) {
            $this->connect();
        }

        $queryData = [
            'token' => $this->token,
            'locale' => $source,
            'filename' => $filename,
            'folder'
        ];

        $url = $this->url . '/api/files/file?' . http_build_query($queryData);

        try {
            $response = $this->requestFactory->request($url, 'DELETE');
            $content = $response->getBody()->getContents();
        } catch (\Exception $exception) {
            $this->checkResponse($exception);
        }
    }

    /**
     * Retrieves work progress of the Beebox for the specified files, if no file specified it will retrieve every file
     *
     * @param array $files An array containing a list of file-names. Can be empty if you do no want to filter (empty by default)
     * @param int $skip Optional number, default is 0. Used for pagination. The files to skip.
     * @param int $count Optional number, default is 100. Used for pagination and indicates the total number of files
     *                   to return from this call. Make sure to specify a limit corresponding to your page
     *                   size (e.g. 100).
     *
     * @return array corresponding to the json returned by the Beebox API
     * @throws Exception This Exception contains details of an eventual error
     */
    public function getWorkProgress(array $files = [], int $skip = 0, int $count = 100) : array
    {
        if ($this->isDisconnected()) {
            $this->connect();
        }

        $query = [
            'token' => $this->token,
            'filter' => [],
        ];

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

        if ($skip > 0) {
            $query['skip'] = $skip;
        }

        if ($count > 0) {
            $query['count'] = $count;
        }

        $url = $this->url . '/api/workprogress/translatedfiles';

        $options = [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'json' => $query,
        ];

        try {
            $response = $this->requestFactory->request($url, 'POST', $options);
        } catch (\Exception $exception) {
            $this->checkResponse($exception);
        }

        return json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Downloads the specified file
     *
     * @param array $file The array with information to the file to download
     * @return string The content of the file
     * @throws \Exception This Exception contains details of an eventual error
     */
    public function getFile(array $file) : string
    {
        if ($this->isDisconnected()) {
            $this->connect();
        }

        $queryData = [
            'token' => $this->token,
            'locale' => $file['locale'],
            'filename' => $file['remoteFilename'],
            // TODO: Make the usage of folders explicit.
            //'folder' => $folder,
        ];

        $url = $this->url . '/api/files/file?' . http_build_query($queryData);

        try {
            $response = $this->requestFactory->request($url);
        } catch (\Exception $exception) {
            $this->checkResponse($exception);
        }

        return $response->getBody()->getContents();
    }

    /**
     * Tells the Beebox to scan its files
     *
     * @TODO This method seems not in use and I can't find the documentation for the endpoint at
     * @TODO https://wordbee.atlassian.net/wiki/spaces/bb/pages/365072/Web+API
     * @throws \Exception This Exception contains details of an eventual error
     */
    public function scanFiles() : void
    {
        if ($this->isDisconnected()) {
            $this->connect();
        }

        $queryData = [
            'token' => $this->token,
        ];

        $this->checkResponse($curl, $content);
        $url = $this->url . '/api/files/operations/scan?' . http_build_query($queryData);

        try {
            $this->requestFactory->request($url, 'PUT'); // TODO: Why PUT?
        } catch (\Exception $exception) {
            $this->checkResponse($exception);
        }
    }

    /**
     * Asks to the Beebox if a scan is required
     *
     * @return bool True if a scan is required, false otherwise
     * @throws Exception This Exception contains details of an eventual error
     */
    public function scanRequired(): bool
    {
        if ($this->isDisconnected()) {
            $this->connect();
        }

        $queryData = [
            'token' => $this->token,
        ];

        $url = $this->url . '/api/files/status?' . http_build_query($queryData);

        try {
            $response = $this->requestFactory->request($url);
            $content = json_decode($response->getBody()->getContents(), true);
            if (isset($content['scanRequired'])) {
                return (boolean)$content['scanRequired'];
            }
        } catch (\Exception $exception) {
            $this->checkResponse($exception);
        }

        throw new \Exception('unexpected result from: scan required');
    }

    /**
     * @param string $fileName Name the file will have in the Localizer
     * @param string $source Source language of the file
     * @throws Exception
     */
    public function sendInstructions($fileName, $source) : void
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
     * @param string $fileContent The content of the file you wish to send
     * @param string $fileName Name the file will have in the Beebox
     * @param string $source Source language of the file
     * @param bool $attachInstruction
     *
     * @throws \Exception This Exception contains details of an eventual error
     */
    public function sendFile(string $fileContent, string $fileName, string $source, bool $attachInstruction = true)
    {
        if ($this->isDisconnected()) {
            $this->connect();
        }

        if ($attachInstruction === true) {
            $this->sendInstructions($fileName, $source);
        }

        $options = [
            'headers' => [
                'Content-Type' => 'Stream'
            ],
            'body' =>  $fileContent,
        ];

        $queryData = [
            'token' => $this->token,
            'locale' => $source,
            'filename' => $fileName,
        ];

        $url = $this->url . '/api/files/file?' . http_build_query($queryData);

        try {
            $this->requestFactory->request($url, 'PUT', $options);
        } catch (\Exception $exception) {
            $this->checkResponse($exception);
        }
    }
}
