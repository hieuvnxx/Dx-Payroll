<?php

namespace Dx\Payroll\Models;

use Dx\Payroll\DxServiceProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Dx\Payroll\Models\ZohoFormLabel;

class Sections extends Model
{
    use HasFactory;

    protected $table = DxServiceProvider::DX_PREFIX_TABLE.'sections';

    protected $fillable = [
        'id',
        'form_id',
        'sections_name',
        'sections_label',
        'sections_id',
    ];

    protected $hidden = [

    ];



}
