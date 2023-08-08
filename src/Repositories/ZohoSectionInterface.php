<?php

namespace Dx\Payroll\Repositories;

use Prettus\Repository\Contracts\RepositoryInterface;

/**
 * Interface CallLeaveRepository.
 *
 * @package namespace App\Repositories;
 */
interface ZohoSectionInterface extends RepositoryInterface
{
    /**
     * Get config from form
     * @param
     * @return mixed
     */
    public function getSectionID($sectioName);

}