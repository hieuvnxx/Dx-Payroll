<?php

namespace Dx\Payroll\Repositories\Eloquent;

use Dx\Payroll\Models\PayrollConfig;
use Dx\Payroll\Repositories\PayrollSettingsInterface;
use Prettus\Repository\Eloquent\BaseRepository;
use Prettus\Repository\Criteria\RequestCriteria;

/**
 * Class EmployeeRepository.
 *
 * @package namespace App\Repositories;
 */
class PayrollSettingsRepository extends BaseRepository implements PayrollSettingsInterface
{

    /**
     * Specify Model class name
     *
     * @return string
     */
    public function model()
    {
        return PayrollConfig::class;
    }

}
