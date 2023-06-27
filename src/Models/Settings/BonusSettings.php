<?php

namespace Dx\Payroll\Models\Settings;

use Dx\Payroll\DxServiceProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BonusSettings extends Model
{
    use HasFactory;

    protected $table = DxServiceProvider::DX_PREFIX_TABLE.'bonus_settings';

    protected $fillable = [
        'zoho_id',
        'row_id',
        'type',
        'date',
        'probation_amount',
        'amount',
    ];

    protected $hidden = [

    ];

}
