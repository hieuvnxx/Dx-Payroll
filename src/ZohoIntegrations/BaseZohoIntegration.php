<?php

namespace Dx\Payroll\ZohoIntegrations;

use Dx\Payroll\Models\RefreshToken;
use Dx\Payroll\Repositories\RefreshTokenInterface;
use Dx\Payroll\Repositories\ZohoFormInterface;
use GuzzleHttp\Exception\GuzzleException;
use Dx\Payroll\Http\Controllers\BaseController;

class BaseZohoIntegration
{
    private static $peopleUrl;
    private static $singletonObject;

    public static function getInstance()
    {
        if (self::$singletonObject == null) {
            self::$singletonObject = new ZohoToken();
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
