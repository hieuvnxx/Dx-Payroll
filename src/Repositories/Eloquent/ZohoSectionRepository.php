<?php

namespace Dx\Payroll\Repositories\Eloquent;

use Dx\Payroll\Models\ZohoSection;
use Dx\Payroll\Repositories\ZohoSectionInterface;
use Prettus\Repository\Eloquent\BaseRepository;

/**
 * Class EmployeeRepository.
 *
 * @package namespace App\Repositories;
 */
class ZohoSectionRepository extends BaseRepository implements ZohoSectionInterface
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
