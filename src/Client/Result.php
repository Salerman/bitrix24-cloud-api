<?php

namespace Salerman\Bitrix24\Client;

class Result
{
    protected $result = null;
    protected $time = [];
    protected $errors = [];
    protected $raw = [];
    protected $isError = false;

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

        if (isset($raw['error'])) {
            $this->isError = true;
            $this->errors[] = [
                'error' => $raw['error'],
                'error_description' => $raw['error_description']
            ];
        }
    }

    public function getRaw()
    {
        return $this->raw;
    }

    public function toArray()
    {
        return $this->result;
    }

    public function getResult()
    {
        return $this->result;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function getTime()
    {
        return $this->time;
    }

    public function isError()
    {
        return $this->isError;
    }
}