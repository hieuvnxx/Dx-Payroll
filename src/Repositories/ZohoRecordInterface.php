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

    public function getRecords($formName, $offset = 0, $limit = 200);

    public function getRecordByZohoID($formName, $ZohoID);

    public function searchRecords($formName = '', $field = '', $value = null);

    public function formatRecords($data);

    public function castValue($type, $value);
}
