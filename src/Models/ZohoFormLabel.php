<?php

namespace Dx\Payroll\Models;

use Dx\Payroll\DxServiceProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZohoFormLabel extends Model
{
    use HasFactory;

    protected $table = DxServiceProvider::DX_PREFIX_TABLE.'zoho_form_label';

    protected $fillable = [
        'form_name',
        'key',
        'slug',
        'label',
        'form_slug',
        'form_id',
    ];

    protected $hidden = [

    ];

}
