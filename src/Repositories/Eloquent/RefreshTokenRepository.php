<?php

namespace Dx\Payroll\Repositories\Eloquent;

use Dx\Payroll\Models\RefreshToken;
use Dx\Payroll\Repositories\RefreshTokenInterface;
use Prettus\Repository\Eloquent\BaseRepository;
use Prettus\Repository\Criteria\RequestCriteria;

/**
 * Class EmployeeRepository.
 *
 * @package namespace App\Repositories;
 */
class RefreshTokenRepository extends BaseRepository implements RefreshTokenInterface
{

    /**
     * Specify Model class name
     *
     * @return string
     */
    public function model()
    {
        return RefreshToken::class;
    }

}
