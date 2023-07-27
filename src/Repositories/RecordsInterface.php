<?php

namespace Dx\Payroll\Repositories;

use Prettus\Repository\Contracts\RepositoryInterface;

/**
 * Interface CallLeaveRepository.
 *
 * @package namespace App\Repositories;
 */
interface RecordsInterface extends RepositoryInterface
{
    public function deleteRecords($formName, $ZohoID);

    public function getRecords($formName = '', $offset = '', $limit = '');

    public function getRecordByID($formName = '', $ZohoID = '');

    public function searchRecords($formName = '', $field = '', $value = null);

    public function formatRecords($zohoForm = '', $data = []);

    public function castValue($type, $value);

    public function getSectionID($formName, $sectionName);
}
