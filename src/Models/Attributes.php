<?php

namespace Dx\Payroll\Models;

use Dx\Payroll\DxServiceProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Dx\Payroll\Models\ZohoFormLabel;

class Attributes extends Model
{
    use HasFactory;

    protected $table = DxServiceProvider::DX_PREFIX_TABLE.'attributes';

    protected $fillable = [
        'id',
        'form_id',
        'attributes_name',
        'attributes_label',
        'type',
        'section_id',
    ];

    protected $hidden = [

    ];

//    public function labelForm(){
//        return $this->hasMany(ZohoFormLabel::class,'form_id', 'id');
//    }

}
