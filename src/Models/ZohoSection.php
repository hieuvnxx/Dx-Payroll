<?php

namespace Dx\Payroll\Models;

use Dx\Payroll\DxServiceProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZohoSection extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = DxServiceProvider::DX_PREFIX_TABLE.'zoho_sections';

    protected $fillable = [
        'form_id',
        'section_id',
        'section_name',
        'section_label',
    ];

    public function attributes() 
    {
        return $this->hasMany(ZohoRecordField::class, 'section_id', 'id');
    }
}
