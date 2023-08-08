<?php

namespace Dx\Payroll\Models;

use Dx\Payroll\DxServiceProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Dx\Payroll\Models\ZohoRecordField;
use Dx\Payroll\Models\ZohoSection;

class ZohoForm extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = DxServiceProvider::DX_PREFIX_TABLE.'zoho_forms';

    protected $fillable = [
        'zoho_id',
        'form_name',
        'form_link_name',
        'status',
    ];

    public function sections()
    {
        return $this->hasMany(ZohoSection::class, 'form_id', 'id');
    }

    public function attributes() 
    {
        return $this->hasMany(ZohoRecordField::class, 'form_id', 'id')->where('section_id', 0);
    }

    public function sectionAttributes() 
    {
        return $this->hasMany(ZohoRecordField::class, 'form_id', 'id')->where('section_id', '!=', 0);
    }
}
