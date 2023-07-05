<?php

namespace Dx\Payroll\Repositories;

use Prettus\Repository\Contracts\RepositoryInterface;

/**
 * Interface CallLeaveRepository.
 *
 * @package namespace App\Repositories;
 */
interface ValuesInterface extends RepositoryInterface
{
    /**
     * delete records
     * @param
     * @return mixed
     */
    public function deleteRecords($formName, $ZohoID);

    /**
     * Get records
     * @param
     * @return mixed
     */
    public function getRecords($formName, $offset, $limit);

    /**
     * Get one record
     * @param
     * @return mixed
     */
    public function getRecordByID($formName, $ZohoID);

    /**
     * format data of records
     * @param
     * @return mixed
     */
    public function formatRecords($data);

}
