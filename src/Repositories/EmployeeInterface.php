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
     * Get all/one employee
     * @param $id
     * @return mixed
     */
    public function getEmployee($id);

    /**
     * Get all/one All Info of employee
     * @param $id
     * @return mixed
     */
    public function getEmployeeByCode($code);

}
