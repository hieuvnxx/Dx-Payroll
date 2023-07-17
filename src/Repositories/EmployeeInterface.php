<?php

namespace Dx\Payroll\Repositories;

use Prettus\Repository\Contracts\RepositoryInterface;

/**
 * Interface CallLeaveRepository.
 *
 * @package namespace App\Repositories;
 */
interface EmployeeInterface extends RepositoryInterface
{
    /**
     * Get ALL employee
     * @param $id
     * @return mixed
     */
    public function getEmployee();

    /**
     * Get ONE Info of employee
     * @param $id
     * @return mixed
     */
    public function getEmployeeByCode($code);

}
