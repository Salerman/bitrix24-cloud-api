<?php

namespace Salerman\Bitrix24\Rest;

class Config
{
    public const CONNECTION_TYPE_UI_APP = 1;
    public const CONNECTION_TYPE_API_APP = 2;
    public const CONNECTION_TYPE_WEBHOOK = 3;

    protected $config = [
        'connection_type' => 1,
        'ignore_ssl' => true,
        'current_encoding' => 'utf-8',
    ];

    public function __construct($params)
    {
        $this->config = array_merge($this->config, $params);
    }

    public function __set($name, $value)
    {
        $this->config[$name] = $value;
    }

    public function __get($name)
    {
        return $this->config[$name] ?? null;
    }

    public function toArray()
    {
        return $this->config;
    }
}