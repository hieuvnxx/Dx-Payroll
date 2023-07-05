<?php

namespace Dx\Payroll\Models;

use Dx\Payroll\DxServiceProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZohoForm extends Model
{
    use HasFactory;

    protected $table = DxServiceProvider::DX_PREFIX_TABLE.'zoho_form';

    protected $fillable = [
        'form_name',
        'zoho_id',
        'form_slug',
        'status',
    ];

    protected $hidden = [

    ];

    public function formSection(){
        return $this->hasMany(Sections::class,'form_id', 'id');
    }

    public function attribute(){
        return $this->hasMany(Attributes::class,'form_id', 'id');
    }

}
