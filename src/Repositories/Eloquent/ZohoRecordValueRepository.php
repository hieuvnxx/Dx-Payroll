<?php

namespace Dx\Payroll\Repositories\Eloquent;

use Dx\Payroll\Models\ZohoRecordValue;
use Prettus\Repository\Eloquent\BaseRepository;
use Dx\Payroll\Repositories\ZohoFormInterface;
use Dx\Payroll\Repositories\ZohoRecordValueInterface;

/**
 * Class EmployeeRepository.
 *
 * @package namespace App\Repositories;
 */
class ZohoRecordValueRepository extends BaseRepository implements ZohoRecordValueInterface
{
    /**
     * Specify Model class name
     *
     * @return string
     */
    public function model()
    {
        return ZohoRecordValue::class;
    }
}
