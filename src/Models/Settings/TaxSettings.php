<?php

namespace Dx\Payroll\Models\Settings;

use Dx\Payroll\DxServiceProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxSettings extends Model
{
    use HasFactory;

    protected $table = DxServiceProvider::DX_PREFIX_TABLE.'tax_settings';

    protected $fillable = [
        'zoho_id',
        'row_id',
        'level',
        'from',
        'to',
        'rate',
    ];

    protected $hidden = [

    ];

}
