<?php

namespace Dx\Payroll\Repositories;

use Prettus\Repository\Contracts\RepositoryInterface;

/**
 * Interface CallLeaveRepository.
 *
 * @package namespace App\Repositories;
 */
interface ZohoRecordInterface extends RepositoryInterface
{
    public function deleteRecords($formName, $ZohoID);

    public function getRecords($formName, $offset = 0, $limit = 200, $params = []);

    public function getRecordByZohoID($formName, $ZohoID);
}
