<?php

namespace Salerman\Bitrix24\Rest;

use Salerman\Bitrix24\Client\Response;
use Salerman\Bitrix24\Client\Result;
use Salerman\Bitrix24\Settings\Auth;

/**
 *  @version 1.36
 *  define:
 *      C_REST_WEB_HOOK_URL = 'https://rest-api.bitrix24.com/rest/1/doutwqkjxgc3mgc1/'  //url on creat Webhook
 *      or
 *      C_REST_CLIENT_ID = 'local.5c8bb1b0891cf2.87252039' //Application ID
 *      C_REST_CLIENT_SECRET = 'SakeVG5mbRdcQet45UUrt6q72AMTo7fkwXSO7Y5LYFYNCRsA6f'//Application key
 *
 *		C_REST_CURRENT_ENCODING = 'windows-1251'//set current encoding site if encoding unequal UTF-8 to use iconv()
 *      C_REST_BLOCK_LOG = true //turn off default logs
 *      C_REST_LOGS_DIR = __DIR__ .'/logs/' //directory path to save the log
 *      C_REST_LOG_TYPE_DUMP = true //logs save var_export for viewing convenience
 *      C_REST_IGNORE_SSL = true //turn off validate ssl by curl
 */

class Crm
{
    const VERSION = '1.36';
    const BATCH_COUNT = 50;
    const TYPE_TRANSPORT = 'json';

    protected $batch = [];

    protected $settings = null;
    protected $config = null;
    protected $request = null;
    protected $logger = null;

    protected static $dataExt = [];

    public function __construct(Auth $authSettings, Config $config = null, $request = null, $logger = null)
    {
        $this->settings = $authSettings;
        if ($config === null) {
            $config = new Config([]);
        }
        $this->config = $config;
        $this->logger = $logger;
        if ($request === null) {
            $request = $_REQUEST;
        }
        $this->request = $request;
    }

    /**
     * call where install application even url
     * only for rest application, not webhook
     */

    public function installApp()
    {
        $result = [
            'rest_only' => true,
            'install' => false
        ];


        if($this->request[ 'event' ] == 'ONAPPINSTALL' && !empty($this->request[ 'auth' ]))
        {
            $result['install'] = $this->setAppSettings($this->request[ 'auth' ], true);
        }
        elseif($this->request['PLACEMENT'] == 'DEFAULT')
        {
            $result['rest_only'] = false;
            $result['install'] = $this->setAppSettings(
                [
                    'access_token' => htmlspecialchars($this->request['AUTH_ID']),
                    'expires_in' => htmlspecialchars($this->request['AUTH_EXPIRES']),
                    'application_token' => htmlspecialchars($this->request['APP_SID']),
                    'refresh_token' => htmlspecialchars($this->request['REFRESH_ID']),
                    'domain' => htmlspecialchars($this->request['DOMAIN']),
                    'client_endpoint' => 'https://' . htmlspecialchars($this->request['DOMAIN']) . '/rest/',
                ],
                true
            );
        }

        $this->log(
            'info',
            'installApp',
            [
                'request' => $this->request,
                'result' => $result
            ],
        );
        return $result;
    }

