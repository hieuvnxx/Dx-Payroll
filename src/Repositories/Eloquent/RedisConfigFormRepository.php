<?php

namespace Dx\Payroll\Repositories\Eloquent;

use Dx\Payroll\Repositories\Contracts\RedisRepository;
use Dx\Payroll\Repositories\RedisConfigFormInterface;
use Dx\Payroll\Repositories\ZohoFormInterface;

/**
 * Class EmployeeRepository.
 *
 * @package namespace App\Repositories;
 */
class RedisConfigFormRepository extends RedisRepository implements RedisConfigFormInterface
{
    protected $repoZohoForm;

    public function __construct(ZohoFormInterface $repoZohoForm)
    {
        $this->repoZohoForm = $repoZohoForm;
    }

    public function getConfig(): mixed
    {
//        $response = [];
//        $arrConfig = $this->makeRedisConnection()->get(env('REDIS_KEY_CONFIG_FORM', 'config_form_zoho'));
//        if (empty($arrConfig)) {
//            $response = $this->repoZohoForm->formatFormConfig();
//            $this->makeRedisConnection()->set(env('REDIS_KEY_CONFIG_FORM', 'config_form_zoho'), json_encode($response));
//        } else {
//            $response = json_decode($arrConfig, true);
//        }
//        $response = ;
        return $this->repoZohoForm->formatFormConfig();
    }

}
