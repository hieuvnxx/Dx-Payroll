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
        'component_id',
        'display_name',
        'form_link_name',
        'is_custom',
        'is_visible',
        'view_id',
        'view_name',
    ];

    protected $casts = [
        'is_custom' => 'boolean',
        'is_visible' => 'boolean',
    ];

    public function sections()
    {
        return $this->hasMany(ZohoSection::class, 'form_id', 'id');
    }

    public function attributes() 
    {
        return $this->hasMany(ZohoRecordField::class, 'form_id', 'id')->where('section_id', 0)->where('deleted_at', null);
    }

    public function sectionAttributes() 
    {
        return $this->hasMany(ZohoRecordField::class, 'form_id', 'id')->where('section_id', '!=', 0)->where('deleted_at', null);
    }
}
