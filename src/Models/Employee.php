<?php

namespace Dx\Payroll\Models;

use Dx\Payroll\DxServiceProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $table = DxServiceProvider::DX_PREFIX_TABLE.'employees';

    protected $fillable = [

    ];

    protected $hidden = [

    ];

    public function getFullNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function offerSalary()
    {
        return $this->hasMany(OfferSalary::class,'code', 'code');
    }

    public function bonus()
    {
        return $this->hasMany(BonusOther::class,'code', 'code');
    }

    public function allowance()
    {
        return $this->hasMany(Allowance::class,'code', 'code');
    }

    public function adjustment()
    {
        return $this->hasMany(Adjustment::class,'code', 'code');
    }

}
