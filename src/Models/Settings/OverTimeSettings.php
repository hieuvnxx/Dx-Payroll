<?php

namespace Dx\Payroll\Models\Settings;

use Dx\Payroll\DxServiceProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OverTimeSettings extends Model
{
    use HasFactory;

    protected $table = DxServiceProvider::DX_PREFIX_TABLE.'over_time_settings';

    protected $fillable = [
        'zoho_id',
        'row_id',
        'type',
        'day_rate',
        'night_rate',
    ];

    protected $hidden = [

    ];

}
