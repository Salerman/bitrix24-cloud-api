<?php

namespace Salerman\Bitrix24\Client;

class Result
{
    protected $result = [];
    protected $time = [];
    protected $errors = [];
    protected $raw = [];

    public function __construct ($raw)
    {
        $this->raw = $raw;
        if (isset($raw['result'])) {
            $this->result = $raw['result'];
        }
        if (isset($raw['time'])) {
            $this->time = $raw['time'];
        }
        if (isset($raw['errors'])) {
            $this->errors = $raw['errors'];
        }
    }

    public function toArray()
    {
        return $this->result;
    }
}