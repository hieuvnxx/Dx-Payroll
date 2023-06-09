<?php

namespace Dx\Payroll\Repositories\Eloquent;

use Dx\Payroll\Models\Employee;
use Dx\Payroll\Repositories\EmployeeInterface;
use Prettus\Repository\Eloquent\BaseRepository;
use Prettus\Repository\Criteria\RequestCriteria;

/**
 * Class EmployeeRepository.
 *
 * @package namespace App\Repositories;
 */
class EmployeeRepository extends BaseRepository implements EmployeeInterface
{

    /**
     * Specify Model class name
     *
     * @return string
     */
    public function model()
    {
        return Employee::class;
    }

    public function getEmployee($code = '')
    {
        if(empty($code)){
            return $this->model->with('offerSalary')->get();
        }else{
            return $this->model->where('code', $code)->with('offerSalary')->get();
        }
    }

    public function getEmployeeByCode($code = '')
    {
        return $this->model->where('code', '=', $code)->with('offerSalary')->first();
    }

}
