<?php

namespace Dx\Payroll\Repositories;

/**
 * Interface CallLeaveRepository.
 *
 * @package namespace App\Repositories;
 */
interface RedisConfigFormInterface
{
    /**
     * format config
     * @param
     * @return mixed
     */
    public function getConfigByToken();
}
