<?php

namespace Dx\Payroll\Repositories;

use Prettus\Repository\Contracts\RepositoryInterface;

/**
 * Interface CallLeaveRepository.
 *
 * @package namespace App\Repositories;
 */
interface ZohoFormInterface extends RepositoryInterface
{
    /**
     * Get field of form
     * @param
     * @return mixed
     */
    public function getFieldOfForm($formName);
}
