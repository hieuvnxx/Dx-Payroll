<?php

namespace Dx\Payroll\Integrations;

use Dx\Payroll\Models\RefreshToken;
use Dx\Payroll\Repositories\RefreshTokenInterface;
use Dx\Payroll\Repositories\ZohoFormInterface;

class ZohoIntegration
{
    private $peopleUrl;
    private static $singletonObject;

    public static function getInstance()
    {
        if (self::$singletonObject == null) {
            self::$singletonObject = new ZohoIntegration();
        }
        return self::$singletonObject;
    }

    public function setPeopleUrl($peopleUrl)
    {
        return $this->peopleUrl = $peopleUrl;
    }

    public function getPeopleUrl()
    {
        return $this->peopleUrl;
    }
}