    /**
     * @var $arParams array
     * $arParams = [
     *      'method'    => 'some rest method',
     *      'params'    => []//array params of method
     * ];
     * @return mixed array|string|boolean curl-return or error
     *
     */
    protected function callCurl($arParams)
    {
        if (!function_exists('curl_init')) {
            return [
                'error'             => 'error_php_lib_curl',
                'error_information' => 'need install curl lib'
            ];
        }

        $arSettings = $this->getAppSettings();
        if ($arSettings !== false) {
            if (isset($arParams[ 'this_auth' ]) && $arParams[ 'this_auth' ] == 'Y') {
                $url = 'https://oauth.bitrix.info/oauth/token/';
            } else {
                $url = $arSettings[ "client_endpoint" ] . $arParams[ 'method' ] . '.' . static::TYPE_TRANSPORT;
                if (empty($arSettings[ 'is_web_hook' ]) || $arSettings[ 'is_web_hook' ] != 'Y') {
                    $arParams[ 'params' ][ 'auth' ] = $arSettings[ 'access_token' ];
                }
            }

            $sPostFields = http_build_query($arParams[ 'params' ]);

            try {
                $obCurl = curl_init();
                curl_setopt($obCurl, CURLOPT_URL, $url);
                curl_setopt($obCurl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($obCurl, CURLOPT_POSTREDIR, 10);
                curl_setopt($obCurl, CURLOPT_USERAGENT, 'Bitrix24 CRest PHP '. static::VERSION);

                if($sPostFields) {
                    curl_setopt($obCurl, CURLOPT_POST, true);
                    curl_setopt($obCurl, CURLOPT_POSTFIELDS, $sPostFields);
                }

                curl_setopt(
                    $obCurl, CURLOPT_FOLLOWLOCATION, (isset($arParams[ 'followlocation' ]))
                    ? $arParams[ 'followlocation' ] : 1
                );

                if ($this->config->ignore_ssl === true) {
                    curl_setopt($obCurl, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($obCurl, CURLOPT_SSL_VERIFYHOST, false);
                }

                $out = curl_exec($obCurl);
                $info = curl_getinfo($obCurl);

                if(curl_errno($obCurl)) {
                    $info[ 'curl_error' ] = curl_error($obCurl);
                }

                if(static::TYPE_TRANSPORT == 'xml' && (!isset($arParams[ 'this_auth' ]) || $arParams[ 'this_auth' ] != 'Y')) {
                    $result = $out;
                } else {
                    $result = $this->expandData($out);
                }
                curl_close($obCurl);

                if(!empty($result[ 'error' ])) {
                    if($result[ 'error' ] == 'expired_token' && empty($arParams[ 'this_auth' ])) {
                        $result = $this->getNewAuth($arParams);
                    } else {
                        $arErrorInform = [
                            'expired_token'          => 'expired token, cant get new auth? Check access oauth server.',
                            'invalid_token'          => 'invalid token, need reinstall application',
                            'invalid_grant'          => 'invalid grant, check out define C_REST_CLIENT_SECRET or C_REST_CLIENT_ID',
                            'invalid_client'         => 'invalid client, check out define C_REST_CLIENT_SECRET or C_REST_CLIENT_ID',
                            'QUERY_LIMIT_EXCEEDED'   => 'Too many requests, maximum 2 query by second',
                            'ERROR_METHOD_NOT_FOUND' => 'Method not found! You can see the permissions of the application: CRest::call(\'scope\')',
                            'NO_AUTH_FOUND'          => 'Some setup error b24, check in table "b_module_to_module" event "OnRestCheckAuth"',
                            'INTERNAL_SERVER_ERROR'  => 'Server down, try later'
                        ];

                        if(!empty($arErrorInform[ $result[ 'error' ] ])) {
                            $result[ 'error_information' ] = $arErrorInform[ $result[ 'error' ] ];
                        }
                    }
                }

                if(!empty($info[ 'curl_error' ])) {
                    $result[ 'error' ] = 'curl_error';
                    $result[ 'error_information' ] = $info[ 'curl_error' ];
                }

                $this->log(
                    'debug',
                    'callCurl',
                    [
                        'url'    => $url,
                        'info'   => $info,
                        'params' => $arParams,
                        'result' => $result
                    ]
                );

                return $result;
            } catch(\Exception $e) {
                $this->log(
                    'error',
                    'exceptionCurl',
                    [
                        'message' => $e->getMessage(),
                        'code' => $e->getCode(),
                        'trace' => $e->getTrace(),
                        'params' => $arParams
                    ]
                );

                return [
                    'error' => 'exception',
                    'error_exception_code' => $e->getCode(),
                    'error_information' => $e->getMessage(),
                ];
            }
        } else {
            $this->log(
                'debug',
                'emptySetting',
                [
                    'params' => $arParams
                ]
            );
        }

        return [
            'error'             => 'no_install_app',
            'error_information' => 'error install app, pls install local application '
        ];
    }

    /**
     * Generate a request for callCurl()
     *
     * @var $method string
     * @var $params array method params
     * @return mixed array|string|boolean curl-return or error
     */

    public function call($method, $params = []): Result
    {
        $arPost = [
            'method' => $method,
            'params' => $params
        ];

        if ($this->config->current_encoding != 'utf-8') {
            $arPost[ 'params' ] = $this->changeEncoding($arPost[ 'params' ]);
        }

        $result = $this->callCurl($arPost);
        return new Result($result);
    }

    /**
     * @example $arData:
     * $arData = [
     *      'find_contact' => [
     *          'method' => 'crm.duplicate.findbycomm',
     *          'params' => [ "entity_type" => "CONTACT",  "type" => "EMAIL", "values" => array("info@bitrix24.com") ]
     *      ],
     *      'get_contact' => [
     *          'method' => 'crm.contact.get',
     *          'params' => [ "id" => '$result[find_contact][CONTACT][0]' ]
     *      ],
     *      'get_company' => [
     *          'method' => 'crm.company.get',
     *          'params' => [ "id" => '$result[get_contact][COMPANY_ID]', "select" => ["*"],]
     *      ]
     * ];
     *
     * @var $arData array
     * @var $halt   integer 0 or 1 stop batch on error
     * @return array
     *
     */

    public function callBatch($arData, $halt = 0)
    {
        $arResult = [];

        if (is_array($arData)) {
            if ($this->config->current_encoding != 'utf-8') {
                $arData = $this->changeEncoding($arData);
            }
            $arDataRest = [];
            $i = 0;
            foreach($arData as $key => $data) {
                if(!empty($data[ 'method' ])) {
                    $i++;
                    if(static::BATCH_COUNT >= $i) {
                        $arDataRest[ 'cmd' ][ $key ] = $data[ 'method' ];
                        if (!empty($data[ 'params' ])) {
                            $arDataRest[ 'cmd' ][ $key ] .= '?' . http_build_query($data[ 'params' ]);
                        }
                    }
                }
            }
            if (!empty($arDataRest)) {
                $arDataRest[ 'halt' ] = $halt;
                $arPost = [
                    'method' => 'batch',
                    'params' => $arDataRest
                ];
                $arResult = $this->callCurl($arPost);
            }
        }
        return $arResult;
    }

    /**
     * Getting a new authorization and sending a request for the 2nd time
     *
     * @var $arParams array request when authorization error returned
     * @return array query result from $arParams
     *
     */

    private function getNewAuth($arParams)
    {
        $result = [];
        $arSettings = $this->getAppSettings();
        if ($arSettings !== false) {
            $arParamsAuth = [
                'this_auth' => 'Y',
                'params'    =>
                    [
                        'client_id'     => $arSettings[ 'client_id' ],
                        'grant_type'    => 'refresh_token',
                        'client_secret' => $arSettings[ 'client_secret' ],
                        'refresh_token' => $arSettings[ "refresh_token" ],
                    ]
            ];
            $newData = $this->callCurl($arParamsAuth);
            /*
            if (isset($newData[ 'client_id' ])) {
                unset($newData[ 'client_id' ]);
            }
            if (isset($newData[ 'client_secret' ])) {
                unset($newData[ 'client_secret' ]);
            }
            */
            if (isset($newData[ 'error' ])) {
                unset($newData[ 'error' ]);
            }
            if ($this->setAppSettings($newData)) {
                $arParams[ 'this_auth' ] = 'N';
                $result = $this->callCurl($arParams);
            }
        }
        return $result;
    }

    /**
     * @var $arSettings array settings application
     * @var $isInstall  boolean true if install app by installApp()
     * @return boolean
     */

    private function setAppSettings($arSettings, $isInstall = false)
    {
        $return = false;
        if (is_array($arSettings)) {
            $oldData = $this->getAppSettings();
            if ($isInstall != true && !empty($oldData) && is_array($oldData)) {
                $arSettings = array_merge($oldData, $arSettings);
            }
            $return = $this->settings->setSettingData($arSettings);
        }
        return $return;
    }

    /**
     * @return mixed setting application for query
     */

    private function getAppSettings()
    {
        if ($this->config->connection_type == Config::CONNECTION_TYPE_WEBHOOK) {
            $arData = [
                'client_endpoint' => $this->settings->client_endpoint,
                'is_web_hook'     => 'Y'
            ];
            $isCurrData = true;
        } else {
            $arData = $this->settings->getSettingData();
            $isCurrData = false;
            if(
                !empty($arData[ 'access_token' ]) &&
                !empty($arData[ 'domain' ]) &&
                !empty($arData[ 'refresh_token' ]) &&
                !empty($arData[ 'application_token' ]) &&
                !empty($arData[ 'client_endpoint' ])
            )
            {
                $isCurrData = true;
            }
        }

        return ($isCurrData) ? $arData : false;
    }

    /**
     * @var $data mixed
     * @var $encoding boolean true - encoding to utf8, false - decoding
     *
     * @return string json_encode with encoding
     */
    protected function changeEncoding($data, $encoding = true)
    {
        if(is_array($data)) {
            $result = [];
            foreach ($data as $k => $item) {
                $k = $this->changeEncoding($k, $encoding);
                $result[$k] = $this->changeEncoding($item, $encoding);
            }
        } else {
            if ($encoding) {
                $result = \iconv($this->config->current_encoding, "UTF-8//TRANSLIT", $data);
            } else {
                $result = \iconv( "UTF-8",$this->config->current_encoding, $data);
            }
        }

        return $result;
    }

    /**
     * @var $data mixed
     * @var $debug boolean
     *
     * @return string json_encode with encoding
     */
    protected function wrapData($data, $debug = false)
    {
        if ($this->config->current_encoding !== 'utf-8') {
            $data = static::changeEncoding($data, true);
        }

        $return = json_encode($data, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);

        if($debug) {
            $e = json_last_error();
            if ($e != JSON_ERROR_NONE) {
                if ($e == JSON_ERROR_UTF8) {
                    return 'Failed encoding! Recommended \'UTF - 8\' or set define C_REST_CURRENT_ENCODING = current site encoding for function iconv()';
                }
            }
        }

        return $return;
    }

    /**
     * @var $data mixed
     * @var $debag boolean
     *
     * @return string json_decode with encoding
     */
    protected function expandData($data)
    {
        $return = json_decode($data, true);
        if ($this->config->current_encoding !== 'utf-8') {
            $return = static::changeEncoding($return, false);
        }
        return $return;
    }

    protected function log($level, $message, $context = [])
    {
        if ($this->logger !== null) {
            $this->logger->log($level, $message, $context);
        }
    }
}