<?php

namespace Dx\Payroll\Models\Forms;

use Dx\Payroll\DxServiceProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OverTime extends Model
{
    use HasFactory;

    protected $table = DxServiceProvider::DX_PREFIX_TABLE.'over_time';

    protected $fillable = [
        'zoho_id',
        'request_id',
        'employee_id',
        'employee_name',
        'project_task',
        'description',
        'reason',
        'allowance',
        'date',
        'type',
        'from',
        'to',
        'hour',
        'status'
    ];

    protected $hidden = [

    ];


}
