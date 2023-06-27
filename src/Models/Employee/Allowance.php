<?php

namespace Dx\Payroll\Models\Employee;

use Dx\Payroll\DxServiceProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Allowance extends Model
{
    use HasFactory;

    protected $table = DxServiceProvider::DX_PREFIX_TABLE.'allowance';

    protected $fillable = [
        'code',
        'zoho_id',
        'type',
        'from_date',
        'to_date',
        'amount',
        'notes',
        'tax',
        'status',
    ];

    protected $hidden = [

    ];


}
