<?php

namespace Dx\Payroll\Models;

use Dx\Payroll\DxServiceProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Dx\Payroll\Models\ZohoFormLabel;

class Values extends Model
{
    use HasFactory;

    protected $table = DxServiceProvider::DX_PREFIX_TABLE.'values';

    protected $fillable = [
        'form_name',
        'zoho_id',
        'form_slug',
        'status',
    ];

    protected $hidden = [

    ];

    public function labelForm(){
        return $this->hasMany(ZohoFormLabel::class,'form_id', 'id');
    }

}
