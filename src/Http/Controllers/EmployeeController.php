<?php

namespace Dx\Payroll\Http\Controllers;

use Dx\Payroll\Repositories\EmployeeInterface;
use Dx\Payroll\ZohoIntegrations\ZohoIntegration;
use Dx\Payroll\ZohoIntegrations\PeopleZohoIntegration;


class EmployeeController
{

    protected $repoEmployee;

    public function __construct(EmployeeInterface $repoEmployee)
    {
        $this->repoEmployee = $repoEmployee;
    }
    /**
     * success response method.
     *
     * @return \Illuminate\Http\Response
     */
    public function getEmployee($data = '')
    {
        $id = $data['code'] ?? '';
        return $this->repoEmployee->getAllEmployee($id)->sortBy('id')->sortBy('offerSalary.from_date');
    }

    public function getEmployeeByCode($code = '')
    {
        return $this->repoEmployee->getEmployeeByCode($code)->sortBy('id')->sortBy('offerSalary.from_date');

    }

}
