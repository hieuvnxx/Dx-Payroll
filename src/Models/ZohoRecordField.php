<?php

namespace Dx\Payroll\Models;

use Dx\Payroll\DxServiceProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Dx\Payroll\Models\ZohoFormLabel;

class ZohoRecordField extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = DxServiceProvider::DX_PREFIX_TABLE.'zoho_record_fields';

    protected $fillable = [
        'form_id',
        'section_id',
        'display_name',
        'label_name',
        'comp_type',
        'autofillvalue',
        'is_mandatory',
        'options',
        'decimal_length',
        'max_length',
    ];

    protected $casts = [
        'ismandatory' => 'boolean',
    ];
}
