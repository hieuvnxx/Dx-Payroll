<?php

namespace Dx\Payroll\Models\Settings;

use Dx\Payroll\DxServiceProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LevelSettings extends Model
{
    use HasFactory;

    protected $table = DxServiceProvider::DX_PREFIX_TABLE.'level_settings';

    protected $fillable = [
        'zoho_id',
        'row_id',
        'level',
        'basic_salary',
        'lunch_allowance',
        'travel_allowance',
        'phone_allowance',
        'other_allowance',
    ];

    protected $hidden = [

    ];

}
