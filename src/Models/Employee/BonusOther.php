<?php

namespace Dx\Payroll\Models\Employee;

use Dx\Payroll\DxServiceProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BonusOther extends Model
{
    use HasFactory;

    protected $table = DxServiceProvider::DX_PREFIX_TABLE.'bonus_other';

    protected $fillable = [
        'code',
        'zoho_id',
        'category',
        'date',
        'amount',
        'description',
        'tax',
        'status',
    ];

    protected $hidden = [

    ];


}
