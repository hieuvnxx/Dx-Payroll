<?php

namespace Dx\Payroll\Repositories\Eloquent;

use Dx\Payroll\Models\ZohoSection;
use Dx\Payroll\Repositories\RefreshTokenInterface;
use Prettus\Repository\Eloquent\BaseRepository;
use Prettus\Repository\Criteria\RequestCriteria;

/**
 * Class EmployeeRepository.
 *
 * @package namespace App\Repositories;
 */
class SectionsRepository extends BaseRepository implements RefreshTokenInterface
{

    /**
     * Specify Model class name
     *
     * @return string
     */
    public function model()
    {
        return ZohoSection::class;
    }

}
