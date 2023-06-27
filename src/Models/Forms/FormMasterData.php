<?php

namespace Dx\Payroll\Models\Forms;

use Dx\Payroll\DxServiceProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormMasterData extends Model
{
    use HasFactory;

    protected $table = DxServiceProvider::DX_PREFIX_TABLE.'form_master_data';

    protected $fillable = [
        'zoho_id',
        'field_name',
        'form_name'
    ];

    protected $hidden = [

    ];


}
