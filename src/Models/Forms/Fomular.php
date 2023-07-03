<?php

namespace Dx\Payroll\Models\Forms;

use Dx\Payroll\DxServiceProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Fomular extends Model
{
    use HasFactory;

    protected $table = DxServiceProvider::DX_PREFIX_TABLE.'fomular';

    protected $fillable = [
        'zoho_id',
        'factor_1',
        'factor_2',
        'factor_3',
        'factor_4',
        'factor_5',
        'factor_6',
        'factor_7',
        'factor_8',
        'factor_9',
        'factor_10',
        'factor_11',
        'factor_12',
        'factor_13',
        'factor_14',
        'field',
        'fomular',
        'from_date',
        'to_date',
        'contract_type',
        'type',
        'department'
    ];

    protected $hidden = [

    ];


}
