<?php

namespace Salerman\Bitrix24\Client;

use Salerman\Bitrix24\Rest\Config;
use Salerman\Bitrix24\Rest\Crm;
use Salerman\Bitrix24\Settings\Auth;

class Bitrix24ApiClient
{
    public const API_VERSION = 4;
    public const BATCH_COUNT    = 50;
    public const TYPE_TRANSPORT = 'json';

    protected $settings = [];

    protected $restApiClient = null;

    public function __construct(Auth $authSettings, Config $config = null, $request = null, $logger = null)
    {
        $this->restApiClient = new Crm($authSettings, $config, $request, $logger);
    }

    /**
     * Запрос в Битрикс24
     *
     * @param $method
     * @param $params
     * @param $raw
     * @return array|mixed|string|string[]
     */
    public function Query($method, $params = []): Result
    {
        $result = $this->restApiClient->call($method, $params);
        return $result;
    }
}