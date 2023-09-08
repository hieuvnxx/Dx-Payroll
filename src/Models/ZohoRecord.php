<?php

namespace Dx\Payroll\Models;

use Dx\Payroll\DxServiceProvider;
use Dx\Payroll\Models\ZohoRecordValue;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZohoRecord extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = DxServiceProvider::DX_PREFIX_TABLE.'zoho_records';

    protected $fillable = [
        'zoho_id',
        'form_id',
    ];

    public function values()
    {
        return $this->hasMany(ZohoRecordValue::class, 'record_id', 'id');
    }

    public function form()
    {
        return $this->belongsTo(ZohoForm::class, 'form_id', 'id');
    }
}
