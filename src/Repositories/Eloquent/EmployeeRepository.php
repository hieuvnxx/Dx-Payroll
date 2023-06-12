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

    public function getAllEmployee($id = '')
    {
        if(empty($id)){
            return Employee::with('offerSalary')->get();
        }else{
            return Employee::where('code', '=', $id)->with('offerSalary')->get();
        }
    }

    public function getEmployeeByCode($code = '')
    {
        if(!empty($code)){
            return Employee::where('code', '=', $code)->with('offerSalary')->get();
        }
    }

}
