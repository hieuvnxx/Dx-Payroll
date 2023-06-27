<?php

namespace Dx\Payroll\Models\Forms;

use Dx\Payroll\DxServiceProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FactorMasterData extends Model
{
    use HasFactory;

    protected $table = DxServiceProvider::DX_PREFIX_TABLE.'factor_master_data';

    protected $fillable = [
        'zoho_id',
        'factor',
        'abbreviation',
        'type',
        'form_name',
        'field_name',
        'note',
    ];

    protected $hidden = [

    ];


}
