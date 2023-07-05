<?php

namespace Dx\Payroll\Models;

use Dx\Payroll\DxServiceProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZohoForm extends Model
{
    use HasFactory;

    protected $table = DxServiceProvider::DX_PREFIX_TABLE.'zoho_forms';

    protected $fillable = [
        'zoho_id',
        'form_name',
        'form_link_name',
        'status',
    ];

    protected $hidden = [

    ];

    public function formSection(){
        return $this->hasMany(ZohoSection::class,'form_id', 'id');
    }

    public function attribute(){
        return $this->hasMany(ZohoRecordField::class,'form_id', 'id');
    }

}
