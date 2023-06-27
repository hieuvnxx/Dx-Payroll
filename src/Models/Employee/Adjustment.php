<?php

namespace Dx\Payroll\Models\Employee;

use Dx\Payroll\DxServiceProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Adjustment extends Model
{
    use HasFactory;

    protected $table = DxServiceProvider::DX_PREFIX_TABLE.'adjustment';

    protected $fillable = [
        'code',
        'zoho_id',
        'type',
        'category',
        'notes',
        'date',
        'amount',
        'status',
    ];

    protected $hidden = [

    ];


}
