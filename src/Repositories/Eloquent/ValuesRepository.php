<?php

namespace Dx\Payroll\Repositories\Eloquent;

use Dx\Payroll\Models\Values;
use Dx\Payroll\Repositories\ValuesInterface;
use PhpParser\Node\Expr\AssignOp\Mod;
use Prettus\Repository\Eloquent\BaseRepository;
use Prettus\Repository\Criteria\RequestCriteria;

/**
 * Class EmployeeRepository.
 *
 * @package namespace App\Repositories;
 */
class ValuesRepository extends BaseRepository implements ValuesInterface
{
    /**
     * Specify Model class name
     *
     * @return string
     */
    public function model()
    {
        return Values::class;
    }

    public function deleteRecords($formName, $ZohoID)
    {
        return $this->deleteWhere(['form_id' => $formName, 'zoho_id'=> $ZohoID]);
    }

}
