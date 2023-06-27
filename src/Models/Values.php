<?php

namespace Dx\Payroll\Models;

use Dx\Payroll\DxServiceProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Dx\Payroll\Models\ZohoFormLabel;

class Values extends Model
{
    use HasFactory;

    protected $table = DxServiceProvider::DX_PREFIX_TABLE.'values';

    protected $fillable = [
        'id',
        'form_id',
        'attribute_id',
        'section_id',
        'zoho_id',
        'value'
    ];

    protected $hidden = [

    ];

}
