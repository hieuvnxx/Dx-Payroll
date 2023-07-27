<?php

namespace Dx\Payroll\Models;

use Dx\Payroll\DxServiceProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZohoRecordValue extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = DxServiceProvider::DX_PREFIX_TABLE.'zoho_record_values';

    protected $fillable = [
        'record_id',
        'field_id',
        'section_id',
        'row_id',
        'value',
    ];

    public function records(){
        return $this->belongsTo(ZohoRecordValue::class,'id', 'record_id');
    }
}
