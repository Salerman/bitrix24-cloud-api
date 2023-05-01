<?php

namespace Salerman\Bitrix24\Settings;

interface AuthInterface
{
    public function setSettingData($arSettings);
    public function getSettingData();
}